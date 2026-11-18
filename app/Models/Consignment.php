<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Venda condicional: o cliente leva as peças para provar e devolve ou compra no acerto. */
class Consignment extends Model
{
    public const OPEN = 'open';

    /** Acerto feito: as peças que o cliente ficou aguardam o pagamento no caixa. */
    public const AWAITING_SALE = 'awaiting_sale';

    public const CLOSED = 'closed';

    public const STATUS_LABELS = [
        self::OPEN => 'Com o cliente',
        self::AWAITING_SALE => 'Aguardando pagamento',
        self::CLOSED => 'Encerrada',
    ];

    protected $fillable = ['customer_id', 'user_id', 'due_date', 'notes'];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'closed_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ConsignmentItem::class)->orderBy('id');
    }

    public function number(): string
    {
        return str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }

    public function isOverdue(): bool
    {
        return $this->status === self::OPEN && $this->due_date->lt(today());
    }

    public function statusLabel(): string
    {
        return $this->isOverdue() ? 'Prazo vencido' : (self::STATUS_LABELS[$this->status] ?? $this->status);
    }

    public function statusTone(): string
    {
        return match (true) {
            $this->isOverdue() => 'danger',
            $this->status === self::OPEN => 'primary',
            $this->status === self::AWAITING_SALE => 'warning',
            default => 'secondary',
        };
    }
}
