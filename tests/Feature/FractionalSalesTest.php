<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Exceptions\BusinessRuleException;
use App\Livewire\Products\Form as ProductForm;
use App\Livewire\Sales\ConsignmentForm;
use App\Livewire\Sales\Pos;
use App\Livewire\Sales\ReturnForm;
use App\Livewire\Stock\Entry;
use App\Livewire\Stock\InventoryCount;
use App\Livewire\Stock\Movement;
use App\Livewire\Stock\TransferForm;
use App\Models\Category;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use App\Services\CashService;
use App\Services\ConsignmentService;
use App\Services\FinanceReports;
use App\Services\InventoryService;
use App\Services\ProductService;
use App\Services\SaleReceiptService;
use App\Services\SaleReturnService;
use App\Services\SaleService;
use App\Services\StockEntryService;
use App\Services\StockService;
use App\Services\StoreService;
use App\Support\CurrentStore;
use App\Support\Quantity;
use App\Support\SaleCalculator;
use Database\Seeders\PaymentMethodsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class FractionalSalesTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private User $operator;

    private PaymentMethod $pix;

    /** Tecido vendido por metro: R$ 12,90/m, custo R$ 6,00/m. */
    private ProductVariant $fabric;

    /** Granel vendido por quilo: R$ 39,90/kg. */
    private ProductVariant $candy;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-03-10 10:00:00');

        $this->seed(PaymentMethodsSeeder::class);
        $this->pix = PaymentMethod::where('type', 'pix')->sole();
        $this->operator = $this->userWith(['pdv.vender', 'caixa.operar', 'vendas.devolver', 'vendas.cancelar', 'estoque.entrada', 'estoque.movimentar', 'estoque.ajustar', 'estoque.ver', 'estoque.inventario', 'condicional.criar']);
        app(CashService::class)->open($this->operator, 0);

        $category = Category::create(['name' => 'Tecidos']);
        $fabric = Product::create(['name' => 'Tricoline Estampado', 'category_id' => $category->id, 'sale_price' => '12.90', 'unit' => 'm']);
        $this->fabric = ProductVariant::create(['product_id' => $fabric->id, 'sku' => 'TRI-1', 'barcode' => '7890000000024', 'sale_price' => '12.90', 'cost_price' => '6.00']);

        $candy = Product::create(['name' => 'Bala de Goma', 'category_id' => $category->id, 'sale_price' => '39.90', 'unit' => 'kg']);
        $this->candy = ProductVariant::create(['product_id' => $candy->id, 'sku' => 'BAL-1', 'sale_price' => '39.90', 'cost_price' => '20.00']);
    }

    protected function tearDown(): void
    {
        CurrentStore::use(null);
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function enter(ProductVariant $variant, int $quantity): void
    {
        app(StockEntryService::class)->register($this->operator, null, 'NF 1', null, [
            ['variant_id' => $variant->id, 'quantity' => $quantity, 'unit_cost' => null],
        ]);
    }

    /** @param int $quantity milésimos */
    private function sell(ProductVariant $variant, int $quantity, int $amount): Sale
    {
        return app(SaleService::class)->finalize($this->operator,
            [['variant_id' => $variant->id, 'quantity' => $quantity, 'discount' => 0]],
            [['method_id' => $this->pix->id, 'amount' => $amount]], null, 0, null, (string) Str::uuid());
    }

    public function test_quantity_helper_parses_formats_and_prices(): void
    {
        $this->assertSame(1500, Quantity::parse('1,5', 'm'));
        $this->assertSame(350, Quantity::parse('0.35', 'kg'));
        $this->assertSame(1250, Quantity::parse(' 1,25 ', 'm'));
        $this->assertSame(0, Quantity::parse('1,2,3', 'm'));
        $this->assertSame(0, Quantity::parse('-1', 'kg'));
        $this->assertSame(3, Quantity::parse('3', 'un'));
        $this->assertSame(0, Quantity::parse('2,5', 'un'));

        $this->assertSame('1,5 m', Quantity::format(1500, 'm'));
        $this->assertSame('0,35 kg', Quantity::format(350, 'kg'));
        $this->assertSame('2 m', Quantity::format(2000, 'm'));
        $this->assertSame('12', Quantity::format(12, 'un'));
        $this->assertSame('1.25', Quantity::toInput(1250, 'm'));

        // 1,5 m × R$ 12,90 = R$ 19,35; 0,333 kg × R$ 39,90 = R$ 13,2867 → R$ 13,29.
        $this->assertSame(1935, Quantity::amount(1290, 1500, 'm'));
        $this->assertSame(1329, Quantity::amount(3990, 333, 'kg'));
    }

    public function test_calculator_prices_fractional_lines_and_keeps_units_unchanged(): void
    {
        $result = SaleCalculator::calculate([
            ['unit_price' => 1290, 'quantity' => 1500, 'discount' => 0, 'unit' => 'm'],
            ['unit_price' => 5000, 'quantity' => 2, 'discount' => 0],
        ], 0);

        $this->assertSame(1935 + 10000, $result['total']);
        $this->assertSame(1935, $result['lines'][0]['total']);
    }

    public function test_sale_by_meter_charges_the_measured_length_and_lowers_stock(): void
    {
        $this->enter($this->fabric, 20000);

        $sale = $this->sell($this->fabric, 1500, 1935);
        $item = $sale->items()->sole();

        $this->assertSame('19.35', $sale->total);
        $this->assertSame(1500, $item->quantity);
        $this->assertSame('m', $item->unit);
        $this->assertSame('1,5 m', $item->formatQuantity());
        $this->assertSame(18500, $this->fabric->fresh()->stock_on_hand);
        $this->assertStringContainsString('1,5 m Tricoline Estampado (R$ 12,90/m) - R$ 19,35', app(SaleReceiptService::class)->text($sale));
    }

    public function test_stock_cannot_go_below_zero_and_the_message_shows_the_unit(): void
    {
        $this->enter($this->candy, 500);

        $this->expectExceptionMessage('disponível 0,5 kg, solicitado 0,75 kg');
        $this->sell($this->candy, 750, 2993);
    }

    public function test_cancelling_returns_the_exact_length(): void
    {
        $this->enter($this->fabric, 10000);
        $sale = $this->sell($this->fabric, 2250, 2903);

        app(SaleService::class)->cancel($this->operator, $sale, 'Cliente desistiu');

        $this->assertSame(10000, $this->fabric->fresh()->stock_on_hand);
    }

    public function test_partial_return_of_a_fraction_refunds_proportionally(): void
    {
        $this->enter($this->fabric, 10000);
        $sale = $this->sell($this->fabric, 3000, 3870);
        $item = $sale->items()->sole();

        $return = app(SaleReturnService::class)->register($this->operator, $sale, [
            ['sale_item_id' => $item->id, 'quantity' => 1000, 'restock' => true],
        ], 'Sobrou', null);

        $this->assertSame('12.90', $return->total);
        $this->assertSame(1000, $item->fresh()->returned_quantity);
        $this->assertSame(8000, $this->fabric->fresh()->stock_on_hand);
    }

    public function test_pos_screen_takes_the_typed_length_and_does_not_add_on_rescan(): void
    {
        $this->enter($this->fabric, 10000);

        $pos = Livewire::actingAs($this->operator)->test(Pos::class)
            ->set('cart', [])
            ->dispatch('variant-picked', id: $this->fabric->id)
            ->assertSet('cart.0.quantity', '')
            ->assertDispatched('focus-quantity', id: $this->fabric->id)
            ->dispatch('variant-picked', id: $this->fabric->id)
            ->assertCount('cart', 1)
            ->assertSet('cart.0.quantity', '')
            ->set('cart.0.quantity', '2,5')
            ->assertSee('R$ 12,90/m')
            ->assertSee('R$ 32,25')
            ->call('addPayment', $this->pix->id)
            ->call('finalize');

        $sale = Sale::sole();
        $pos->assertRedirect(route('sales.show', $sale->id));
        $this->assertSame(2500, $sale->items()->sole()->quantity);
        $this->assertSame('32.25', $sale->total);
    }

    public function test_pos_requires_the_length_before_finishing(): void
    {
        $this->enter($this->fabric, 10000);

        Livewire::actingAs($this->operator)->test(Pos::class)
            ->set('cart', [])
            ->dispatch('variant-picked', id: $this->fabric->id)
            ->call('finalize')
            ->assertSee('Informe os metros de Tricoline Estampado');

        $this->assertSame(0, Sale::count());
    }

    public function test_pos_warns_when_the_length_exceeds_the_store_stock(): void
    {
        $this->enter($this->candy, 400);

        Livewire::actingAs($this->operator)->test(Pos::class)
            ->set('cart', [])
            ->dispatch('variant-picked', id: $this->candy->id)
            ->set('cart.0.quantity', '0,5')
            ->assertSee('Disponível nesta loja: 0,4 kg');
    }

    public function test_entry_and_movement_screens_accept_decimals(): void
    {
        Livewire::actingAs($this->operator)->test(Entry::class)
            ->dispatch('variant-picked', id: $this->fabric->id)
            ->assertSet('items.0.quantity', '')
            ->set('items.0.quantity', '50,5')
            ->set('items.0.unit_cost', '6')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(50500, $this->fabric->fresh()->stock_on_hand);

        Livewire::actingAs($this->operator)->test(Movement::class)
            ->dispatch('variant-picked', id: $this->fabric->id)
            ->assertSee('50,5 m')
            ->set('type', MovementType::Damage->value)
            ->set('quantity', '0,75')
            ->set('reason', 'Mancha no tecido')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(49750, $this->fabric->fresh()->stock_on_hand);
    }

    public function test_entry_screen_rejects_an_invalid_length(): void
    {
        Livewire::actingAs($this->operator)->test(Entry::class)
            ->dispatch('variant-picked', id: $this->fabric->id)
            ->set('items.0.quantity', 'dois')
            ->set('items.0.unit_cost', '6')
            ->call('save')
            ->assertHasErrors('items.0.quantity');

        $this->assertSame(0, $this->fabric->fresh()->stock_on_hand);
    }

    public function test_return_screen_accepts_a_fraction(): void
    {
        $this->enter($this->fabric, 10000);
        $sale = $this->sell($this->fabric, 3000, 3870);
        $item = $sale->items()->sole();

        Livewire::actingAs($this->operator)->test(ReturnForm::class, ['sale' => $sale->id])
            ->assertSee('3 m')
            ->set("quantities.{$item->id}", '0,5')
            ->set('reason', 'Corte errado')
            ->call('save')
            ->assertRedirect(route('sales.show', $sale->id));

        $this->assertSame(500, $item->fresh()->returned_quantity);
        $this->assertSame(7500, $this->fabric->fresh()->stock_on_hand);
    }

    public function test_inventory_counts_decimals_and_refuses_the_scanner(): void
    {
        $this->enter($this->fabric, 10000);
        $inventory = app(InventoryService::class)->open($this->operator, null, null);
        $item = $inventory->items()->where('product_variant_id', $this->fabric->id)->sole();

        try {
            app(InventoryService::class)->scan($inventory, '7890000000024');
            $this->fail('Ler o código de um produto por metro deveria ser recusado.');
        } catch (BusinessRuleException $exception) {
            $this->assertStringContainsString('meça ou pese', $exception->getMessage());
        }

        Livewire::actingAs($this->operator)->test(InventoryCount::class, ['inventory' => $inventory->id])
            ->call('saveCount', $item->id, '9,25')
            ->assertSee('-0,75 m');

        app(InventoryService::class)->finish($this->operator, $inventory);

        $this->assertSame(9250, $this->fabric->fresh()->stock_on_hand);
    }

    public function test_transfer_sends_the_typed_length(): void
    {
        config(['loja.plan.max_stores' => 2]);
        $admin = $this->admin();
        $branch = app(StoreService::class)->save(null, ['name' => 'Filial', 'code' => 'fil', 'phone' => null, 'address' => null, 'city' => null, 'is_active' => true], $admin);
        app(StoreService::class)->syncUsers($branch, [$admin->id], $admin);
        $this->enter($this->fabric, 10000);

        Livewire::actingAs($admin)->test(TransferForm::class)
            ->dispatch('variant-picked', id: $this->fabric->id)
            ->set('items.'.$this->fabric->id, '4,5')
            ->assertSee('10 m')
            ->set('toStoreId', (string) $branch->id)
            ->call('send')
            ->assertRedirect(route('stock.transfers', ['ver' => 'enviadas']));

        $main = Store::where('is_main', true)->sole();
        $this->assertSame(5500, $this->fabric->fresh()->stockIn($main->id)['on_hand']);
    }

    public function test_consignment_refuses_products_sold_by_weight_or_length(): void
    {
        $this->enter($this->fabric, 10000);
        $customer = Customer::create(['name' => 'Ana']);

        Livewire::actingAs($this->operator)->test(ConsignmentForm::class)
            ->dispatch('variant-picked', id: $this->fabric->id)
            ->assertSet('items', [])
            ->assertSee('não entra na condicional');

        $this->expectExceptionMessage('não entra na condicional');
        app(ConsignmentService::class)->open($this->operator, $customer->id, [['variant_id' => $this->fabric->id, 'quantity' => 1000]], today()->addDays(3), null);
    }

    public function test_product_form_saves_the_unit_and_decimal_initial_stock(): void
    {
        $admin = $this->admin();
        $category = Category::create(['name' => 'Aviamentos']);

        Livewire::actingAs($admin)->test(ProductForm::class)
            ->set('name', 'Fita de Cetim')
            ->set('categoryId', (string) $category->id)
            ->set('salePrice', '2.50')
            ->set('tracksSerial', true)
            ->set('unit', 'm')
            ->assertSet('tracksSerial', false)
            ->assertSee('Preço base (R$/m)')
            ->set('rows.0.min_stock', '5.5')
            ->set('rows.0.initial_stock', '100.25')
            ->call('save')
            ->assertHasNoErrors();

        $variant = Product::where('name', 'Fita de Cetim')->sole()->variants()->sole();
        $this->assertSame('m', $variant->product->unit);
        $this->assertSame(5500, $variant->min_stock);
        $this->assertSame(100250, $variant->stock_on_hand);
    }

    public function test_unit_cannot_change_after_the_product_has_movements(): void
    {
        $admin = $this->admin();
        $product = $this->fabric->product;
        $row = ['id' => $this->fabric->id, 'value_ids' => [], 'sku' => 'TRI-1', 'barcode' => '7890000000024', 'sale_price' => '12.90', 'cost_price' => '6.00', 'min_stock' => 0, 'is_active' => true];
        $data = fn (string $unit, bool $tracks = false) => ['name' => 'Tricoline Estampado', 'description' => null, 'category_id' => $product->category_id, 'brand_id' => null, 'supplier_id' => null,
            'internal_code' => null, 'sku' => null, 'cost_price' => '6.00', 'sale_price' => '12.90', 'is_active' => true, 'tracks_serial' => $tracks, 'warranty_months' => null, 'unit' => $unit];

        try {
            app(ProductService::class)->save($admin, $product, $data('m', true), [$row]);
            $this->fail('Número de série em produto por metro deveria ser recusado.');
        } catch (BusinessRuleException $exception) {
            $this->assertStringContainsString('não tem controle de número de série', $exception->getMessage());
        }

        // Sem movimentação, ainda pode mudar.
        app(ProductService::class)->save($admin, $product, $data('kg'), [$row]);
        $this->assertSame('kg', $product->fresh()->unit);

        app(StockService::class)->register($this->fabric, MovementType::AdjustmentIn, 1000, 'Ajuste', $this->operator);

        $this->expectExceptionMessage('só pode mudar enquanto o produto não tem movimentação');
        app(ProductService::class)->save($admin, $product->fresh(), $data('un'), [$row]);
    }

    public function test_reports_value_fractions_by_the_real_quantity(): void
    {
        $this->enter($this->fabric, 10000);
        $this->sell($this->fabric, 2000, 2580);

        $reports = new FinanceReports;
        $result = $reports->result(today(), today());
        $summary = $reports->salesSummary(today(), today());
        $top = $reports->topProducts(today(), today())->sole();
        $stock = $reports->stockValue();

        // CMV: 2 m × R$ 6,00 = R$ 12,00 (e não 2000 × R$ 6,00).
        $this->assertSame(1200, $result['cost']);
        // A venda conta como uma peça.
        $this->assertSame(1, $summary['items']);
        $this->assertSame(1, $top->quantity);
        $this->assertSame('2 m', Quantity::format($top->amount, $top->unit));
        // 8 m em estoque: custo R$ 48,00, venda R$ 103,20.
        $this->assertSame(['quantity' => 1, 'cost' => 4800, 'sale' => 10320], $stock);
    }

    public function test_sale_page_and_receipt_show_the_unit_price(): void
    {
        $this->enter($this->candy, 5000);
        $sale = $this->sell($this->candy, 1250, 4988);

        $this->actingAs($this->operator)->get(route('sales.show', $sale->id))
            ->assertOk()
            ->assertSee('1,25 kg')
            ->assertSee('R$ 39,90/kg');
    }
}
