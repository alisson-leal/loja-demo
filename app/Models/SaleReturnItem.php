<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleReturnItem extends Model
{
    protected $fillable = ['sale_return_id', 'sale_item_id', 'quantity', 'amount', 'restocked'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'amount' => 'decimal:2', 'restocked' => 'boolean'];
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }
}
