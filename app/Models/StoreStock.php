<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Saldo de uma variação em uma loja. Só o StockService e o
 * StockReservationService alteram; a soma das lojas é o total da variação.
 */
class StoreStock extends Model
{
    protected $fillable = ['store_id', 'product_variant_id', 'on_hand', 'reserved'];

    protected function casts(): array
    {
        return ['on_hand' => 'integer', 'reserved' => 'integer'];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function available(): int
    {
        return $this->on_hand - $this->reserved;
    }
}
