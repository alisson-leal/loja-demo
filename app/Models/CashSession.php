<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashSession extends Model
{
    public const OPEN = 'open';

    public const CLOSED = 'closed';

    protected $fillable = [
        'user_id', 'status', 'opening_amount', 'expected_amount', 'counted_amount',
        'difference', 'closing_notes', 'closed_by', 'opened_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'opening_amount' => 'decimal:2',
            'expected_amount' => 'decimal:2',
            'counted_amount' => 'decimal:2',
            'difference' => 'decimal:2',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function isOpen(): bool
    {
        return $this->status === self::OPEN;
    }

    public function isVisibleTo(User $user): bool
    {
        return $this->user_id === $user->id || $user->can('caixa.gerir');
    }
}
