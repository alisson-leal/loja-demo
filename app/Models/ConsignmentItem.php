<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsignmentItem extends Model
{
    protected $fillable = ['consignment_id', 'product_variant_id', 'description', 'unit_price', 'quantity'];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'quantity' => 'integer',
            'returned_quantity' => 'integer',
            'kept_quantity' => 'integer',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function consignment(): BelongsTo
    {
        return $this->belongsTo(Consignment::class);
    }
}
