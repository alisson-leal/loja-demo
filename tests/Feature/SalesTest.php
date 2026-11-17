<?php

namespace Tests\Feature;

use App\Enums\CashMovementType;
use App\Exceptions\BusinessRuleException;
use App\Livewire\Cash\Current;
use App\Livewire\Dashboard;
use App\Livewire\Sales\Pos;
use App\Livewire\Sales\Show;
use App\Mail\SaleReceipt;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Category;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\StoreCredit;
use App\Models\User;
use App\Services\CashService;
use App\Services\SaleReceiptService;
use App\Services\SaleReturnService;
use App\Services\SaleService;
use App\Support\Money;
use App\Support\SaleCalculator;
use Database\Seeders\PaymentMethodsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use LogicException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SalesTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private User $operator;

    private PaymentMethod $cash;

    private PaymentMethod $pix;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PaymentMethodsSeeder::class);
        $this->cash = PaymentMethod::where('type', 'cash')->sole();
        $this->pix = PaymentMethod::where('type', 'pix')->sole();
        $this->operator = $this->userWith(['pdv.vender', 'caixa.operar', 'pdv.desconto']);
        app(CashService::class)->open($this->operator, 5000);
    }

    private function variant(int $stock = 10, string $price = '100.00'): ProductVariant
    {
        static $sequence = 0;
        $sequence++;

        $category = Category::create(['name' => "Cat {$sequence}"]);
        $product = Product::create(['name' => "Produto {$sequence}", 'category_id' => $category->id, 'sale_price' => $price]);

        $variant = new ProductVariant(['product_id' => $product->id, 'sku' => "S{$sequence}", 'sale_price' => $price, 'cost_price' => '40.00']);
        $variant->stock_on_hand = $stock;
        $variant->save();

        return $variant;
    }

    private function sell(ProductVariant $variant, int $quantity, array $payments, int $orderDiscount = 0, int $lineDiscount = 0, ?User $actor = null, ?string $token = null): Sale
    {
        return app(SaleService::class)->finalize(
            $actor ?? $this->operator,
            [['variant_id' => $variant->id, 'quantity' => $quantity, 'discount' => $lineDiscount]],
            $payments,
            null,
            $orderDiscount,
            null,
            $token ?? (string) Str::uuid(),
        );
    }

    private function expectedCash(): int
    {
        return app(CashService::class)->expectedCents(CashSession::where('user_id', $this->operator->id)->sole());
    }

    public function test_sale_updates_stock_and_records_movement_with_price_from_database(): void
    {
        $variant = $this->variant(10, '100.00');

        $sale = $this->sell($variant, 2, [['method_id' => $this->pix->id, 'amount' => 20000]]);

        $this->assertSame('200.00', $sale->total);
        $this->assertSame(8, $variant->fresh()->stock_on_hand);
        $this->assertDatabaseHas('stock_movements', ['product_variant_id' => $variant->id, 'type' => 'sale', 'quantity' => -2]);
        $this->assertDatabaseHas('sale_items', ['sale_id' => $sale->id, 'unit_price' => '100.00', 'unit_cost' => '40.00', 'quantity' => 2]);
    }

    public function test_insufficient_stock_rolls_back_everything(): void
    {
        $variant = $this->variant(1);

        try {
            $this->sell($variant, 2, [['method_id' => $this->cash->id, 'amount' => 20000]]);
            $this->fail('Deveria recusar.');
        } catch (BusinessRuleException) {
            $this->assertSame(0, Sale::count());
            $this->assertSame(1, $variant->fresh()->stock_on_hand);
            $this->assertSame(5000, $this->expectedCash());
        }
    }

    public function test_split_payment_with_change_puts_only_net_cash_in_drawer(): void
    {
        $sale = $this->sell($this->variant(), 1, [
            ['method_id' => $this->pix->id, 'amount' => 6000],
            ['method_id' => $this->cash->id, 'amount' => 5000],
        ]);

        $this->assertSame('10.00', $sale->change_amount);
        $this->assertSame(5000 + 4000, $this->expectedCash());
        $this->assertDatabaseHas('sale_payments', ['sale_id' => $sale->id, 'payment_method_id' => $this->cash->id, 'amount' => '40.00', 'received_amount' => '50.00']);
    }

    public function test_underpayment_and_non_cash_overpayment_are_refused(): void
    {
        $variant = $this->variant();

        foreach ([9000, 11000] as $amount) {
            try {
                $this->sell($variant, 1, [['method_id' => $this->pix->id, 'amount' => $amount]]);
                $this->fail('Deveria recusar.');
            } catch (BusinessRuleException) {
                $this->assertSame(0, Sale::count());
            }
        }
    }

    public function test_sale_requires_open_cash_session(): void
    {
        $other = $this->userWith(['pdv.vender']);

        $this->expectException(BusinessRuleException::class);
        $this->sell($this->variant(), 1, [['method_id' => $this->pix->id, 'amount' => 10000]], actor: $other);
    }

    public function test_same_token_does_not_create_a_second_sale(): void
    {
        $variant = $this->variant();
        $token = (string) Str::uuid();

        $first = $this->sell($variant, 1, [['method_id' => $this->pix->id, 'amount' => 10000]], token: $token);
        $second = $this->sell($variant, 1, [['method_id' => $this->pix->id, 'amount' => 10000]], token: $token);

        $this->assertTrue($first->is($second));
        $this->assertSame(9, $variant->fresh()->stock_on_hand);
    }

    public function test_discount_needs_permission_and_respects_limit(): void
    {
        config(['loja.max_discount_percent' => 10]);
        $variant = $this->variant();

        $this->sell($variant, 1, [['method_id' => $this->pix->id, 'amount' => 9000]], orderDiscount: 1000);
        $this->assertSame(1, Sale::count());

        try {
            $this->sell($variant, 1, [['method_id' => $this->pix->id, 'amount' => 8900]], orderDiscount: 1100);
            $this->fail('Acima do limite deveria recusar.');
        } catch (BusinessRuleException) {
            $this->assertSame(1, Sale::count());
        }

        $free = $this->userWith(['pdv.desconto', 'pdv.desconto_livre']);
        app(CashService::class)->open($free, 0);
        $this->sell($variant, 1, [['method_id' => $this->pix->id, 'amount' => 5000]], orderDiscount: 5000, actor: $free);
        $this->assertSame(2, Sale::count());

        $noDiscount = $this->userWith(['pdv.vender']);
        app(CashService::class)->open($noDiscount, 0);
        $this->expectException(BusinessRuleException::class);
        $this->sell($variant, 1, [['method_id' => $this->pix->id, 'amount' => 9900]], orderDiscount: 100, actor: $noDiscount);
    }

    public function test_calculator_distributes_order_discount_without_losing_cents(): void
    {
        $result = SaleCalculator::calculate([
            ['unit_price' => 3333, 'quantity' => 1, 'discount' => 0],
            ['unit_price' => 3333, 'quantity' => 1, 'discount' => 0],
            ['unit_price' => 3334, 'quantity' => 1, 'discount' => 0],
        ], 1000);

        $this->assertSame(9000, $result['total']);
        $this->assertSame(9000, array_sum(array_column($result['lines'], 'total')));
    }

    public function test_cancel_restores_stock_and_cash_and_requires_reason_and_permission(): void
    {
        $variant = $this->variant();
        $sale = $this->sell($variant, 2, [['method_id' => $this->cash->id, 'amount' => 20000]]);
        $this->assertSame(25000, $this->expectedCash());

        Livewire::actingAs($this->operator)->test(Show::class, ['sale' => $sale->id])->set('cancelReason', 'Sem autorização')->call('cancel')->assertHasErrors('supervisorEmail');
        $this->assertSame(Sale::COMPLETED, $sale->fresh()->status);

        $this->operator->givePermissionTo('vendas.cancelar');

        Livewire::actingAs($this->operator)->test(Show::class, ['sale' => $sale->id])->call('cancel')->assertHasErrors('cancelReason');

        Livewire::actingAs($this->operator)->test(Show::class, ['sale' => $sale->id])
            ->set('cancelReason', 'Cliente desistiu')
            ->call('cancel')
            ->assertHasNoErrors();

        $sale->refresh();
        $this->assertSame(Sale::CANCELLED, $sale->status);
        $this->assertSame(10, $variant->fresh()->stock_on_hand);
        $this->assertSame(5000, $this->expectedCash());
        $this->assertDatabaseHas('activity_logs', ['event' => 'sale.cancelled', 'subject_id' => $sale->id]);

        $this->expectException(BusinessRuleException::class);
        app(SaleService::class)->cancel($this->operator, $sale, 'Repetido');
    }

    public function test_partial_return_with_credit_and_use_in_new_sale(): void
    {
        $variant = $this->variant();
        $sale = $this->sell($variant, 2, [['method_id' => $this->pix->id, 'amount' => 20000]]);
        $item = $sale->items()->sole();

        $return = app(SaleReturnService::class)->register($this->operator, $sale, [
            ['sale_item_id' => $item->id, 'quantity' => 1, 'restock' => true],
        ], 'Tamanho errado', null);

        $this->assertSame('100.00', $return->total);
        $this->assertSame(9, $variant->fresh()->stock_on_hand);
        $credit = StoreCredit::sole();
        $this->assertSame('100.00', $credit->balance);

        $method = PaymentMethod::where('type', PaymentMethod::STORE_CREDIT)->sole();
        $this->sell($this->variant(), 1, [
            ['method_id' => $method->id, 'amount' => 6000, 'credit_code' => $credit->code],
            ['method_id' => $this->pix->id, 'amount' => 4000],
        ]);

        $this->assertSame('40.00', $credit->fresh()->balance);

        $this->expectException(BusinessRuleException::class);
        $this->sell($this->variant(), 1, [['method_id' => $method->id, 'amount' => 5000, 'credit_code' => $credit->code]], orderDiscount: 0);
    }

    public function test_return_cannot_exceed_sold_quantity_and_blocks_cancellation(): void
    {
        $sale = $this->sell($this->variant(), 2, [['method_id' => $this->pix->id, 'amount' => 20000]]);
        $item = $sale->items()->sole();
        $service = app(SaleReturnService::class);

        $service->register($this->operator, $sale, [['sale_item_id' => $item->id, 'quantity' => 2, 'restock' => false]], 'Defeito', $this->pix->id);

        try {
            $service->register($this->operator, $sale, [['sale_item_id' => $item->id, 'quantity' => 1, 'restock' => true]], 'De novo', $this->pix->id);
            $this->fail('Deveria recusar.');
        } catch (BusinessRuleException) {
            $this->assertSame(2, $item->fresh()->returned_quantity);
        }

        $this->expectException(BusinessRuleException::class);
        app(SaleService::class)->cancel($this->operator, $sale, 'Tentativa');
    }

    public function test_defective_return_does_not_go_back_to_stock_and_cash_refund_leaves_drawer(): void
    {
        $variant = $this->variant();
        $sale = $this->sell($variant, 1, [['method_id' => $this->cash->id, 'amount' => 10000]]);

        app(SaleReturnService::class)->register($this->operator, $sale, [
            ['sale_item_id' => $sale->items()->sole()->id, 'quantity' => 1, 'restock' => false],
        ], 'Defeito', $this->cash->id);

        $this->assertSame(9, $variant->fresh()->stock_on_hand);
        $this->assertSame(5000, $this->expectedCash());
    }

    public function test_partial_returns_add_up_to_exactly_the_amount_paid(): void
    {
        $variant = $this->variant(10, '33.33');
        $sale = $this->sell($variant, 3, [['method_id' => $this->pix->id, 'amount' => 9999 - 99]], orderDiscount: 99);
        $item = $sale->items()->sole();
        $service = app(SaleReturnService::class);
        $total = 0;

        foreach (range(1, 3) as $ignored) {
            $total += Money::toCents($service->register($this->operator, $sale, [['sale_item_id' => $item->id, 'quantity' => 1, 'restock' => true]], 'Parcial', $this->pix->id)->total);
        }

        $this->assertSame(Money::toCents($sale->total), $total);
    }

    public function test_only_one_open_session_per_operator_and_withdrawal_limited_to_balance(): void
    {
        $cash = app(CashService::class);

        try {
            $cash->open($this->operator, 0);
            $this->fail('Deveria recusar.');
        } catch (BusinessRuleException) {
            $this->assertSame(1, CashSession::where('user_id', $this->operator->id)->count());
        }

        $cash->registerManual($this->operator, CashMovementType::Bleed, 2000, 'Depósito');
        $this->assertSame(3000, $this->expectedCash());

        $this->expectException(BusinessRuleException::class);
        $cash->registerManual($this->operator, CashMovementType::Withdrawal, 3001, 'Excesso');
    }

    public function test_closing_records_difference_and_requires_note_when_there_is_one(): void
    {
        $session = CashSession::where('user_id', $this->operator->id)->sole();

        Livewire::actingAs($this->operator)->test(Current::class)
            ->set('countedAmount', '45.00')
            ->call('close')
            ->assertHasNoErrors();
        $this->assertTrue($session->fresh()->isOpen());

        Livewire::actingAs($this->operator)->test(Current::class)
            ->set('countedAmount', '45.00')
            ->set('closingNotes', 'Troco errado')
            ->call('close')
            ->assertRedirect(route('cash.report', $session->id));

        $session->refresh();
        $this->assertFalse($session->isOpen());
        $this->assertSame('50.00', $session->expected_amount);
        $this->assertSame('-5.00', $session->difference);
    }

    public function test_cash_movements_are_immutable(): void
    {
        $this->expectException(LogicException::class);
        CashMovement::firstOrFail()->delete();
    }

    public function test_operator_sees_only_own_sales(): void
    {
        $sale = $this->sell($this->variant(), 1, [['method_id' => $this->pix->id, 'amount' => 10000]]);
        $other = $this->userWith(['pdv.vender']);

        $this->actingAs($this->operator)->get(route('sales.show', $sale))->assertOk();
        $this->actingAs($other)->get(route('sales.show', $sale))->assertForbidden();
        $this->actingAs($other)->get(route('sales.receipt', $sale))->assertForbidden();
        $this->actingAs($this->userWith(['vendas.ver']))->get(route('sales.receipt', $sale))->assertOk()->assertSee('não fiscal');
    }

    public function test_pos_screen_sells_using_barcode_pick_cash_and_change(): void
    {
        $variant = $this->variant(5, '30.00');

        $component = Livewire::actingAs($this->operator)->test(Pos::class)
            ->dispatch('variant-picked', id: $variant->id)
            ->dispatch('variant-picked', id: $variant->id)
            ->call('addPayment', $this->cash->id)
            ->set('payments.0.amount', '100.00');

        $this->assertSame('2', $component->get('cart')[0]['quantity']);

        $component->call('finalize')->assertHasNoErrors();

        $sale = Sale::sole();
        $component->assertRedirect(route('sales.show', $sale));
        $this->assertSame('60.00', $sale->total);
        $this->assertSame('40.00', $sale->change_amount);
        $this->assertSame(3, $variant->fresh()->stock_on_hand);
    }

    public function test_receipt_by_whatsapp_link_and_email(): void
    {
        Mail::fake();
        $sale = $this->sell($this->variant(), 1, [['method_id' => $this->pix->id, 'amount' => 10000]]);

        $url = app(SaleReceiptService::class)->whatsappUrl($sale->load('items'), '(86) 99989-9079');
        $this->assertStringStartsWith('https://wa.me/5586999899079?text=', $url);
        $this->assertNull(app(SaleReceiptService::class)->whatsappUrl($sale, '123'));

        Livewire::actingAs($this->operator)->test(Show::class, ['sale' => $sale->id])
            ->set('email', 'cliente@example.com')
            ->call('sendEmail')
            ->assertHasNoErrors();

        Mail::assertSent(SaleReceipt::class, fn ($mail) => $mail->hasTo('cliente@example.com'));
    }

    public function test_dashboard_sales_indicators_are_real_and_exclude_cancelled_and_returns(): void
    {
        $variant = $this->variant();
        $this->sell($variant, 1, [['method_id' => $this->pix->id, 'amount' => 10000]]);
        $kept = $this->sell($variant, 2, [['method_id' => $this->pix->id, 'amount' => 20000]]);
        $cancelled = $this->sell($variant, 1, [['method_id' => $this->pix->id, 'amount' => 10000]]);
        $this->operator->givePermissionTo(['vendas.cancelar']);
        app(SaleService::class)->cancel($this->operator, $cancelled, 'Teste');
        app(SaleReturnService::class)->register($this->operator, $kept, [
            ['sale_item_id' => $kept->items()->sole()->id, 'quantity' => 1, 'restock' => true],
        ], 'Teste', $this->pix->id);

        Livewire::actingAs($this->userWith(['dashboard.ver', 'vendas.ver']))->test(Dashboard::class)
            ->assertViewHas('sales', fn ($sales) => $sales['today'] === 20000 && $sales['count'] === 2 && $sales['ticket'] === 15000)
            ->assertViewHas('topProducts', fn ($top) => (int) $top->first()->quantity === 2 && (float) $top->first()->total === 200.0);

        Livewire::actingAs($this->userWith(['dashboard.ver']))->test(Dashboard::class)->assertViewHas('sales', null);
    }

    public function test_default_roles_receive_phase_three_permissions(): void
    {
        $this->assertTrue(Role::findByName('Vendedor')->hasPermissionTo('pdv.vender'));
        $this->assertFalse(Role::findByName('Vendedor')->hasPermissionTo('vendas.cancelar'));
        $this->assertFalse(Role::findByName('Vendedor')->hasPermissionTo('pdv.desconto_livre'));
        $this->assertTrue(Role::findByName('Gerente')->hasPermissionTo('vendas.devolver'));
    }

    public function test_stock_movement_of_sale_is_linked_to_it(): void
    {
        $sale = $this->sell($this->variant(), 1, [['method_id' => $this->pix->id, 'amount' => 10000]]);

        $this->assertSame($sale->id, StockMovement::where('type', 'sale')->sole()->reference_id);
    }
}
