<?php

namespace App\Support;

use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;

/**
 * Regras de segmentação de clientes, em um só lugar: usadas pela lista, pela
 * exportação, pelos segmentos salvos e, na Fase 5, pelas campanhas.
 */
final class CustomerFilter
{
    public const SPENT_SQL = "(COALESCE((SELECT SUM(s.total) FROM sales s WHERE s.customer_id = customers.id AND s.status = 'completed'), 0) - COALESCE((SELECT SUM(r.total) FROM sale_returns r JOIN sales s2 ON s2.id = r.sale_id WHERE s2.customer_id = customers.id AND s2.status = 'completed'), 0))";

    public const COUNT_SQL = "(SELECT COUNT(*) FROM sales s WHERE s.customer_id = customers.id AND s.status = 'completed')";

    public const LAST_SQL = "(SELECT MAX(s.created_at) FROM sales s WHERE s.customer_id = customers.id AND s.status = 'completed')";

    public const DEFAULTS = [
        'search' => '',
        'status' => '',
        'min_spent' => '',
        'min_purchases' => '',
        'idle_days' => '',
        'birthday_month' => '',
        'has_children' => '',
        'child_size' => '',
        'child_age_min' => '',
        'child_age_max' => '',
        'category_id' => '',
        'city' => '',
        'neighborhood' => '',
    ];

    /** Mantém só filtros conhecidos, sempre como texto. */
    public static function normalize(array $filters): array
    {
        $clean = self::DEFAULTS;

        foreach (array_keys(self::DEFAULTS) as $key) {
            if (isset($filters[$key]) && is_scalar($filters[$key])) {
                $clean[$key] = trim((string) $filters[$key]);
            }
        }

        return $clean;
    }

    public static function apply(Builder $query, array $filters): Builder
    {
        $f = self::normalize($filters);
        $inactiveFrom = now()->subDays((int) config('loja.customer_inactive_days'));

        return $query
            ->when($f['search'] !== '', fn (Builder $q) => $q->search($f['search']))
            ->when($f['status'] === 'ativos', fn (Builder $q) => $q->whereRaw(self::LAST_SQL.' >= ?', [$inactiveFrom]))
            ->when($f['status'] === 'inativos', fn (Builder $q) => $q->whereRaw(self::LAST_SQL.' < ?', [$inactiveFrom]))
            ->when($f['status'] === 'sem_compras', fn (Builder $q) => $q->whereRaw(self::COUNT_SQL.' = 0'))
            ->when(is_numeric($f['min_spent']), fn (Builder $q) => $q->whereRaw(self::SPENT_SQL.' >= ?', [(float) $f['min_spent']]))
            ->when(ctype_digit($f['min_purchases']), fn (Builder $q) => $q->whereRaw(self::COUNT_SQL.' >= ?', [(int) $f['min_purchases']]))
            ->when(ctype_digit($f['idle_days']), fn (Builder $q) => $q->whereRaw(self::LAST_SQL.' < ?', [now()->subDays((int) $f['idle_days'])]))
            ->when(ctype_digit($f['birthday_month']), fn (Builder $q) => $q->whereMonth('birth_date', (int) $f['birthday_month']))
            ->when($f['has_children'] === '1', fn (Builder $q) => $q->has('children'))
            ->when($f['has_children'] === '0', fn (Builder $q) => $q->doesntHave('children'))
            ->when($f['child_size'] !== '', fn (Builder $q) => $q->whereHas('children', fn (Builder $c) => $c->where('clothing_size', $f['child_size'])))
            ->when(ctype_digit($f['child_age_min']) || ctype_digit($f['child_age_max']), fn (Builder $q) => $q->whereHas('children', function (Builder $c) use ($f) {
                $c->whereNotNull('birth_date')
                    ->when(ctype_digit($f['child_age_min']), fn (Builder $a) => $a->whereDate('birth_date', '<=', now()->subYears((int) $f['child_age_min'])))
                    ->when(ctype_digit($f['child_age_max']), fn (Builder $a) => $a->whereDate('birth_date', '>', now()->subYears((int) $f['child_age_max'] + 1)));
            }))
            ->when(ctype_digit($f['category_id']), fn (Builder $q) => self::boughtInCategory($q, (int) $f['category_id']))
            ->when($f['city'] !== '', fn (Builder $q) => $q->where('city', 'like', "%{$f['city']}%"))
            ->when($f['neighborhood'] !== '', fn (Builder $q) => $q->where('neighborhood', 'like', "%{$f['neighborhood']}%"));
    }

    private static function boughtInCategory(Builder $query, int $categoryId): Builder
    {
        return $query->whereExists(fn ($sub) => $sub->selectRaw('1')
            ->from('sales')
            ->join('sale_items', 'sale_items.sale_id', '=', 'sales.id')
            ->join('product_variants', 'product_variants.id', '=', 'sale_items.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->whereColumn('sales.customer_id', 'customers.id')
            ->where('sales.status', 'completed')
            ->whereIn('products.category_id', Category::idsIncludingChildren($categoryId)));
    }
}
