<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Uma unidade física de um produto com controle de série. O status só muda
 * pelo SerialService, chamado pelo StockService junto com o saldo.
 */
class SerialNumber extends Model
{
    public const IN_STOCK = 'in_stock';

    public const IN_TRANSIT = 'in_transit';

    public const SOLD = 'sold';

    public const REMOVED = 'removed';

    public const LABELS = [
        self::IN_STOCK => 'Em estoque',
        self::IN_TRANSIT => 'Em transferência',
        self::SOLD => 'Vendido',
        self::REMOVED => 'Fora do estoque',
    ];

    protected $fillable = ['product_variant_id', 'serial', 'status', 'store_id', 'sale_item_id'];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(SerialNumberEvent::class)->orderBy('id');
    }

    public function statusLabel(): string
    {
        return self::LABELS[$this->status] ?? $this->status;
    }
}
