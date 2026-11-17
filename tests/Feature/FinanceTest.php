<?php

namespace Tests\Feature;

use App\Exceptions\BusinessRuleException;
use App\Livewire\Catalog\PaymentMethods;
use App\Livewire\Finance\PayableForm;
use App\Livewire\Finance\Payables;
use App\Livewire\Finance\Receivables;
use App\Models\Category;
use App\Models\ExpenseCategory;
use App\Models\Payable;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Receivable;
use App\Models\Sale;
use App\Models\User;
use App\Services\CashService;
use App\Services\FinanceReports;
use App\Services\PayableService;
use App\Services\ReceivableService;
use App\Services\SaleReturnService;
use App\Services\SaleService;
use Database\Seeders\PaymentMethodsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class FinanceTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private User $operator;

    private User $manager;

    private ProductVariant $variant;

    private PaymentMethod $cash;

    private PaymentMethod $pix;

    private PaymentMethod $debit;

    private PaymentMethod $credit;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-03-10 10:00:00');

        $this->seed(PaymentMethodsSeeder::class);
        $this->cash = PaymentMethod::where('type', 'cash')->sole();
        $this->pix = PaymentMethod::where('type', 'pix')->sole();
        $this->debit = PaymentMethod::where('type', 'debit')->sole();
        $this->debit->update(['fee_percent' => 1.5, 'settlement_days' => 1]);
        $this->credit = PaymentMethod::where('type', 'credit')->sole();
        $this->credit->update(['fee_percent' => 3, 'installment_fee_percent' => 4.5, 'settlement_days' => 30, 'max_installments' => 6]);

        $this->operator = $this->userWith(['pdv.vender', 'caixa.operar', 'vendas.devolver']);
        $this->manager = $this->userWith(['financeiro.ver', 'financeiro.pagar', 'financeiro.receber', 'relatorios.vendas', 'relatorios.produtos', 'relatorios.resultado', 'produtos.ver_custo', 'cadastros.ver', 'cadastros.gerir']);
        app(CashService::class)->open($this->operator, 0);

        $category = Category::create(['name' => 'Vestidos']);
        $product = Product::create(['name' => 'Vestido', 'category_id' => $category->id, 'sale_price' => '100.00']);
        $this->variant = new ProductVariant(['product_id' => $product->id, 'sku' => 'VES', 'sale_price' => '100.00', 'cost_price' => '40.00']);
        $this->variant->stock_on_hand = 50;
        $this->variant->save();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function sell(array $payments, int $quantity = 1): Sale
    {
        return app(SaleService::class)->finalize(
            $this->operator,
            [['variant_id' => $this->variant->id, 'quantity' => $quantity, 'discount' => 0]],
            $payments,
            null,
            0,
            null,
            (string) Str::uuid(),
        );
    }

    public function test_sale_payments_become_receivables_with_fees_and_dates(): void
    {
        $this->sell([['method_id' => $this->cash->id, 'amount' => 10000]]);
        $this->sell([['method_id' => $this->pix->id, 'amount' => 10000]]);
        $this->sell([['method_id' => $this->debit->id, 'amount' => 10000]]);
        $this->sell([['method_id' => $this->credit->id, 'amount' => 10000, 'installments' => 3]]);

        $cash = Receivable::where('payment_method_id', $this->cash->id)->sole();
        $this->assertSame(Receivable::RECEIVED, $cash->status);
        $this->assertSame('2026-03-10', $cash->received_at->toDateString());
        $this->assertSame(Receivable::RECEIVED, Receivable::where('payment_method_id', $this->pix->id)->value('status'));

        $debit = Receivable::where('payment_method_id', $this->debit->id)->sole();
        $this->assertSame(Receivable::PENDING, $debit->status);
        $this->assertSame('2026-03-11', $debit->due_date->toDateString());
        $this->assertSame('1.50', $debit->fee_amount);
        $this->assertSame('98.50', $debit->net_amount);

        $credit = Receivable::where('payment_method_id', $this->credit->id)->orderBy('installment')->get();
        $this->assertSame(['33.34', '33.33', '33.33'], $credit->pluck('gross_amount')->all());
        $this->assertSame(['2026-04-09', '2026-05-09', '2026-06-08'], $credit->map(fn ($r) => $r->due_date->toDateString())->all());
        $this->assertSame('1.50', $credit[0]->fee_amount, 'Parcelado usa a taxa de 4,5%.');
        $this->assertSame(3, Sale::latest('id')->first()->payments->first()->installments);
    }

    public function test_installments_are_validated(): void
    {
        $this->expectException(BusinessRuleException::class);
        $this->sell([['method_id' => $this->debit->id, 'amount' => 10000, 'installments' => 2]]);
    }

    public function test_installments_above_the_limit_are_refused(): void
    {
        $this->expectException(BusinessRuleException::class);
        $this->sell([['method_id' => $this->credit->id, 'amount' => 10000, 'installments' => 7]]);
    }

    public function test_cancelled_sale_cancels_receivables_and_refund_becomes_paid_expense(): void
    {
        $sale = $this->sell([['method_id' => $this->credit->id, 'amount' => 10000, 'installments' => 2]]);
        app(SaleService::class)->cancel($this->operator, $sale, 'Desistência');
        $this->assertSame(0, Receivable::where('status', '!=', Receivable::CANCELLED)->count());

        $sale = $this->sell([['method_id' => $this->pix->id, 'amount' => 20000]], 2);
        $item = $sale->items()->first();
        app(SaleReturnService::class)->register($this->operator, $sale, [['sale_item_id' => $item->id, 'quantity' => 1, 'restock' => true]], 'Tamanho errado', $this->pix->id);

        $refund = Payable::sole();
        $this->assertSame(Payable::PAID, $refund->status);
        $this->assertSame('100.00', $refund->amount);
        $this->assertSame(ExpenseCategory::REFUNDS, $refund->category->system_key);

        // Troca por vale não tira dinheiro do caixa.
        app(SaleReturnService::class)->register($this->operator, $sale, [['sale_item_id' => $item->id, 'quantity' => 1, 'restock' => true]], 'Troca', null);
        $this->assertSame(1, Payable::count());

        $this->expectException(BusinessRuleException::class);
        app(PayableService::class)->reopen($refund);
    }

    public function test_receivables_are_confirmed_once_and_not_in_the_future(): void
    {
        $this->sell([['method_id' => $this->debit->id, 'amount' => 10000]]);
        $receivable = Receivable::sole();

        Carbon::setTestNow('2026-03-12 09:00:00');
        $this->actingAs($this->manager);

        Livewire::test(Receivables::class)
            ->assertSee('98,50')
            ->call('selectAllDue')
            ->assertSet('selected', [(string) $receivable->id])
            ->set('receivedDate', '2026-03-13')
            ->call('confirm')
            ->assertSee('não pode ser futura')
            ->set('receivedDate', '2026-03-11')
            ->call('confirm')
            ->assertSee('1 recebimento(s) confirmado(s).');

        $this->assertSame(Receivable::RECEIVED, $receivable->fresh()->status);
        $this->assertSame(0, app(ReceivableService::class)->confirm([$receivable->id], today(), $this->manager));
    }

    public function test_payable_series_payment_reopen_and_cancel(): void
    {
        $this->actingAs($this->manager);
        $rent = ExpenseCategory::where('name', 'Aluguel')->sole();
        $goods = ExpenseCategory::where('system_key', ExpenseCategory::MERCHANDISE)->sole();

        Livewire::test(PayableForm::class)
            ->set('description', 'Nota 123 Fornecedor')
            ->set('categoryId', (string) $goods->id)
            ->set('dueDate', '2026-01-31')
            ->set('amount', '100')
            ->set('mode', 'parcelada')
            ->set('times', '3')
            ->assertSee('3 parcelas mensais')
            ->call('save')
            ->assertRedirect(route('finance.payables'));

        $series = Payable::orderBy('installment')->get();
        $this->assertSame(['33.34', '33.33', '33.33'], $series->pluck('amount')->all());
        $this->assertSame(['2026-01-31', '2026-02-28', '2026-03-31'], $series->map(fn ($p) => $p->due_date->toDateString())->all());

        Livewire::test(PayableForm::class)
            ->set('description', 'Aluguel')
            ->set('categoryId', (string) $rent->id)
            ->set('dueDate', '2026-03-05')
            ->set('amount', '1500,00')
            ->set('mode', 'mensal')
            ->set('times', '12')
            ->call('save');
        $this->assertSame(12, Payable::where('description', 'Aluguel')->count());
        $this->assertSame('1500.00', Payable::where('description', 'Aluguel')->value('amount'));

        $march = Payable::where('description', 'Aluguel')->whereDate('due_date', '2026-03-05')->sole();
        $component = Livewire::test(Payables::class)
            ->assertSee('Vencida')
            ->set('paymentDate', '2026-03-09')
            ->call('pay', $march->id)
            ->assertSee('Pagamento registrado.');
        $this->assertSame('2026-03-09', $march->fresh()->paid_at->toDateString());

        $component->call('reopen', $march->id);
        $this->assertTrue($march->fresh()->isOpen());

        $component->call('startCancel', $march->id)->set('cancelReason', '')->call('confirmCancel')->assertHasErrors('cancelReason')
            ->set('cancelReason', 'Lançada em duplicidade')->call('confirmCancel');
        $this->assertSame(Payable::CANCELLED, $march->fresh()->status);

        $this->expectException(BusinessRuleException::class);
        app(PayableService::class)->pay($march->fresh(), today(), $this->manager);
    }

    public function test_cash_flow_combines_receivables_and_payables(): void
    {
        $this->sell([['method_id' => $this->pix->id, 'amount' => 10000]]);
        $this->sell([['method_id' => $this->debit->id, 'amount' => 10000]]);
        $other = ExpenseCategory::where('name', 'Outras despesas')->sole();
        [$paid] = app(PayableService::class)->create(['description' => 'Luz', 'expense_category_id' => $other->id, 'supplier_id' => null, 'document' => null, 'due_date' => '2026-03-10', 'amount_cents' => 3000, 'notes' => null], 1, false, $this->manager);
        app(PayableService::class)->pay($paid, today(), $this->manager);
        app(PayableService::class)->create(['description' => 'Internet', 'expense_category_id' => $other->id, 'supplier_id' => null, 'document' => null, 'due_date' => '2026-03-11', 'amount_cents' => 2000, 'notes' => null], 1, false, $this->manager);

        $flow = app(FinanceReports::class)->cashFlow(Carbon::parse('2026-03-10'), Carbon::parse('2026-03-11'));

        $this->assertSame(['received' => 10000, 'expected' => 9850, 'paid' => 3000, 'open' => 2000, 'balance' => 14850], $flow['totals']);
        $this->assertSame(7000, $flow['days'][0]['running']);
        $this->assertSame(14850, $flow['days'][1]['running']);
    }

    public function test_result_report_matches_the_month(): void
    {
        $sale = $this->sell([['method_id' => $this->credit->id, 'amount' => 20000]], 2);
        $pixSale = $this->sell([['method_id' => $this->pix->id, 'amount' => 10000]]);
        app(SaleReturnService::class)->register($this->operator, $pixSale, [['sale_item_id' => $pixSale->items()->first()->id, 'quantity' => 1, 'restock' => true]], 'Defeito', $this->pix->id);

        $payables = app(PayableService::class);
        $base = ['supplier_id' => null, 'document' => null, 'due_date' => '2026-03-20', 'notes' => null];
        $payables->create($base + ['description' => 'Aluguel', 'expense_category_id' => ExpenseCategory::where('name', 'Aluguel')->value('id'), 'amount_cents' => 5000], 1, false, $this->manager);
        $payables->create($base + ['description' => 'Mercadoria', 'expense_category_id' => ExpenseCategory::where('system_key', 'mercadorias')->value('id'), 'amount_cents' => 50000], 1, false, $this->manager);

        $r = app(FinanceReports::class)->result(Carbon::parse('2026-03-01'), Carbon::parse('2026-03-31'));

        // Vendas 300 - devolução 100 = 200; custo 120 - 40 = 80; taxa 3% de 200 = 6.
        $this->assertSame(30000, $r['gross']);
        $this->assertSame(20000, $r['net']);
        $this->assertSame(8000, $r['cost']);
        $this->assertSame(600, $r['fees']);
        $this->assertSame(11400, $r['gross_profit']);
        $this->assertSame(5000, $r['expense_total'], 'Mercadorias e reembolsos não entram como despesa.');
        $this->assertSame(6400, $r['result']);
        $this->assertSame(50000, $r['merchandise']);

        $this->actingAs($this->manager);
        $this->get(route('reports.result', ['de' => '2026-03-01', 'ate' => '2026-03-31']))->assertOk()->assertSee('R$ 64,00');
        $this->get(route('reports.sales', ['de' => '2026-03-01', 'ate' => '2026-03-31']))->assertOk()->assertSee('R$ 6,00')->assertSee('Vestidos');
        $this->get(route('reports.products'))->assertOk()->assertSee('Vestido');
    }

    public function test_payment_method_terms_are_saved_and_cash_has_none(): void
    {
        $this->actingAs($this->manager);

        Livewire::test(PaymentMethods::class)
            ->call('edit', $this->credit->id)
            ->set('feePercent', '2.99')
            ->set('maxInstallments', '10')
            ->set('installmentFeePercent', '')
            ->call('save')
            ->assertHasNoErrors();

        $credit = $this->credit->fresh();
        $this->assertSame('2.99', $credit->fee_percent);
        $this->assertNull($credit->installment_fee_percent);
        $this->assertSame(10, $credit->max_installments);
        $this->assertSame(2.99, $credit->feeFor(4));

        Livewire::test(PaymentMethods::class)->call('edit', $this->cash->id)->set('feePercent', '5')->call('save');
        $this->assertSame('0.00', $this->cash->fresh()->fee_percent);
    }

    public function test_finance_pages_require_permissions(): void
    {
        $routes = ['finance.payables', 'finance.payables.create', 'finance.receivables', 'finance.cash-flow', 'finance.categories', 'reports.sales', 'reports.result', 'reports.products'];

        $this->actingAs($this->operator);
        foreach ($routes as $route) {
            $this->get(route($route))->assertForbidden();
        }

        $this->actingAs($this->manager);
        foreach ($routes as $route) {
            $this->get(route($route))->assertOk();
        }

        // Quem só vê o financeiro não lança contas.
        $this->actingAs($this->userWith(['financeiro.ver']));
        $this->get(route('finance.payables'))->assertOk()->assertDontSee('Nova conta');
        $this->get(route('finance.payables.create'))->assertForbidden();
    }
}
