<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Indicadores do painel, sempre calculados a partir de dados reais do banco.
 */
class DashboardMetrics
{
    public function __construct(private readonly UserSessionService $sessions) {}

    /** @return array{active_users: int, blocked_users: int, roles: int, active_sessions: int} */
    public function summary(): array
    {
        return [
            'active_users' => User::active()->count(),
            'blocked_users' => User::where('is_blocked', true)->count(),
            'roles' => Role::count(),
            'active_sessions' => $this->sessions->activeQuery()->count(),
        ];
    }

    /** @return array{logins: int, failures: int} */
    public function accessTotals(CarbonInterface $from, CarbonInterface $to, ?int $userId): array
    {
        return [
            'logins' => $this->events('auth.login', $from, $to, $userId)->count(),
            'failures' => $this->events('auth.failed', $from, $to, $userId)->count(),
        ];
    }

    /** @return array{labels: list<string>, values: list<int>} */
    public function loginsPerDay(CarbonInterface $from, CarbonInterface $to, ?int $userId): array
    {
        $totals = $this->events('auth.login', $from, $to, $userId)
            ->select(DB::raw('DATE(created_at) as day'), DB::raw('COUNT(*) as total'))
            ->groupBy('day')
            ->toBase()
            ->pluck('total', 'day');

        $labels = [];
        $values = [];

        foreach (CarbonPeriod::create($from->copy()->startOfDay(), $to->copy()->startOfDay()) as $day) {
            $labels[] = $day->format('d/m');
            $values[] = (int) ($totals[$day->toDateString()] ?? 0);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /** @return array{total: int, new: int} */
    public function customerSummary(CarbonInterface $from, CarbonInterface $to): array
    {
        return [
            'total' => Customer::count(),
            'new' => Customer::whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->count(),
        ];
    }

    /** @return array{products: int, low: int, out: int} */
    public function stockSummary(?int $categoryId): array
    {
        return [
            'products' => Product::where('is_active', true)
                ->when($categoryId, fn (Builder $query) => $query->whereIn('category_id', Category::idsIncludingChildren($categoryId)))
                ->count(),
            'low' => $this->operationalVariants($categoryId)->lowStock()->count(),
            'out' => $this->operationalVariants($categoryId)->outOfStock()->count(),
        ];
    }

    public function lowStockItems(int $limit, ?int $categoryId): Collection
    {
        return $this->operationalVariants($categoryId)
            ->lowStock()
            ->with(['product', 'values.attribute'])
            ->orderByRaw('(stock_on_hand - stock_reserved) - min_stock')
            ->limit($limit)
            ->get();
    }

    private function operationalVariants(?int $categoryId): Builder
    {
        return ProductVariant::operational()
            ->when($categoryId, fn (Builder $query) => $query->inCategory($categoryId));
    }

    public function recentActivity(int $limit, ?int $userId): Collection
    {
        return ActivityLog::with('user:id,name')
            ->when($userId, fn (Builder $query) => $query->where('user_id', $userId))
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    private function events(string $event, CarbonInterface $from, CarbonInterface $to, ?int $userId): Builder
    {
        return ActivityLog::query()
            ->where('event', $event)
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->when($userId, fn (Builder $query) => $query->where('user_id', $userId));
    }
}
