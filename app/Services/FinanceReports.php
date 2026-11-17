<?php

namespace App\Services;

use App\Models\ExpenseCategory;
use App\Models\Payable;
use App\Models\Receivable;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\SaleReturn;
use App\Support\Money;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Números do financeiro e dos relatórios gerenciais. Valores sempre em
 * centavos. Vendas canceladas não contam; devoluções reduzem a receita.
 */
class FinanceReports
{
    /**
     * Fluxo de caixa diário: o que entrou e saiu (realizado) e o que está
     * previsto para entrar e sair no período.
     *
     * @return array{days: list<array<string, mixed>>, totals: array<string, int>, overdue_payables: int, late_receivables: int}
     */
    public function cashFlow(CarbonInterface $from, CarbonInterface $to): array
    {
        [$start, $end] = [$from->toDateString(), $to->toDateString()];

        $received = Receivable::where('status', Receivable::RECEIVED)->whereBetween('received_at', [$start, $end])
            ->groupBy('received_at')->selectRaw('received_at as day, SUM(net_amount) as total')->toBase()->pluck('total', 'day');
        $expected = Receivable::pending()->whereBetween('due_date', [$start, $end])
            ->groupBy('due_date')->selectRaw('due_date as day, SUM(net_amount) as total')->toBase()->pluck('total', 'day');
        $paid = Payable::where('status', Payable::PAID)->whereBetween('paid_at', [$start, $end])
            ->groupBy('paid_at')->selectRaw('paid_at as day, SUM(amount) as total')->toBase()->pluck('total', 'day');
        $open = Payable::open()->whereBetween('due_date', [$start, $end])
            ->groupBy('due_date')->selectRaw('due_date as day, SUM(amount) as total')->toBase()->pluck('total', 'day');

        $days = [];
        $totals = ['received' => 0, 'expected' => 0, 'paid' => 0, 'open' => 0, 'balance' => 0];
        $running = 0;

        foreach (CarbonPeriod::create($from->copy()->startOfDay(), $to->copy()->startOfDay()) as $day) {
            $key = $day->toDateString();
            $row = [
                'date' => $day->copy(),
                'received' => Money::toCents($received[$key] ?? 0),
                'expected' => Money::toCents($expected[$key] ?? 0),
                'paid' => Money::toCents($paid[$key] ?? 0),
                'open' => Money::toCents($open[$key] ?? 0),
            ];
            $row['balance'] = $row['received'] + $row['expected'] - $row['paid'] - $row['open'];
            $running += $row['balance'];
            $row['running'] = $running;

            foreach (['received', 'expected', 'paid', 'open', 'balance'] as $field) {
                $totals[$field] += $row[$field];
            }

            $days[] = $row;
        }

        return [
            'days' => $days,
            'totals' => $totals,
            'overdue_payables' => Money::toCents(Payable::open()->where('due_date', '<', today()->toDateString())->sum('amount')),
            'late_receivables' => Money::toCents(Receivable::pending()->where('due_date', '<', today()->toDateString())->sum('net_amount')),
        ];
    }

    /** @return array<string, int> */
    public function salesSummary(CarbonInterface $from, CarbonInterface $to): array
    {
        $range = $this->range($from, $to);
        $sales = Sale::where('status', Sale::COMPLETED)->whereBetween('created_at', $range);

        $gross = Money::toCents((clone $sales)->sum('total'));
        $discounts = Money::toCents((clone $sales)->sum('discount_total'));
        $count = (clone $sales)->count();
        $items = (int) SaleItem::whereIn('sale_id', (clone $sales)->select('id'))->sum('quantity');
        $returns = Money::toCents(SaleReturn::whereBetween('created_at', $range)->sum('total'));
        $cancelled = Sale::where('status', Sale::CANCELLED)->whereBetween('created_at', $range)->count();

        return [
            'gross' => $gross,
            'discounts' => $discounts,
            'returns' => $returns,
            'net' => $gross - $returns,
            'count' => $count,
            'cancelled' => $cancelled,
            'items' => $items,
            'ticket' => $count > 0 ? (int) round($gross / $count) : 0,
        ];
    }

    /** Por forma de pagamento, com a taxa da maquininha e o valor líquido. */
    public function salesByPaymentMethod(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return SalePayment::query()
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->join('payment_methods', 'payment_methods.id', '=', 'sale_payments.payment_method_id')
            ->leftJoinSub(
                Receivable::where('status', '!=', Receivable::CANCELLED)->groupBy('sale_payment_id')
                    ->selectRaw('sale_payment_id, SUM(fee_amount) as fee'),
                'fees', 'fees.sale_payment_id', '=', 'sale_payments.id')
            ->where('sales.status', Sale::COMPLETED)
            ->whereBetween('sales.created_at', $this->range($from, $to))
            ->groupBy('payment_methods.id', 'payment_methods.name')
            ->orderByDesc(DB::raw('SUM(sale_payments.amount)'))
            ->selectRaw('payment_methods.name as name, COUNT(*) as payments, SUM(sale_payments.amount) as gross, COALESCE(SUM(fees.fee), 0) as fee')
            ->toBase()
            ->get()
            ->map(fn ($row) => (object) [
                'name' => $row->name,
                'payments' => (int) $row->payments,
                'gross' => Money::toCents($row->gross),
                'fee' => Money::toCents($row->fee),
                'net' => Money::toCents($row->gross) - Money::toCents($row->fee),
            ]);
    }

    public function salesBySeller(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return Sale::query()
            ->join('users', 'users.id', '=', 'sales.user_id')
            ->where('sales.status', Sale::COMPLETED)
            ->whereBetween('sales.created_at', $this->range($from, $to))
            ->groupBy('users.id', 'users.name')
            ->orderByDesc(DB::raw('SUM(sales.total)'))
            ->selectRaw('users.name as name, COUNT(*) as sales, SUM(sales.total) as total')
            ->toBase()
            ->get()
            ->map(fn ($row) => (object) [
                'name' => $row->name,
                'sales' => (int) $row->sales,
                'total' => Money::toCents($row->total),
                'ticket' => (int) round(Money::toCents($row->total) / max(1, (int) $row->sales)),
            ]);
    }

    /** Receita, custo e margem por categoria principal (subcategorias somadas à mãe). */
    public function salesByCategory(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return $this->itemsQuery($from, $to)
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoin('categories as parents', 'parents.id', '=', 'categories.parent_id')
            ->groupBy(DB::raw("COALESCE(parents.name, categories.name, 'Sem categoria')"))
            ->selectRaw("COALESCE(parents.name, categories.name, 'Sem categoria') as name")
            ->selectRaw($this->itemTotalsSql())
            ->orderByDesc('revenue')
            ->toBase()
            ->get()
            ->map(fn ($row) => $this->withMargin($row));
    }

    public function topProducts(CarbonInterface $from, CarbonInterface $to, int $limit = 30): Collection
    {
        return $this->itemsQuery($from, $to)
            ->groupBy('sale_items.product_variant_id', 'sale_items.description')
            ->selectRaw('sale_items.description as name')
            ->selectRaw($this->itemTotalsSql())
            ->orderByDesc('quantity')
            ->limit($limit)
            ->toBase()
            ->get()
            ->map(fn ($row) => $this->withMargin($row));
    }

    /**
     * Resultado simplificado do período (DRE gerencial).
     *
     * Receita: vendas concluídas menos devoluções do período. Custo: custo das
     * peças vendidas menos o das devolvidas. Despesas: contas com vencimento no
     * período, exceto compra de mercadorias (já no custo) e devoluções (já na receita).
     *
     * @return array<string, mixed>
     */
    public function result(CarbonInterface $from, CarbonInterface $to): array
    {
        $range = $this->range($from, $to);
        $summary = $this->salesSummary($from, $to);

        $soldCost = (float) SaleItem::join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', Sale::COMPLETED)->whereBetween('sales.created_at', $range)
            ->sum(DB::raw('sale_items.unit_cost * sale_items.quantity'));
        $returnedCost = (float) DB::table('sale_return_items')
            ->join('sale_returns', 'sale_returns.id', '=', 'sale_return_items.sale_return_id')
            ->join('sale_items', 'sale_items.id', '=', 'sale_return_items.sale_item_id')
            ->whereBetween('sale_returns.created_at', $range)
            ->sum(DB::raw('sale_items.unit_cost * sale_return_items.quantity'));
        $cost = Money::toCents($soldCost) - Money::toCents($returnedCost);

        $fees = Money::toCents(Receivable::join('sales', 'sales.id', '=', 'receivables.sale_id')
            ->where('receivables.status', '!=', Receivable::CANCELLED)
            ->whereBetween('sales.created_at', $range)
            ->sum('receivables.fee_amount'));

        $expenses = Payable::query()
            ->join('expense_categories', 'expense_categories.id', '=', 'payables.expense_category_id')
            ->where('payables.status', '!=', Payable::CANCELLED)
            ->whereBetween('payables.due_date', [$from->toDateString(), $to->toDateString()])
            ->where(fn ($q) => $q->whereNull('expense_categories.system_key')
                ->orWhereNotIn('expense_categories.system_key', [ExpenseCategory::MERCHANDISE, ExpenseCategory::REFUNDS]))
            ->groupBy('expense_categories.id', 'expense_categories.name')
            ->orderByDesc(DB::raw('SUM(payables.amount)'))
            ->selectRaw('expense_categories.name as name, SUM(payables.amount) as total')
            ->toBase()
            ->get()
            ->map(fn ($row) => (object) ['name' => $row->name, 'total' => Money::toCents($row->total)]);

        $merchandise = Money::toCents(Payable::query()
            ->join('expense_categories', 'expense_categories.id', '=', 'payables.expense_category_id')
            ->where('expense_categories.system_key', ExpenseCategory::MERCHANDISE)
            ->where('payables.status', '!=', Payable::CANCELLED)
            ->whereBetween('payables.due_date', [$from->toDateString(), $to->toDateString()])
            ->sum('payables.amount'));

        $grossProfit = $summary['net'] - $cost - $fees;
        $expenseTotal = $expenses->sum('total');
        $result = $grossProfit - $expenseTotal;

        return [
            'gross' => $summary['gross'],
            'returns' => $summary['returns'],
            'net' => $summary['net'],
            'cost' => $cost,
            'fees' => $fees,
            'gross_profit' => $grossProfit,
            'gross_margin' => $summary['net'] > 0 ? round($grossProfit / $summary['net'] * 100, 1) : null,
            'expenses' => $expenses,
            'expense_total' => $expenseTotal,
            'result' => $result,
            'result_margin' => $summary['net'] > 0 ? round($result / $summary['net'] * 100, 1) : null,
            'merchandise' => $merchandise,
        ];
    }

    /** Peças com estoque sem nenhuma venda nos últimos $days dias. */
    public function stalledStock(int $days, int $limit = 50): Collection
    {
        $since = now()->subDays($days);

        return DB::table('product_variants')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->where('product_variants.stock_on_hand', '>', 0)
            ->where('product_variants.is_active', true)
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('sale_items')
                ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                ->whereColumn('sale_items.product_variant_id', 'product_variants.id')
                ->where('sales.status', Sale::COMPLETED)
                ->where('sales.created_at', '>=', $since))
            ->selectRaw('product_variants.id, products.name as product, product_variants.sku, product_variants.stock_on_hand as quantity')
            ->selectRaw('product_variants.stock_on_hand * product_variants.cost_price as cost_value')
            ->selectRaw('product_variants.stock_on_hand * product_variants.sale_price as sale_value')
            ->selectRaw('(SELECT MAX(s.created_at) FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE si.product_variant_id = product_variants.id AND s.status = ?) as last_sale', [Sale::COMPLETED])
            ->orderByDesc('cost_value')
            ->limit($limit)
            ->get();
    }

    /** @return array{quantity: int, cost: int, sale: int} */
    public function stockValue(): array
    {
        $row = DB::table('product_variants')
            ->where('stock_on_hand', '>', 0)
            ->selectRaw('COALESCE(SUM(stock_on_hand), 0) as quantity, COALESCE(SUM(stock_on_hand * cost_price), 0) as cost, COALESCE(SUM(stock_on_hand * sale_price), 0) as sale')
            ->first();

        return ['quantity' => (int) $row->quantity, 'cost' => Money::toCents($row->cost), 'sale' => Money::toCents($row->sale)];
    }

    private function itemsQuery(CarbonInterface $from, CarbonInterface $to)
    {
        return SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('product_variants', 'product_variants.id', '=', 'sale_items.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->where('sales.status', Sale::COMPLETED)
            ->whereBetween('sales.created_at', $this->range($from, $to));
    }

    /** Quantidade, receita e custo líquidos de devoluções. */
    private function itemTotalsSql(): string
    {
        return 'SUM(sale_items.quantity - sale_items.returned_quantity) as quantity, '
            .'SUM(sale_items.total - COALESCE((SELECT SUM(ri.amount) FROM sale_return_items ri WHERE ri.sale_item_id = sale_items.id), 0)) as revenue, '
            .'SUM(sale_items.unit_cost * (sale_items.quantity - sale_items.returned_quantity)) as cost';
    }

    private function withMargin(object $row): object
    {
        $revenue = Money::toCents($row->revenue);
        $cost = Money::toCents($row->cost);

        return (object) [
            'name' => $row->name,
            'quantity' => (int) $row->quantity,
            'revenue' => $revenue,
            'cost' => $cost,
            'profit' => $revenue - $cost,
            'margin' => $revenue > 0 ? round(($revenue - $cost) / $revenue * 100, 1) : null,
        ];
    }

    /** @return array{CarbonInterface, CarbonInterface} */
    private function range(CarbonInterface $from, CarbonInterface $to): array
    {
        return [$from->copy()->startOfDay(), $to->copy()->endOfDay()];
    }
}
