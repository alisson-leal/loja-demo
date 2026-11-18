<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Mercadoria enviada de uma loja para outra. Sai da origem no envio e entra no destino ao ser recebida. */
class StockTransfer extends Model
{
    public const IN_TRANSIT = 'in_transit';

    public const RECEIVED = 'received';

    public const CANCELLED = 'cancelled';

    public const STATUS_LABELS = [
        self::IN_TRANSIT => 'A caminho',
        self::RECEIVED => 'Recebida',
        self::CANCELLED => 'Cancelada',
    ];

    protected $fillable = ['from_store_id', 'to_store_id', 'notes', 'user_id'];

    protected function casts(): array
    {
        return ['received_at' => 'datetime'];
    }

    public function from(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'from_store_id');
    }

    public function to(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'to_store_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class)->orderBy('id');
    }

    public function number(): string
    {
        return str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function statusTone(): string
    {
        return match ($this->status) {
            self::IN_TRANSIT => 'warning',
            self::RECEIVED => 'success',
            default => 'secondary',
        };
    }
}
