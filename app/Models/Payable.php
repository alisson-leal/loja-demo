<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Payable extends Model
{
    public const OPEN = 'open';

    public const PAID = 'paid';

    public const CANCELLED = 'cancelled';

    public const STATUS_LABELS = [
        self::OPEN => 'Em aberto',
        self::PAID => 'Paga',
        self::CANCELLED => 'Cancelada',
    ];

    protected $fillable = [
        'description', 'expense_category_id', 'supplier_id', 'document', 'due_date', 'amount',
        'series', 'installment', 'installments', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'paid_at' => 'date',
            'amount' => 'decimal:2',
            'installment' => 'integer',
            'installments' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::OPEN);
    }

    public function isOpen(): bool
    {
        return $this->status === self::OPEN;
    }

    public function isOverdue(): bool
    {
        return $this->isOpen() && $this->due_date->lt(today());
    }

    public function statusLabel(): string
    {
        if ($this->isOverdue()) {
            return 'Vencida';
        }

        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function statusTone(): string
    {
        return match (true) {
            $this->isOverdue() => 'danger',
            $this->status === self::PAID => 'success',
            $this->status === self::CANCELLED => 'secondary',
            default => 'warning',
        };
    }

    public function installmentLabel(): string
    {
        return $this->installments > 1 ? "{$this->installment}/{$this->installments}" : '';
    }
}
