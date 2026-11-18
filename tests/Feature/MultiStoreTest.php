<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Exceptions\BusinessRuleException;
use App\Livewire\Finance\PayableForm;
use App\Livewire\Stores\Index as StoreIndex;
use App\Models\Category;
use App\Models\ExpenseCategory;
use App\Models\Payable;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\StockTransfer;
use App\Models\Store;
use App\Models\User;
use App\Services\CashService;
use App\Services\FinanceReports;
use App\Services\SaleService;
use App\Services\StockService;
use App\Services\StockTransferService;
use App\Services\StoreService;
use App\Support\CurrentStore;
use Database\Seeders\PaymentMethodsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class MultiStoreTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private User $admin;

    private Store $main;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-03-10 10:00:00');
        $this->seed(PaymentMethodsSeeder::class);
        config(['loja.plan.max_stores' => 2, 'loja.plan.name' => 'Multi Lojas']);

        $this->admin = $this->userWith(['lojas.gerir', 'lojas.ver_todas', 'estoque.transferir']);
        $this->main = Store::where('is_main', true)->sole();

        $category = Category::create(['name' => 'Blusas']);
        $product = Product::create(['name' => 'Blusa Linho', 'category_id' => $category->id, 'sale_price' => '100.00']);
        $this->variant = new ProductVariant(['product_id' => $product->id, 'sku' => 'BL-M', 'sale_price' => '100.00', 'cost_price' => '40.00']);
        $this->variant->stock_on_hand = 10;
        $this->variant->save();
    }

    protected function tearDown(): void
    {
        CurrentStore::use(null);
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function branch(array $users = []): Store
    {
        $store = app(StoreService::class)->save(null, ['name' => 'Loja Shopping', 'code' => 'shop', 'phone' => null, 'address' => null, 'city' => null, 'is_active' => true], $this->admin);
        app(StoreService::class)->syncUsers($store, array_map(fn (User $u) => $u->id, $users), $this->admin);

        return $store;
    }

    private function transfer(Store $to, int $quantity, ?User $actor = null): StockTransfer
    {
        return app(StockTransferService::class)->send($actor ?? $this->admin, $this->main->id, $to->id, [['variant_id' => $this->variant->id, 'quantity' => $quantity]], null);
    }

    private function sellAt(User $seller, Store $store, int $quantity): Sale
    {
        CurrentStore::use($store->id);
        app(CashService::class)->open($seller, 0);
        CurrentStore::use(null);

        $pix = PaymentMethod::where('type', 'pix')->sole();

        return app(SaleService::class)->finalize($seller, [['variant_id' => $this->variant->id, 'quantity' => $quantity, 'discount' => 0]],
            [['method_id' => $pix->id, 'amount' => 10000 * $quantity]], null, 0, null, (string) Str::uuid());
    }

    public function test_single_store_plan_blocks_new_branches(): void
    {
        config(['loja.plan.max_stores' => 1]);

        try {
            $this->branch();
            $this->fail('Deveria bloquear a filial.');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString('plano Multi Lojas', $e->getMessage());
        }

        $this->assertSame(1, Store::count());

        Livewire::actingAs($this->admin)->test(StoreIndex::class)
            ->assertSeeHtml('<strong>1</strong> de <strong>1</strong>')
            ->assertSee('Limite do plano atingido')
            ->call('create')->set('name', 'Filial Norte')->set('code', 'NORTE')->call('save')
            ->assertSee('Seu plano inclui uma loja');
        $this->assertSame(1, Store::count());
    }

    public function test_plan_limit_counts_active_stores_and_inactive_store_can_be_saved(): void
    {
        $branch = $this->branch();
        $this->assertSame(['name' => 'Multi Lojas', 'max' => 2, 'active' => 2, 'available' => 0], app(StoreService::class)->plan());
        $this->assertSame('SHOP', $branch->code);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Seu plano permite 2 lojas ativas');

        // Uma loja inativa pode ser cadastrada; ativar passa do limite.
        $third = app(StoreService::class)->save(null, ['name' => 'Loja Norte', 'code' => 'NORTE', 'phone' => null, 'address' => null, 'city' => null, 'is_active' => false], $this->admin);
        app(StoreService::class)->save($third, ['name' => 'Loja Norte', 'code' => 'NORTE', 'phone' => null, 'address' => null, 'city' => null, 'is_active' => true], $this->admin);
    }

    public function test_main_store_cannot_be_deactivated_and_branch_with_stock_neither(): void
    {
        $branch = $this->branch();

        try {
            app(StoreService::class)->save($this->main, ['name' => $this->main->name, 'code' => $this->main->code, 'phone' => null, 'address' => null, 'city' => null, 'is_active' => false], $this->admin);
            $this->fail('A principal não pode ser desativada.');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString('principal', $e->getMessage());
        }

        $this->transfer($branch, 2);
        try {
            app(StoreService::class)->save($branch, ['name' => 'Loja Shopping', 'code' => 'SHOP', 'phone' => null, 'address' => null, 'city' => null, 'is_active' => false], $this->admin);
            $this->fail('Transferência a caminho deveria impedir.');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString('a caminho', $e->getMessage());
        }
    }

    public function test_transfer_moves_stock_between_stores_only_when_received(): void
    {
        $branch = $this->branch();
        $transfer = $this->transfer($branch, 4);
        $variant = $this->variant->fresh();

        $this->assertSame(6, $variant->availableIn($this->main->id));
        $this->assertSame(0, $variant->availableIn($branch->id));
        $this->assertSame(6, $variant->stock_on_hand, 'A caminho fica fora das duas lojas.');

        app(StockTransferService::class)->receive($this->admin, $transfer);
        $variant = $this->variant->fresh();
        $this->assertSame(6, $variant->availableIn($this->main->id));
        $this->assertSame(4, $variant->availableIn($branch->id));
        $this->assertSame(10, $variant->stock_on_hand);
        $this->assertSame(StockTransfer::RECEIVED, $transfer->fresh()->status);

        $this->expectException(BusinessRuleException::class);
        app(StockTransferService::class)->receive($this->admin, $transfer);
    }

    public function test_cancelled_transfer_returns_to_origin_and_cannot_exceed_origin_stock(): void
    {
        $branch = $this->branch();
        $transfer = $this->transfer($branch, 3);
        app(StockTransferService::class)->cancel($this->admin, $transfer);

        $this->assertSame(10, $this->variant->fresh()->availableIn($this->main->id));
        $this->assertSame(StockTransfer::CANCELLED, $transfer->fresh()->status);

        $this->expectException(BusinessRuleException::class);
        $this->transfer($branch, 11);
    }

    public function test_transfer_requires_permission_and_working_at_the_store(): void
    {
        $seller = $this->userWith(['pdv.vender']);
        $branchWorker = $this->userWith(['estoque.transferir']);
        $branch = $this->branch([$branchWorker]);

        foreach ([$seller, $branchWorker] as $actor) {
            try {
                $this->transfer($branch, 1, $actor);
                $this->fail('Envio sem permissão ou de outra loja.');
            } catch (BusinessRuleException) {
            }
        }

        $transfer = $this->transfer($branch, 1);
        app(StockTransferService::class)->receive($branchWorker, $transfer);
        $this->assertSame(1, $this->variant->fresh()->availableIn($branch->id));
    }

    public function test_sale_uses_the_stock_of_the_cash_register_store(): void
    {
        $seller = $this->userWith(['pdv.vender', 'caixa.operar']);
        $branch = $this->branch([$seller]);
        app(StockTransferService::class)->receive($this->admin, $this->transfer($branch, 2));

        $sale = $this->sellAt($seller, $branch, 2);
        $this->assertSame($branch->id, $sale->store_id);
        $this->assertSame(0, $this->variant->fresh()->availableIn($branch->id));
        $this->assertSame(8, $this->variant->fresh()->availableIn($this->main->id));

        // Sem saldo na filial, a venda é recusada mesmo com estoque na principal.
        $this->expectException(BusinessRuleException::class);
        app(SaleService::class)->finalize($seller, [['variant_id' => $this->variant->id, 'quantity' => 1, 'discount' => 0]],
            [['method_id' => PaymentMethod::where('type', 'pix')->value('id'), 'amount' => 10000]], null, 0, null, (string) Str::uuid());
    }

    public function test_cancelling_branch_sale_returns_stock_to_the_branch(): void
    {
        $seller = $this->userWith(['pdv.vender', 'caixa.operar', 'vendas.cancelar']);
        $branch = $this->branch([$seller]);
        app(StockTransferService::class)->receive($this->admin, $this->transfer($branch, 2));
        $sale = $this->sellAt($seller, $branch, 1);

        app(SaleService::class)->cancel($seller, $sale, 'Cliente desistiu da compra', $this->admin);

        $this->assertSame(2, $this->variant->fresh()->availableIn($branch->id));
        $this->assertSame(8, $this->variant->fresh()->availableIn($this->main->id));
    }

    public function test_stock_adjustment_in_branch_does_not_touch_main(): void
    {
        $branch = $this->branch();
        app(StockService::class)->register($this->variant, MovementType::Entry, 5, 'Compra direta', $this->admin, storeId: $branch->id);

        $this->assertSame(5, $this->variant->fresh()->availableIn($branch->id));
        $this->assertSame(10, $this->variant->fresh()->availableIn($this->main->id));
        $this->assertSame(15, $this->variant->fresh()->stock_on_hand);
    }

    public function test_user_linked_to_branch_works_only_there(): void
    {
        $seller = $this->userWith(['pdv.vender']);
        $branch = $this->branch([$seller]);

        $this->assertSame([$branch->id], CurrentStore::allowedIds($seller));
        $this->assertSame(2, count(CurrentStore::allowedIds($this->admin)));

        $this->actingAs($seller)->post(route('stores.switch', $this->main->id))->assertForbidden();
        $this->actingAs($this->admin)->post(route('stores.switch', $branch->id))->assertSessionHas('success');
        $this->actingAs($seller)->get('/')->assertOk();
        $this->assertSame($branch->id, CurrentStore::id());
    }

    public function test_reports_filter_by_store(): void
    {
        $seller = $this->userWith(['pdv.vender', 'caixa.operar']);
        $other = $this->userWith(['pdv.vender', 'caixa.operar']);
        $branch = $this->branch([$seller]);
        app(StockTransferService::class)->receive($this->admin, $this->transfer($branch, 3));

        $this->sellAt($seller, $branch, 2);
        $this->sellAt($other, $this->main, 1);

        $reports = app(FinanceReports::class);
        $day = today();
        $this->assertSame(20000, $reports->forStore($branch->id)->salesSummary($day, $day)['gross']);
        $this->assertSame(10000, $reports->forStore($this->main->id)->salesSummary($day, $day)['gross']);
        $this->assertSame(30000, $reports->salesSummary($day, $day)['gross'], 'forStore não altera o original.');
    }

    public function test_payable_can_belong_to_a_store(): void
    {
        $branch = $this->branch();
        $manager = $this->userWith(['financeiro.pagar', 'lojas.ver_todas']);
        $category = ExpenseCategory::where('is_active', true)->whereNull('system_key')->first()
            ?? ExpenseCategory::create(['name' => 'Aluguel', 'is_active' => true]);

        Livewire::actingAs($manager)->test(PayableForm::class)
            ->assertSee('Geral (toda a rede)')
            ->set('description', 'Aluguel do shopping')->set('categoryId', (string) $category->id)
            ->set('storeId', (string) $branch->id)->set('amount', '4100')->call('save')->assertHasNoErrors();

        $this->assertSame($branch->id, Payable::where('description', 'Aluguel do shopping')->sole()->store_id);
    }

    public function test_store_pages_require_permission(): void
    {
        $seller = $this->userWith(['pdv.vender', 'estoque.ver']);

        $this->actingAs($seller)->get(route('stores.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('stores.index'))->assertOk()->assertSee('Multi Lojas');
        $this->actingAs($seller)->get(route('stock.transfers.create'))->assertForbidden();
    }
}
