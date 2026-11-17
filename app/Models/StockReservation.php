<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockReservation extends Model
{
    public const ACTIVE = 'active';

    public const CONSUMED = 'consumed';

    public const RELEASED = 'released';

    public const EXPIRED = 'expired';

    protected $fillable = ['product_variant_id', 'quantity', 'status', 'reference_key', 'expires_at'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'expires_at' => 'datetime'];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
