<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Exceptions\BusinessRuleException;
use App\Livewire\Products\Form as ProductForm;
use App\Livewire\Sales\Pos;
use App\Livewire\Sales\ReturnForm;
use App\Livewire\Sales\Warranty;
use App\Livewire\Stock\Entry;
use App\Livewire\Stock\Movement;
use App\Livewire\Stock\VariantPicker;
use App\Models\Category;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SerialNumber;
use App\Models\SerialNumberEvent;
use App\Models\Store;
use App\Models\User;
use App\Services\CashService;
use App\Services\InventoryService;
use App\Services\ProductService;
use App\Services\SaleReturnService;
use App\Services\SaleService;
use App\Services\SerialService;
use App\Services\StockEntryService;
use App\Services\StockService;
use App\Services\StockTransferService;
use App\Services\StoreService;
use App\Support\CurrentStore;
use Database\Seeders\PaymentMethodsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

class SerialNumbersTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private User $operator;

    private PaymentMethod $pix;

    private ProductVariant $watch;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-03-10 10:00:00');

        $this->seed(PaymentMethodsSeeder::class);
        $this->pix = PaymentMethod::where('type', 'pix')->sole();
        $this->operator = $this->userWith(['pdv.vender', 'caixa.operar', 'vendas.devolver', 'vendas.cancelar', 'vendas.garantia', 'estoque.entrada', 'estoque.movimentar', 'estoque.ajustar', 'estoque.ver', 'estoque.inventario']);
        app(CashService::class)->open($this->operator, 0);

        $category = Category::create(['name' => 'Relógios']);
        $product = Product::create(['name' => 'Relógio Kids', 'category_id' => $category->id, 'sale_price' => '200.00', 'tracks_serial' => true, 'warranty_months' => 3]);
        $this->watch = ProductVariant::create(['product_id' => $product->id, 'sku' => 'REL-1', 'barcode' => '7890000000017', 'sale_price' => '200.00', 'cost_price' => '90.00']);
    }

    protected function tearDown(): void
    {
        CurrentStore::use(null);
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function enter(array $serials): void
    {
        app(StockEntryService::class)->register($this->operator, null, 'NF 1', null, [
            ['variant_id' => $this->watch->id, 'quantity' => count($serials), 'unit_cost' => '90.00', 'serials' => $serials],
        ]);
    }

    private function sell(array $serials, ?int $quantity = null): Sale
    {
        $quantity ??= count($serials);

        return app(SaleService::class)->finalize($this->operator,
            [['variant_id' => $this->watch->id, 'quantity' => $quantity, 'discount' => 0, 'serials' => $serials]],
            [['method_id' => $this->pix->id, 'amount' => 20000 * $quantity]], null, 0, null, (string) Str::uuid());
    }

    private function unit(string $serial): SerialNumber
    {
        return SerialNumber::where('serial', $serial)->sole();
    }

    public function test_serials_are_normalized_and_validated(): void
    {
        $this->assertSame(['AB-1', 'CD/2', 'EF.3'], SerialService::parse(" ab-1\ncd/2, ef.3 ;"));
        $this->assertSame(2, SerialService::count('A1 A1'));

        $this->expectException(BusinessRuleException::class);
        SerialService::parse('A1 a1');
    }

    public function test_invalid_serial_characters_are_rejected(): void
    {
        $this->expectException(BusinessRuleException::class);
        SerialService::parse('ÁRVORE<script>');
    }

    public function test_entry_registers_each_unit_and_keeps_stock_in_step(): void
    {
        $this->enter(['SN1', 'sn2']);

        $this->assertSame(2, $this->watch->fresh()->stock_on_hand);
        $this->assertSame(SerialNumber::IN_STOCK, $this->unit('SN2')->status);
        $this->assertSame(CurrentStore::mainId(), $this->unit('SN1')->store_id);
        $this->assertSame(2, SerialNumberEvent::where('type', MovementType::Entry->value)->count());
    }

    public function test_entry_needs_one_serial_per_unit_and_no_duplicates(): void
    {
        try {
            app(StockEntryService::class)->register($this->operator, null, null, null, [['variant_id' => $this->watch->id, 'quantity' => 3, 'unit_cost' => null, 'serials' => ['A1', 'A2']]]);
            $this->fail('Quantidade diferente dos números deveria ser recusada.');
        } catch (BusinessRuleException $exception) {
            $this->assertStringContainsString('Informe 3 número(s) de série', $exception->getMessage());
        }

        $this->enter(['A1']);

        $this->expectExceptionMessage('já está no estoque');
        $this->enter(['A1']);
    }

    public function test_failed_entry_rolls_back_units_and_stock(): void
    {
        $this->enter(['A1']);

        try {
            $this->enter(['B1', 'A1']);
        } catch (BusinessRuleException) {
        }

        $this->assertSame(1, $this->watch->fresh()->stock_on_hand);
        $this->assertFalse(SerialNumber::where('serial', 'B1')->exists());
    }

    public function test_sale_records_the_units_and_the_warranty(): void
    {
        $this->enter(['SN1', 'SN2', 'SN3']);

        $sale = $this->sell(['sn2']);
        $item = $sale->items()->sole();

        $this->assertSame(['SN2'], $item->serials);
        $this->assertSame('2026-06-10', $item->warranty_until->toDateString());
        $this->assertTrue($item->underWarranty());
        $this->assertSame(SerialNumber::SOLD, $this->unit('SN2')->status);
        $this->assertSame($item->id, $this->unit('SN2')->sale_item_id);
        $this->assertSame(SerialNumber::IN_STOCK, $this->unit('SN1')->status);
        $this->assertSame(2, $this->watch->fresh()->stock_on_hand);

        $this->actingAs($this->operator)->get(route('sales.receipt', $sale->id))
            ->assertOk()->assertSee('Nº de série: SN2')->assertSee('Garantia até 10/06/2026');
    }

    public function test_warranty_without_serial_control_is_also_printed(): void
    {
        $category = Category::create(['name' => 'Bolsas']);
        $product = Product::create(['name' => 'Bolsa', 'category_id' => $category->id, 'sale_price' => '50.00', 'warranty_months' => 1]);
        $bag = new ProductVariant(['product_id' => $product->id, 'sku' => 'BOL', 'sale_price' => '50.00', 'cost_price' => '20.00']);
        $bag->stock_on_hand = 5;
        $bag->save();

        $sale = app(SaleService::class)->finalize($this->operator, [['variant_id' => $bag->id, 'quantity' => 2, 'discount' => 0]],
            [['method_id' => $this->pix->id, 'amount' => 10000]], null, 0, null, (string) Str::uuid());

        $item = $sale->items()->sole();
        $this->assertNull($item->serials);
        $this->assertSame('2026-04-10', $item->warranty_until->toDateString());
    }

    public function test_sale_rejects_missing_or_unavailable_serials_and_rolls_back(): void
    {
        $this->enter(['SN1']);

        foreach ([[[], 1], [['SN9'], 1], [['SN1', 'SN1'], 2]] as [$serials, $quantity]) {
            try {
                $this->sell($serials, $quantity);
                $this->fail('A venda deveria ser recusada.');
            } catch (BusinessRuleException) {
            }
        }

        $this->assertSame(0, Sale::count());
        $this->assertSame(SerialNumber::IN_STOCK, $this->unit('SN1')->status);
        $this->assertSame(1, $this->watch->fresh()->stock_on_hand);
    }

    public function test_serialized_product_never_goes_negative(): void
    {
        $this->operator->givePermissionTo('estoque.negativo');

        $this->expectException(BusinessRuleException::class);
        app(StockService::class)->register($this->watch, MovementType::Loss, 1, 'Perda', $this->operator, allowNegative: true, serials: ['X1']);
    }

    public function test_cancelling_the_sale_puts_the_unit_back(): void
    {
        $this->enter(['SN1']);
        $sale = $this->sell(['SN1']);

        app(SaleService::class)->cancel($this->operator, $sale, 'Erro');

        $this->assertSame(SerialNumber::IN_STOCK, $this->unit('SN1')->status);
        $this->assertSame(1, $this->watch->fresh()->stock_on_hand);

        // A mesma unidade pode ser vendida de novo.
        $this->assertSame(['SN1'], $this->sell(['SN1'])->items()->sole()->serials);
    }

    public function test_return_with_restock_brings_the_unit_back_and_defective_one_leaves(): void
    {
        $this->enter(['SN1', 'SN2']);
        $sale = $this->sell(['SN1', 'SN2']);
        $item = $sale->items()->sole();
        $returns = app(SaleReturnService::class);

        try {
            $returns->register($this->operator, $sale, [['sale_item_id' => $item->id, 'quantity' => 1, 'restock' => true, 'serials' => ['SN7']]], 'Troca', null);
            $this->fail('Número que não é desta venda deveria ser recusado.');
        } catch (BusinessRuleException $exception) {
            $this->assertStringContainsString('Marque os números de série', $exception->getMessage());
        }

        $returns->register($this->operator, $sale, [['sale_item_id' => $item->id, 'quantity' => 1, 'restock' => true, 'serials' => ['SN1']]], 'Troca', null);
        $this->assertSame(SerialNumber::IN_STOCK, $this->unit('SN1')->status);
        $this->assertSame(1, $this->watch->fresh()->stock_on_hand);

        $returns->register($this->operator, $sale, [['sale_item_id' => $item->id, 'quantity' => 1, 'restock' => false, 'serials' => ['SN2']]], 'Defeito', null);
        $this->assertSame(SerialNumber::REMOVED, $this->unit('SN2')->status);
        $this->assertSame(1, $this->watch->fresh()->stock_on_hand);
        $this->assertTrue(SerialNumberEvent::where('type', 'return_retired')->exists());

        // A mesma unidade não pode ser devolvida duas vezes.
        $this->expectException(BusinessRuleException::class);
        $returns->register($this->operator, $sale->fresh(), [['sale_item_id' => $item->id, 'quantity' => 1, 'restock' => true, 'serials' => ['SN1']]], 'Troca', null);
    }

    public function test_manual_movements_need_units_of_this_store(): void
    {
        $this->enter(['SN1']);
        $stock = app(StockService::class);

        $stock->register($this->watch, MovementType::Damage, 1, 'Tela quebrada', $this->operator, serials: ['SN1']);
        $this->assertSame(SerialNumber::REMOVED, $this->unit('SN1')->status);
        $this->assertSame(0, $this->watch->fresh()->stock_on_hand);

        // Unidade consertada volta pelo ajuste.
        $stock->register($this->watch, MovementType::AdjustmentIn, 1, 'Voltou do conserto', $this->operator, serials: ['SN1']);
        $this->assertSame(SerialNumber::IN_STOCK, $this->unit('SN1')->status);
        $this->assertSame(1, $this->watch->fresh()->stock_on_hand);
    }

    public function test_sold_unit_cannot_come_back_by_entry(): void
    {
        $this->enter(['SN1']);
        $sale = $this->sell(['SN1']);

        $this->expectExceptionMessage("vendido na venda #{$sale->number()}");
        $this->enter(['SN1']);
    }

    public function test_transfer_carries_the_units_to_the_other_store(): void
    {
        config(['loja.plan.max_stores' => 2]);
        $admin = $this->userWith(['lojas.gerir', 'lojas.ver_todas', 'estoque.transferir']);
        $branch = app(StoreService::class)->save(null, ['name' => 'Filial', 'code' => 'fil', 'phone' => null, 'address' => null, 'city' => null, 'is_active' => true], $admin);
        app(StoreService::class)->syncUsers($branch, [$admin->id], $admin);
        $main = Store::where('is_main', true)->sole();
        $this->enter(['SN1', 'SN2']);
        $transfers = app(StockTransferService::class);

        $transfer = $transfers->send($admin, $main->id, $branch->id, [['variant_id' => $this->watch->id, 'quantity' => 1, 'serials' => ['sn1']]], null);
        $this->assertSame(SerialNumber::IN_TRANSIT, $this->unit('SN1')->status);
        $this->assertSame(['SN1'], $transfer->items()->sole()->serials);

        $transfers->receive($admin, $transfer);
        $this->assertSame(SerialNumber::IN_STOCK, $this->unit('SN1')->status);
        $this->assertSame($branch->id, $this->unit('SN1')->store_id);
        $this->assertSame(1, $this->watch->stockIn($branch->id)['on_hand']);

        // Vender na loja principal uma unidade que está na filial não é possível.
        try {
            $this->sell(['SN1']);
            $this->fail('A unidade está em outra loja.');
        } catch (BusinessRuleException) {
        }

        $back = $transfers->send($admin, $main->id, $branch->id, [['variant_id' => $this->watch->id, 'quantity' => 1, 'serials' => ['SN2']]], null);
        $transfers->cancel($admin, $back);
        $this->assertSame(SerialNumber::IN_STOCK, $this->unit('SN2')->status);
        $this->assertSame($main->id, $this->unit('SN2')->store_id);
    }

    public function test_inventory_leaves_serialized_products_out(): void
    {
        $this->enter(['SN1']);
        $category = Category::create(['name' => 'Camisetas']);
        $shirt = Product::create(['name' => 'Camiseta', 'category_id' => $category->id, 'sale_price' => '30.00']);
        $shirtVariant = ProductVariant::create(['product_id' => $shirt->id, 'sku' => 'CAM', 'sale_price' => '30.00', 'cost_price' => '10.00']);

        $inventory = app(InventoryService::class)->open($this->operator, null, null);

        $this->assertSame([$shirtVariant->id], $inventory->items()->pluck('product_variant_id')->all());

        $this->expectException(BusinessRuleException::class);
        app(StockService::class)->setCounted($this->watch, 0, 'Contagem', $this->operator);
    }

    public function test_serial_control_switches_only_with_safe_stock(): void
    {
        $admin = $this->admin();
        $products = app(ProductService::class);
        $product = $this->watch->product;
        $row = ['id' => $this->watch->id, 'value_ids' => [], 'sku' => 'REL-1', 'barcode' => '7890000000017', 'sale_price' => '200.00', 'cost_price' => '90.00', 'min_stock' => 0, 'is_active' => true];
        $data = fn (bool $tracks) => ['name' => 'Relógio Kids', 'description' => null, 'category_id' => $product->category_id, 'brand_id' => null, 'supplier_id' => null,
            'internal_code' => null, 'sku' => null, 'cost_price' => '90.00', 'sale_price' => '200.00', 'is_active' => true, 'tracks_serial' => $tracks, 'warranty_months' => 3];

        $this->enter(['SN1']);

        try {
            $products->save($admin, $product, $data(false), [$row]);
            $this->fail('Desligar com unidades em estoque deveria ser recusado.');
        } catch (BusinessRuleException $exception) {
            $this->assertStringContainsString('Dê saída', $exception->getMessage());
        }

        app(StockService::class)->register($this->watch, MovementType::Exit, 1, 'Uso interno', $this->operator, serials: ['SN1']);
        $products->save($admin, $product, $data(false), [$row]);
        $this->assertFalse($product->fresh()->tracks_serial);

        app(StockService::class)->register($this->watch->fresh(), MovementType::AdjustmentIn, 2, 'Ajuste', $this->operator);

        $this->expectExceptionMessage('estoque do produto precisa estar zerado');
        $products->save($admin, $product->fresh(), $data(true), [$row]);
    }

    public function test_product_form_saves_warranty_and_serial_control_without_initial_stock(): void
    {
        $admin = $this->admin();
        $category = Category::create(['name' => 'Eletrônicos']);

        Livewire::actingAs($admin)->test(ProductForm::class)
            ->set('name', 'Fone Bluetooth')
            ->set('categoryId', (string) $category->id)
            ->set('salePrice', '99.90')
            ->set('rows.0.initial_stock', '5')
            ->set('tracksSerial', true)
            ->assertSet('rows.0.initial_stock', '')
            ->set('warrantyMonths', '6')
            ->call('save')
            ->assertHasNoErrors();

        $product = Product::where('name', 'Fone Bluetooth')->sole();
        $this->assertTrue($product->tracks_serial);
        $this->assertSame(6, $product->warranty_months);
        $this->assertSame(0, $product->variants()->sole()->stock_on_hand);
    }

    public function test_entry_screen_counts_the_typed_serials(): void
    {
        Livewire::actingAs($this->operator)->test(Entry::class)
            ->dispatch('variant-picked', id: $this->watch->id)
            ->set('items.0.serials', "a1\na2\na3")
            ->assertSet('items.0.quantity', '3')
            ->set('items.0.unit_cost', '90')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('stock.history'));

        $this->assertSame(3, $this->watch->fresh()->stock_on_hand);
        $this->assertSame(3, SerialNumber::where('status', SerialNumber::IN_STOCK)->count());
    }

    public function test_movement_screen_takes_serials_instead_of_quantity(): void
    {
        $this->enter(['SN1', 'SN2']);

        Livewire::actingAs($this->operator)->test(Movement::class)
            ->dispatch('serial-picked', id: $this->watch->id, serial: 'SN2')
            ->assertSet('serials', 'SN2')
            ->assertSee('SN1, SN2')
            ->set('type', MovementType::Loss->value)
            ->set('reason', 'Extraviado')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(SerialNumber::REMOVED, $this->unit('SN2')->status);
        $this->assertSame(1, $this->watch->fresh()->stock_on_hand);
    }

    public function test_barcode_reader_on_a_serial_adds_the_exact_unit_to_the_pos(): void
    {
        $this->enter(['SN1', 'SN2']);

        Livewire::actingAs($this->operator)->test(VariantPicker::class, ['serials' => true])
            ->set('term', 'sn2')
            ->call('pickExact')
            ->assertDispatched('serial-picked', id: $this->watch->id, serial: 'SN2');

        // Sem a opção, o número de série não é procurado.
        Livewire::actingAs($this->operator)->test(VariantPicker::class)
            ->set('term', 'SN2')
            ->call('pickExact')
            ->assertNotDispatched('serial-picked');

        $pos = Livewire::actingAs($this->operator)->test(Pos::class)
            ->set('cart', [])
            ->dispatch('serial-picked', id: $this->watch->id, serial: 'SN2')
            ->dispatch('serial-picked', id: $this->watch->id, serial: 'SN1')
            ->assertSet('cart.0.quantity', '2')
            ->assertSet('cart.0.serials', "SN2\nSN1")
            ->assertSee('Garantia de 3 meses')
            ->call('addPayment', $this->pix->id)
            ->call('finalize');

        $sale = Sale::sole();
        $pos->assertRedirect(route('sales.show', $sale->id));
        $this->assertSame(['SN2', 'SN1'], $sale->items()->sole()->serials);
    }

    public function test_pos_asks_for_the_serial_before_finishing(): void
    {
        $this->enter(['SN1']);

        Livewire::actingAs($this->operator)->test(Pos::class)
            ->set('cart', [])
            ->dispatch('variant-picked', id: $this->watch->id)
            ->dispatch('variant-picked', id: $this->watch->id)
            ->assertSet('cart.0.quantity', '0')
            ->call('addPayment', $this->pix->id)
            ->call('finalize')
            ->assertSee('Informe o número de série de Relógio Kids');

        $this->assertSame(0, Sale::count());
    }

    public function test_return_screen_lists_the_units_still_with_the_customer(): void
    {
        $this->enter(['SN1', 'SN2']);
        $sale = $this->sell(['SN1', 'SN2']);
        $item = $sale->items()->sole();

        Livewire::actingAs($this->operator)->test(ReturnForm::class, ['sale' => $sale->id])
            ->assertSee('SN1')->assertSee('Garantia até 10/06/2026')
            ->set("returnSerials.{$item->id}", ['SN2'])
            ->assertSet("quantities.{$item->id}", '1')
            ->set('reason', 'Não gostou')
            ->call('save')
            ->assertRedirect(route('sales.show', $sale->id));

        $this->assertSame(SerialNumber::IN_STOCK, $this->unit('SN2')->status);
        $this->assertSame(SerialNumber::SOLD, $this->unit('SN1')->status);

        Livewire::actingAs($this->operator)->test(ReturnForm::class, ['sale' => $sale->id])
            ->assertSee('SN1')->assertDontSee('SN2');
    }

    public function test_warranty_page_finds_the_unit_and_the_sale(): void
    {
        Carbon::setTestNow('2026-01-10 10:00:00');
        $this->enter(['TTK001']);
        $sale = $this->sell(['TTK001']);
        Carbon::setTestNow('2026-05-01 10:00:00');

        Livewire::actingAs($this->operator)->test(Warranty::class)
            ->set('search', 'ttk0')
            ->assertSee('TTK001')
            ->assertSee('Vendido')
            ->assertSee("#{$sale->number()}")
            ->assertSee('Garantia vencida em 10/04/2026')
            ->set('search', $sale->number())
            ->assertSee('Venda #'.$sale->number())
            ->set('search', 'NADA-AQUI')
            ->assertSee('Nada encontrado');

        $this->actingAs($this->operator)->get(route('warranty.index'))->assertOk()->assertSee('Garantias');
        $this->actingAs($this->userWith(['pdv.vender']))->get(route('warranty.index'))->assertForbidden();
    }

    public function test_serial_history_is_immutable(): void
    {
        $this->enter(['SN1']);

        $this->expectException(LogicException::class);
        SerialNumberEvent::first()->update(['description' => 'Alterado']);
    }

    public function test_default_profiles_can_check_warranties(): void
    {
        foreach (['Gerente', 'Vendedor', 'Estoquista'] as $role) {
            $this->assertContains('vendas.garantia', config("loja.default_roles.{$role}"));
        }
    }
}
