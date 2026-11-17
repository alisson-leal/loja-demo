<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inventory extends Model
{
    public const OPEN = 'open';

    public const FINISHED = 'finished';

    public const CANCELLED = 'cancelled';

    public const STATUS_LABELS = [
        self::OPEN => 'Em contagem',
        self::FINISHED => 'Concluído',
        self::CANCELLED => 'Cancelado',
    ];

    protected $fillable = ['status', 'category_id', 'notes', 'opened_by', 'closed_by', 'closed_at'];

    protected function casts(): array
    {
        return ['closed_at' => 'datetime'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function isOpen(): bool
    {
        return $this->status === self::OPEN;
    }
}
