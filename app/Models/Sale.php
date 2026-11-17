<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    public const COMPLETED = 'completed';

    public const CANCELLED = 'cancelled';

    protected $fillable = [
        'checkout_token', 'channel', 'status', 'cash_session_id', 'user_id', 'customer_id',
        'subtotal', 'discount_total', 'total', 'change_amount', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'total' => 'decimal:2',
            'change_amount' => 'decimal:2',
            'cancelled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function cancelAuthorizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancel_authorized_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(SaleReturn::class);
    }

    public function number(): string
    {
        return str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    public function isCompleted(): bool
    {
        return $this->status === self::COMPLETED;
    }

    public function isVisibleTo(User $user): bool
    {
        return $this->user_id === $user->id || $user->can('vendas.ver');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->can('vendas.ver') ? $query : $query->where('user_id', $user->id);
    }
}
