<?php

namespace App\Models;

use App\Support\Quantity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'internal_code', 'sku', 'name', 'description', 'category_id', 'brand_id',
        'supplier_id', 'cost_price', 'sale_price', 'is_active', 'tracks_serial', 'warranty_months', 'unit',
    ];

    protected $attributes = ['unit' => Quantity::UNIT];

    /** Vendido por metro ou quilo: quantidades em milésimos (ver App\Support\Quantity). */
    public function isFractional(): bool
    {
        return Quantity::isFractional($this->unit);
    }

    public function formatQuantity(int|float|string|null $quantity, bool $withUnit = true): string
    {
        return Quantity::format($quantity, $this->unit, $withUnit);
    }

    public function unitLabel(): string
    {
        return Quantity::UNITS[$this->unit] ?? Quantity::UNITS[Quantity::UNIT];
    }

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'is_active' => 'boolean',
            'tracks_serial' => 'boolean',
            'warranty_months' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('position')->orderBy('id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('id');
    }
}
