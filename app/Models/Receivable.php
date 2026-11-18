<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Valor a receber de um pagamento de venda: dinheiro e Pix entram como
 * recebidos; cartões ficam pendentes até a data prevista e a conferência.
 */
class Receivable extends Model
{
    public const PENDING = 'pending';

    public const RECEIVED = 'received';

    public const CANCELLED = 'cancelled';

    public const STATUS_LABELS = [
        self::PENDING => 'A receber',
        self::RECEIVED => 'Recebido',
        self::CANCELLED => 'Cancelado',
    ];

    protected $fillable = [
        'sale_id', 'sale_payment_id', 'payment_method_id', 'installment', 'installments',
        'due_date', 'gross_amount', 'fee_amount', 'net_amount', 'status', 'received_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'received_at' => 'date',
            'gross_amount' => 'decimal:2',
            'fee_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'received_amount' => 'decimal:2',
            'charges_waived' => 'boolean',
            'installment' => 'integer',
            'installments' => 'integer',
        ];
    }

    public function receivedMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'received_method_id');
    }

    /** Parcelas do crediário próprio da loja. */
    public function scopeCrediario(Builder $query): Builder
    {
        return $query->whereHas('method', fn (Builder $m) => $m->where('type', PaymentMethod::CREDIARIO));
    }

    /** Recebimentos de maquininha e Pix (tudo que não é crediário). */
    public function scopeNotCrediario(Builder $query): Builder
    {
        return $query->whereHas('method', fn (Builder $m) => $m->where('type', '!=', PaymentMethod::CREDIARIO));
    }

    public function isOverdue(): bool
    {
        return $this->status === self::PENDING && $this->due_date->lt(today());
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::PENDING);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }
}
