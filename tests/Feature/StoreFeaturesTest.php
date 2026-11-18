<?php

namespace Tests\Feature;

use App\Livewire\Customers\Form as CustomerForm;
use App\Livewire\Marketing\Automations;
use App\Livewire\Reports\Commissions;
use App\Livewire\Stock\Labels;
use App\Models\Category;
use App\Models\CommissionClosing;
use App\Models\Customer;
use App\Models\CustomerChild;
use App\Models\Payable;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\User;
use App\Services\CashService;
use App\Services\CommissionService;
use App\Services\SaleReturnService;
use App\Services\SaleService;
use App\Services\StockEntryService;
use App\Support\CustomerFilter;
use App\Support\LabelPrinter;
use Database\Seeders\PaymentMethodsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class StoreFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private ProductVariant $variant;

    private PaymentMethod $pix;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PaymentMethodsSeeder::class);
        $this->pix = PaymentMethod::where('type', 'pix')->sole();

        $category = Category::create(['name' => 'Camisas']);
        $product = Product::create(['name' => 'Camisa Social', 'category_id' => $category->id, 'sale_price' => '100.00']);
        $this->variant = new ProductVariant(['product_id' => $product->id, 'sku' => 'CS-M', 'barcode' => '2000000000015', 'sale_price' => '100.00', 'cost_price' => '40.00']);
        $this->variant->stock_on_hand = 50;
        $this->variant->save();
    }

    private function seller(string $name, ?float $percent): User
    {
        $user = $this->userWith(['pdv.vender', 'caixa.operar', 'comissoes.ver_propria'], ['name' => $name]);
        $user->forceFill(['commission_percent' => $percent])->save();
        app(CashService::class)->open($user, 0);

        return $user;
    }

    private function sell(User $seller, int $quantity): Sale
    {
        return app(SaleService::class)->finalize($seller, [['variant_id' => $this->variant->id, 'quantity' => $quantity, 'discount' => 0]],
            [['method_id' => $this->pix->id, 'amount' => 10000 * $quantity]], null, 0, null, (string) Str::uuid());
    }

    public function test_adult_segment_hides_children_but_keeps_existing_data(): void
    {
        config(['loja.segment' => 'adulto']);
        $manager = $this->userWith(['clientes.ver', 'clientes.criar', 'clientes.editar', 'marketing.automacoes']);
        $customer = Customer::create(['name' => 'Paula Reis']);
        $customer->children()->create(['name' => 'Lia', 'birth_date' => '2020-01-01']);

        $this->actingAs($manager);
        $this->get(route('customers.create'))->assertOk()->assertDontSee('Adicionar filho');
        $this->get(route('customers.show', $customer))->assertOk()->assertDontSee('Nenhum filho cadastrado');
        $this->get(route('customers.index'))->assertOk()->assertDontSee('Tamanho do filho');

        Livewire::test(CustomerForm::class, ['customer' => $customer->id])->set('notes', 'Prefere camisas slim')->call('save')->assertHasNoErrors();
        $this->assertSame(1, CustomerChild::count(), 'Os filhos já cadastrados continuam guardados.');

        // Um segmento antigo com filtro de filhos não esconde clientes em loja adulta.
        $this->assertSame(1, CustomerFilter::apply(Customer::query(), ['has_children' => '0'])->count());

        Livewire::test(Automations::class)->assertDontSee('Aniversário do filho')->assertSee('Aniversário do cliente');

        config(['loja.segment' => 'misto']);
        $this->get(route('customers.create'))->assertSee('Adicionar filho');
    }

    public function test_commission_is_percent_of_net_sales_and_month_closing_creates_payables(): void
    {
        Carbon::setTestNow('2026-10-10 10:00:00');
        $ana = $this->seller('Ana Vendas', 3);
        $bia = $this->seller('Bia Vendas', null);

        $sale = $this->sell($ana, 3);
        $this->sell($bia, 1);
        app(SaleReturnService::class)->register($ana, $sale, [['sale_item_id' => $sale->items()->value('id'), 'quantity' => 1, 'restock' => true]], 'Troca', $this->pix->id);

        $rows = app(CommissionService::class)->report(Carbon::parse('2026-10-01'))->keyBy(fn ($r) => $r->user->name);
        $this->assertSame([30000, 10000, 20000, 600], [$rows['Ana Vendas']->sales, $rows['Ana Vendas']->returns, $rows['Ana Vendas']->base, $rows['Ana Vendas']->amount]);
        $this->assertSame(0, $rows['Bia Vendas']->amount);

        $manager = $this->userWith(['comissoes.ver', 'comissoes.fechar', 'comissoes.ver_propria']);
        $this->actingAs($manager);

        Livewire::test(Commissions::class, ['month' => '2026-10'])->call('close')->assertSee('meses que já terminaram');

        Carbon::setTestNow('2026-11-02 09:00:00');
        Livewire::test(Commissions::class)->set('month', '2026-10')->set('dueDate', '2026-11-05')->call('close')->assertSee('Mês fechado');

        $payable = Payable::where('description', 'like', 'Comissão%')->sole();
        $this->assertSame('6.00', $payable->amount);
        $this->assertSame('Comissões', $payable->category->name);
        $this->assertSame(2, CommissionClosing::count());

        // Depois do fechamento, mudar o percentual não altera o mês fechado.
        $ana->forceFill(['commission_percent' => 10])->save();
        $this->assertSame(600, app(CommissionService::class)->report(Carbon::parse('2026-10-01'))->firstWhere('user.id', $ana->id)->amount);

        Livewire::test(Commissions::class)->set('month', '2026-10')->call('reopen');
        $this->assertSame(0, CommissionClosing::count());
        $this->assertSame(Payable::CANCELLED, $payable->fresh()->status);
        Carbon::setTestNow();
    }

    public function test_seller_sees_only_own_commission(): void
    {
        $ana = $this->seller('Ana Vendas', 3);
        $bia = $this->seller('Bia Vendas', 2);
        $this->sell($ana, 1);
        $this->sell($bia, 1);

        $this->actingAs($ana);
        Livewire::test(Commissions::class)->assertSee('Ana Vendas')->assertDontSee('Bia Vendas')->assertDontSee('Fechar o mês');

        $this->actingAs(User::factory()->create());
        $this->get(route('reports.commissions'))->assertForbidden();
    }

    public function test_labels_from_entry_print_with_barcodes(): void
    {
        $user = $this->userWith(['produtos.ver', 'estoque.entrada']);
        $entry = app(StockEntryService::class)->register($user, null, 'NF 10', null, [['variant_id' => $this->variant->id, 'quantity' => 4, 'unit_cost' => '40.00']]);

        $this->actingAs($user);
        Livewire::withQueryParams(['entrada' => $entry->id])->test(Labels::class)
            ->assertSet('items', [$this->variant->id => 4])
            ->dispatch('variant-picked', id: $this->variant->id)
            ->assertSet('items', [$this->variant->id => 5])
            ->set('format', 'rolo')
            ->call('print')
            ->assertRedirect(route('stock.labels.print'));

        $this->get(route('stock.labels.print'))->assertOk()
            ->assertSee('5 etiqueta(s)')
            ->assertSee('2000000000015')
            ->assertSee('R$ 100,00')
            ->assertSee('<svg', false);

        // EAN com dígito errado sai em Code 128, sem quebrar a impressão.
        $this->variant->forceFill(['barcode' => '2000000000019'])->save();
        $this->assertStringContainsString('<svg', LabelPrinter::svg($this->variant->fresh()));
    }
}
