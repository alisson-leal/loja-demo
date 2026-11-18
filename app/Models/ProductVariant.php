<?php

namespace App\Models;

use App\Support\CurrentStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Unidade real de estoque e de venda. Todo produto tem ao menos uma; o produto
 * sem variações possui uma única, sem valores associados.
 *
 * stock_on_hand e stock_reserved só devem ser alterados pelos serviços de estoque.
 */
class ProductVariant extends Model
{
    private const AVAILABLE_SQL = '(stock_on_hand - stock_reserved)';

    protected $fillable = [
        'product_id', 'combination_key', 'sku', 'barcode',
        'cost_price', 'sale_price', 'min_stock', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'stock_on_hand' => 'integer',
            'stock_reserved' => 'integer',
            'min_stock' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function values(): BelongsToMany
    {
        return $this->belongsToMany(VariationValue::class, 'product_variant_value');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(StockReservation::class);
    }

    public function costHistory(): HasMany
    {
        return $this->hasMany(VariantCostHistory::class);
    }

    /** Disponível na rede inteira (soma das lojas). */
    public function available(): int
    {
        return $this->stock_on_hand - $this->stock_reserved;
    }

    public function storeStocks(): HasMany
    {
        return $this->hasMany(StoreStock::class);
    }

    /**
     * Saldo em uma loja (padrão: a loja atual do usuário). A loja principal
     * guarda o que ainda não foi distribuído às outras.
     *
     * @return array{on_hand: int, reserved: int, available: int}
     */
    public function stockIn(?int $storeId = null): array
    {
        $storeId ??= CurrentStore::id();
        $rows = $this->relationLoaded('storeStocks') ? $this->storeStocks : $this->storeStocks()->get();
        $row = $rows->firstWhere('store_id', $storeId);

        if ($row) {
            [$onHand, $reserved] = [$row->on_hand, $row->reserved];
        } elseif ($storeId === CurrentStore::mainId()) {
            $others = $rows->where('store_id', '!=', $storeId);
            [$onHand, $reserved] = [$this->stock_on_hand - $others->sum('on_hand'), $this->stock_reserved - $others->sum('reserved')];
        } else {
            [$onHand, $reserved] = [0, 0];
        }

        return ['on_hand' => (int) $onHand, 'reserved' => (int) $reserved, 'available' => (int) ($onHand - $reserved)];
    }

    public function availableIn(?int $storeId = null): int
    {
        return $this->stockIn($storeId)['available'];
    }

    public function label(): string
    {
        $text = $this->values
            ->sortBy(fn (VariationValue $value) => [$value->attribute->position, $value->attribute->id])
            ->pluck('value')
            ->implode(' / ');

        return $text !== '' ? $text : 'Único';
    }

    public function fullName(): string
    {
        return $this->values->isEmpty() ? $this->product->name : "{$this->product->name} - {$this->label()}";
    }

    public function hasHistory(): bool
    {
        return $this->movements()->exists() || $this->reservations()->exists();
    }

    /** Variações ativas de produtos ativos. */
    public function scopeOperational(Builder $query): Builder
    {
        return $query->where('product_variants.is_active', true)
            ->whereHas('product', fn (Builder $product) => $product->where('is_active', true));
    }

    /**
     * Junta o saldo da loja (colunas store_on_hand e store_reserved). Sem linha
     * na loja principal, vale o total menos o que está nas outras lojas.
     */
    public function scopeWithStoreStock(Builder $query, int $storeId): Builder
    {
        return $query->addSelect('product_variants.*')
            ->leftJoin('store_stocks as ss', fn ($join) => $join->on('ss.product_variant_id', '=', 'product_variants.id')->where('ss.store_id', $storeId))
            ->selectRaw(self::storeSql($storeId, 'on_hand').' as store_on_hand')
            ->selectRaw(self::storeSql($storeId, 'reserved').' as store_reserved');
    }

    /** Expressão SQL do saldo na loja (requer o join "ss" de scopeWithStoreStock). */
    public static function storeSql(int $storeId, string $column): string
    {
        $total = $column === 'on_hand' ? 'product_variants.stock_on_hand' : 'product_variants.stock_reserved';
        $fallback = $storeId === CurrentStore::mainId()
            ? "({$total} - (SELECT COALESCE(SUM(o.{$column}), 0) FROM store_stocks o WHERE o.product_variant_id = product_variants.id AND o.store_id <> {$storeId}))"
            : '0';

        return "COALESCE(ss.{$column}, {$fallback})";
    }

    public function scopeStoreLowStock(Builder $query, int $storeId): Builder
    {
        $available = '('.self::storeSql($storeId, 'on_hand').' - '.self::storeSql($storeId, 'reserved').')';

        return $query->where('min_stock', '>', 0)->whereRaw("{$available} > 0")->whereRaw("{$available} <= min_stock");
    }

    public function scopeStoreOutOfStock(Builder $query, int $storeId): Builder
    {
        return $query->whereRaw('('.self::storeSql($storeId, 'on_hand').' - '.self::storeSql($storeId, 'reserved').') <= 0');
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->where('min_stock', '>', 0)
            ->whereRaw(self::AVAILABLE_SQL.' > 0')
            ->whereRaw(self::AVAILABLE_SQL.' <= min_stock');
    }

    public function scopeOutOfStock(Builder $query): Builder
    {
        return $query->whereRaw(self::AVAILABLE_SQL.' <= 0');
    }

    public function scopeInCategory(Builder $query, int $categoryId): Builder
    {
        return $query->whereHas('product', fn (Builder $product) => $product
            ->whereIn('category_id', Category::idsIncludingChildren($categoryId)));
    }

    /** Busca por nome do produto, código, SKU ou código de barras. */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $like = "%{$term}%";

        return $query->where(fn (Builder $inner) => $inner
            ->where('product_variants.sku', 'like', $like)
            ->orWhere('product_variants.barcode', $term)
            ->orWhereHas('product', fn (Builder $product) => $product
                ->where('name', 'like', $like)
                ->orWhere('internal_code', 'like', $like)));
    }
}
