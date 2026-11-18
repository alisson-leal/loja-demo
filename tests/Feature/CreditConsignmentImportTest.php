<?php

namespace Tests\Feature;

use App\Enums\CashMovementType;
use App\Exceptions\BusinessRuleException;
use App\Livewire\Finance\Crediario;
use App\Livewire\Products\Form as ProductForm;
use App\Livewire\Products\Import;
use App\Livewire\Sales\ConsignmentShow;
use App\Livewire\Sales\Pos;
use App\Models\CashMovement;
use App\Models\Category;
use App\Models\Consignment;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Receivable;
use App\Models\Sale;
use App\Models\StockEntry;
use App\Models\User;
use App\Models\VariationAttribute;
use App\Services\CashService;
use App\Services\ConsignmentService;
use App\Services\CrediarioService;
use App\Services\FinanceReports;
use App\Services\ReceivableService;
use App\Services\SaleService;
use Database\Seeders\PaymentMethodsSeeder;
use Database\Seeders\VariationAttributesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class CreditConsignmentImportTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private User $seller;

    private User $manager;

    private ProductVariant $variant;

    private PaymentMethod $crediario;

    private PaymentMethod $pix;

    private PaymentMethod $cash;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-03-10 10:00:00');
        $this->seed([PaymentMethodsSeeder::class, VariationAttributesSeeder::class]);
        $this->crediario = PaymentMethod::where('type', 'crediario')->sole();
        $this->pix = PaymentMethod::where('type', 'pix')->sole();
        $this->cash = PaymentMethod::where('type', 'cash')->sole();

        $this->seller = $this->userWith(['pdv.vender', 'caixa.operar', 'crediario.vender', 'crediario.receber', 'condicional.criar', 'condicional.acertar']);
        $this->manager = $this->userWith(['pdv.vender', 'caixa.operar', 'crediario.vender', 'crediario.receber', 'crediario.gerir', 'financeiro.ver', 'relatorios.resultado']);
        app(CashService::class)->open($this->seller, 0);
        app(CashService::class)->open($this->manager, 0);

        $category = Category::create(['name' => 'Blusas']);
        $product = Product::create(['name' => 'Blusa Linho', 'category_id' => $category->id, 'sale_price' => '100.00']);
        $this->variant = new ProductVariant(['product_id' => $product->id, 'sku' => 'BL-M', 'sale_price' => '100.00', 'cost_price' => '40.00']);
        $this->variant->stock_on_hand = 10;
        $this->variant->save();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function customer(?float $limit): Customer
    {
        $customer = Customer::create(['name' => 'Rita Moura', 'whatsapp' => '86999990000']);
        $customer->forceFill(['credit_limit' => $limit])->save();

        return $customer;
    }

    private function sellOnCredit(User $actor, ?Customer $customer, int $quantity = 3, int $installments = 3, ?int $consignmentId = null): Sale
    {
        return app(SaleService::class)->finalize($actor, [['variant_id' => $this->variant->id, 'quantity' => $quantity, 'discount' => 0]],
            [['method_id' => $this->crediario->id, 'amount' => 10000 * $quantity, 'installments' => $installments]],
            $customer?->id, 0, null, (string) Str::uuid(), $consignmentId);
    }

    public function test_crediario_sale_creates_monthly_installments_within_the_limit(): void
    {
        $customer = $this->customer(400);
        $this->sellOnCredit($this->seller, $customer);

        $installments = Receivable::crediario()->orderBy('installment')->get();
        $this->assertSame(['2026-04-09', '2026-05-09', '2026-06-08'], $installments->map(fn ($r) => $r->due_date->toDateString())->all());
        $this->assertSame(['100.00', '100.00', '100.00'], $installments->pluck('net_amount')->all());
        $this->assertSame('0.00', $installments->first()->fee_amount);
        $this->assertSame(10000, app(CrediarioService::class)->position($customer)['available']);

        // Acima do limite só com a gerente.
        try {
            $this->sellOnCredit($this->seller, $customer, 2, 2);
            $this->fail('Deveria barrar acima do limite.');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString('limite de crediário', $e->getMessage());
        }
        $this->sellOnCredit($this->manager, $customer, 2, 2);
        $this->assertSame(5, Receivable::crediario()->count());

        // Sem cliente ou sem limite definido, não vende.
        $this->expectException(BusinessRuleException::class);
        $this->sellOnCredit($this->seller, null);
    }

    public function test_late_installment_charges_fee_and_interest_and_blocks_new_credit(): void
    {
        $customer = $this->customer(2000);
        $this->sellOnCredit($this->seller, $customer, 2, 2);
        $first = Receivable::crediario()->orderBy('installment')->first();

        Carbon::setTestNow('2026-04-24 10:00:00'); // 15 dias depois do vencimento de 09/04
        $charges = app(CrediarioService::class)->charges($first->fresh());
        $this->assertSame(['days' => 15, 'fee' => 200, 'interest' => 50, 'total' => 10250], $charges);

        // Vencida há mais de 10 dias: a vendedora não consegue vender de novo no crediário.
        try {
            app(CashService::class)->open($this->seller, 0);
        } catch (BusinessRuleException) {
        }
        $this->expectExceptionMessage('vencida há 15 dias');
        $this->sellOnCredit($this->seller, $customer, 1, 1);
    }

    public function test_receiving_an_installment_in_cash_goes_to_the_drawer_and_to_the_result(): void
    {
        $customer = $this->customer(2000);
        $sale = $this->sellOnCredit($this->seller, $customer, 2, 2);
        $first = Receivable::crediario()->orderBy('installment')->first();

        Carbon::setTestNow('2026-04-24 10:00:00');
        $this->actingAs($this->seller);

        Livewire::test(Crediario::class)
            ->assertSee('Rita Moura')
            ->call('startReceive', $first->id)
            ->set('waive', true)
            ->call('receive')
            ->assertSee('Só um gerente pode dispensar');

        Livewire::test(Crediario::class)->call('startReceive', $first->id)->set('methodId', (string) $this->cash->id)->call('receive')->assertSee('R$ 102,50');

        $first->refresh();
        $this->assertSame(Receivable::RECEIVED, $first->status);
        $this->assertSame('102.50', $first->received_amount);
        $this->assertTrue(CashMovement::where('type', CashMovementType::Income)->where('amount', '102.50')->exists());

        $result = app(FinanceReports::class)->result(Carbon::parse('2026-04-01'), Carbon::parse('2026-04-30'));
        $this->assertSame(250, $result['interest']);
        $this->assertSame(10250, app(FinanceReports::class)->cashFlow(Carbon::parse('2026-04-24'), Carbon::parse('2026-04-24'))['totals']['received']);

        // Crediário não aparece nos recebimentos de cartão nem é confirmado por lá.
        $second = Receivable::crediario()->where('installment', 2)->sole();
        $this->assertSame(0, app(ReceivableService::class)->confirm([$second->id], today(), $this->manager));

        // Venda com parcela paga não pode ser cancelada.
        $this->expectExceptionMessage('parcelas do crediário já pagas');
        app(SaleService::class)->cancel($this->manager, $sale, 'Teste');
    }

    public function test_consignment_reserves_stock_and_settlement_goes_to_the_cashier(): void
    {
        $customer = $this->customer(null);
        $consignment = app(ConsignmentService::class)->open($this->seller, $customer->id, [['variant_id' => $this->variant->id, 'quantity' => 3]], today()->addDays(3), null);

        $this->assertSame(7, $this->variant->fresh()->available());
        $this->assertSame(10, $this->variant->fresh()->stock_on_hand);

        $this->actingAs($this->seller);
        $item = $consignment->items()->sole();
        Livewire::test(ConsignmentShow::class, ['consignment' => $consignment->id])
            ->set("returned.{$item->id}", '1')
            ->call('settle')
            ->assertRedirect(route('pos'));

        $this->assertSame(Consignment::AWAITING_SALE, $consignment->fresh()->status);
        $this->assertSame(8, $this->variant->fresh()->available(), 'A peça devolvida volta; as duas que ficaram seguem reservadas.');

        // O PDV abre com as peças do acerto e o cliente.
        $pos = Livewire::test(Pos::class)
            ->assertSet('consignmentId', $consignment->id)
            ->assertSet('customerId', $customer->id)
            ->assertSee('condicional #'.$consignment->number());
        $this->assertSame([['variant_id' => $this->variant->id, 'quantity' => '2', 'discount' => '']], $pos->get('cart'));

        $pos->call('addPayment', $this->pix->id)->call('finalize');

        $sale = Sale::sole();
        $consignment->refresh();
        $this->assertSame(Consignment::CLOSED, $consignment->status);
        $this->assertSame($sale->id, $consignment->sale_id);
        $this->assertSame(8, $this->variant->fresh()->stock_on_hand);
        $this->assertSame(8, $this->variant->fresh()->available());
    }

    public function test_consignment_closed_without_sale_releases_everything(): void
    {
        $customer = $this->customer(null);
        $service = app(ConsignmentService::class);
        $consignment = $service->open($this->seller, $customer->id, [['variant_id' => $this->variant->id, 'quantity' => 2]], today(), null);
        $service->settle($consignment, [], $this->seller);

        $this->expectException(BusinessRuleException::class);
        try {
            $service->settle($consignment, [], $this->seller);
        } finally {
            $service->closeWithoutSale($consignment, $this->seller);
            $this->assertSame(10, $this->variant->fresh()->available());
            $this->assertSame(Consignment::CLOSED, $consignment->fresh()->status);
        }
    }

    public function test_spreadsheet_import_validates_then_creates_products_variants_and_stock(): void
    {
        $user = $this->userWith(['produtos.criar', 'produtos.ver', 'produtos.ver_custo', 'estoque.entrada']);
        $this->actingAs($user);

        $bad = "nome;categoria;tamanho;cor;preco_venda;codigo_barras\nCamisa;Masculino;P;Azul;abc;789\nCamisa;Masculino;M;Azul;59,90;789\n";
        Livewire::test(Import::class)
            ->set('file', UploadedFile::fake()->createWithContent('ruim.csv', $bad))
            ->call('analyze')
            ->assertSee('preço de venda inválido')
            ->assertSee('repetido na planilha')
            ->assertDontSee('Importar</button>', false);

        $good = mb_convert_encoding("nome;categoria;subcategoria;marca;tamanho;cor;preco_venda;preco_custo;estoque_inicial\n"
            ."Calça Alfaiataria;Feminino;Calças;Marca X;38;Preto;1.199,90;500,00;2\n"
            ."Calça Alfaiataria;Feminino;Calças;Marca X;40;Preto;1.199,90;500,00;3\n"
            ."Bolsa Couro;Acessórios;;;;Caramelo;249,90;;1\n", 'Windows-1252', 'UTF-8');

        Livewire::test(Import::class)
            ->set('file', UploadedFile::fake()->createWithContent('bom.csv', $good))
            ->call('analyze')
            ->assertSee('2</strong> produto(s)', false)
            ->assertSee('Categorias novas: Feminino, Acessórios')
            ->call('import')
            ->assertRedirect(route('products.index'));

        $pants = Product::where('name', 'Calça Alfaiataria')->sole();
        $this->assertSame('Calças', $pants->category->name);
        $this->assertSame('Feminino', $pants->category->parent->name);
        $this->assertSame(2, $pants->variants()->count());
        $this->assertSame('1199.90', $pants->variants()->first()->sale_price);
        $this->assertSame(6, (int) ProductVariant::whereIn('product_id', Product::pluck('id'))->where('sku', '!=', 'BL-M')->sum('stock_on_hand'));
        $this->assertTrue(VariationAttribute::where('name', 'Tamanho')->sole()->values()->where('value', '38')->exists());
        $this->assertSame(1, StockEntry::count(), 'Uma entrada por fornecedor; os dois produtos não têm fornecedor.');

        $this->get(route('products.import.template'))->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_product_grid_with_initial_stock_creates_an_entry(): void
    {
        $user = $this->userWith(['produtos.criar', 'produtos.ver', 'produtos.ver_custo', 'estoque.entrada']);
        $this->actingAs($user);
        $sizes = VariationAttribute::where('name', 'Tamanho')->sole();
        $p = $sizes->values()->where('value', 'P')->value('id');
        $m = $sizes->values()->where('value', 'M')->value('id');

        Livewire::test(ProductForm::class)
            ->set('name', 'Regata Básica')
            ->set('categoryId', (string) Category::first()->id)
            ->set('salePrice', '39.90')
            ->set('costPrice', '15.00')
            ->set("selectedValues.{$sizes->id}", [(string) $p, (string) $m])
            ->call('generate')
            ->set('initialForAll', '4')
            ->call('applyInitialStock')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('products.index'));

        $product = Product::where('name', 'Regata Básica')->sole();
        $this->assertSame(8, (int) $product->variants()->sum('stock_on_hand'));
        $this->assertSame('Estoque inicial do cadastro', StockEntry::latest('id')->value('notes'));
    }
}
