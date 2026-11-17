<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\SaleReturn;
use App\Support\Money;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Indicadores de vendas do painel. Faturamento é o total das vendas concluídas
 * menos as devoluções do período; vendas canceladas não contam.
 */
class SalesMetrics
{
    /**
     * @return array{today: int, month: int, period: int, count: int, ticket: int, previous: int, variation: ?float}
     */
    public function summary(CarbonInterface $from, CarbonInterface $to, ?int $userId): array
    {
        $period = $this->revenueCents($from, $to, $userId);
        $count = $this->completed($from, $to, $userId)->count();

        $days = $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1;
        $previousTo = $from->copy()->subDay();
        $previousFrom = $previousTo->copy()->subDays((int) $days - 1);
        $previous = $this->revenueCents($previousFrom, $previousTo, $userId);

        return [
            'today' => $this->revenueCents(today(), today(), $userId),
            'month' => $this->revenueCents(today()->startOfMonth(), today(), $userId),
            'period' => $period,
            'count' => $count,
            'ticket' => $count > 0 ? (int) round($this->grossCents($from, $to, $userId) / $count) : 0,
            'previous' => $previous,
            'variation' => $previous > 0 ? round(($period - $previous) / $previous * 100, 1) : null,
        ];
    }

    /** @return array{labels: list<string>, values: list<float>} */
    public function perDay(CarbonInterface $from, CarbonInterface $to, ?int $userId): array
    {
        $totals = $this->completed($from, $to, $userId)
            ->select(DB::raw('DATE(created_at) as day'), DB::raw('SUM(total) as total'))
            ->groupBy('day')
            ->toBase()
            ->pluck('total', 'day');

        $labels = [];
        $values = [];

        foreach (CarbonPeriod::create($from->copy()->startOfDay(), $to->copy()->startOfDay()) as $day) {
            $labels[] = $day->format('d/m');
            $values[] = (float) ($totals[$day->toDateString()] ?? 0);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    public function byPaymentMethod(CarbonInterface $from, CarbonInterface $to, ?int $userId): Collection
    {
        return SalePayment::query()
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->join('payment_methods', 'payment_methods.id', '=', 'sale_payments.payment_method_id')
            ->where('sales.status', Sale::COMPLETED)
            ->whereBetween('sales.created_at', $this->range($from, $to))
            ->when($userId, fn (Builder $query) => $query->where('sales.user_id', $userId))
            ->groupBy('payment_methods.id', 'payment_methods.name')
            ->orderByDesc(DB::raw('SUM(sale_payments.amount)'))
            ->selectRaw('payment_methods.name as name, SUM(sale_payments.amount) as total')
            ->toBase()
            ->get();
    }

    public function topProducts(int $limit, CarbonInterface $from, CarbonInterface $to, ?int $userId, ?int $categoryId): Collection
    {
        return SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', Sale::COMPLETED)
            ->whereBetween('sales.created_at', $this->range($from, $to))
            ->when($userId, fn (Builder $query) => $query->where('sales.user_id', $userId))
            ->when($categoryId, fn (Builder $query) => $query
                ->join('product_variants', 'product_variants.id', '=', 'sale_items.product_variant_id')
                ->join('products', 'products.id', '=', 'product_variants.product_id')
                ->whereIn('products.category_id', Category::idsIncludingChildren($categoryId)))
            ->groupBy('sale_items.product_variant_id', 'sale_items.description')
            ->orderByDesc(DB::raw('SUM(sale_items.quantity - sale_items.returned_quantity)'))
            ->limit($limit)
            ->selectRaw('sale_items.description as description, SUM(sale_items.quantity - sale_items.returned_quantity) as quantity, SUM(sale_items.total - COALESCE((SELECT SUM(ri.amount) FROM sale_return_items ri WHERE ri.sale_item_id = sale_items.id), 0)) as total')
            ->toBase()
            ->get();
    }

    public function recent(int $limit, ?int $userId): Collection
    {
        return Sale::with(['user:id,name', 'customer:id,name'])
            ->when($userId, fn (Builder $query) => $query->where('user_id', $userId))
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    private function revenueCents(CarbonInterface $from, CarbonInterface $to, ?int $userId): int
    {
        $returns = SaleReturn::query()
            ->whereBetween('created_at', $this->range($from, $to))
            ->when($userId, fn (Builder $query) => $query->whereHas('sale', fn (Builder $sale) => $sale->where('user_id', $userId)))
            ->sum('total');

        return $this->grossCents($from, $to, $userId) - Money::toCents($returns);
    }

    private function grossCents(CarbonInterface $from, CarbonInterface $to, ?int $userId): int
    {
        return Money::toCents($this->completed($from, $to, $userId)->sum('total'));
    }

    private function completed(CarbonInterface $from, CarbonInterface $to, ?int $userId): Builder
    {
        return Sale::query()
            ->where('status', Sale::COMPLETED)
            ->whereBetween('created_at', $this->range($from, $to))
            ->when($userId, fn (Builder $query) => $query->where('user_id', $userId));
    }

    /** @return array{CarbonInterface, CarbonInterface} */
    private function range(CarbonInterface $from, CarbonInterface $to): array
    {
        return [$from->copy()->startOfDay(), $to->copy()->endOfDay()];
    }
}
