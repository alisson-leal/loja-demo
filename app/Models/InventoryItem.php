<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryItem extends Model
{
    protected $fillable = ['inventory_id', 'product_variant_id', 'counted_quantity', 'expected_quantity'];

    protected function casts(): array
    {
        return ['counted_quantity' => 'integer', 'expected_quantity' => 'integer'];
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
