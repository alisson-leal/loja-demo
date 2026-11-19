<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleItem extends Model
{
    protected $fillable = [
        'sale_id', 'product_variant_id', 'description', 'sku', 'quantity', 'returned_quantity',
        'unit_price', 'unit_cost', 'discount', 'total', 'serials', 'warranty_until',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'returned_quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
            'serials' => 'array',
            'warranty_until' => 'date',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function serialNumbers(): HasMany
    {
        return $this->hasMany(SerialNumber::class);
    }

    public function returnableQuantity(): int
    {
        return $this->quantity - $this->returned_quantity;
    }

    public function underWarranty(): bool
    {
        return $this->warranty_until !== null && $this->warranty_until->gte(today());
    }
}
