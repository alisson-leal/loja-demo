<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma mensagem a um cliente, de campanha ou de automação. E-mails são
 * enviados pelo agendador; mensagens de WhatsApp aguardam envio manual.
 */
class MarketingMessage extends Model
{
    public const PENDING = 'pending';

    public const SENT = 'sent';

    public const FAILED = 'failed';

    public const SKIPPED = 'skipped';

    public const EXPIRED = 'expired';

    public const CANCELLED = 'cancelled';

    public const STATUS_LABELS = [
        self::PENDING => 'Pendente',
        self::SENT => 'Enviada',
        self::FAILED => 'Falhou',
        self::SKIPPED => 'Não enviada',
        self::EXPIRED => 'Vencida',
        self::CANCELLED => 'Cancelada',
    ];

    public const AUTOMATION_LABELS = MarketingAutomation::LABELS;

    protected $fillable = [
        'campaign_id', 'automation', 'customer_id', 'channel', 'destination',
        'subject', 'body', 'status', 'dedupe_key', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'expires_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MarketingCampaign::class, 'campaign_id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /** Mensagens de WhatsApp que o atendente ainda deve enviar. */
    public function scopeWhatsappQueue(Builder $query): Builder
    {
        return $query->where('channel', 'whatsapp')
            ->where('status', self::PENDING)
            ->where(fn (Builder $q) => $q->whereNull('campaign_id')
                ->orWhereHas('campaign', fn (Builder $c) => $c->where('status', MarketingCampaign::SENDING)));
    }

    public function originLabel(): string
    {
        if ($this->automation) {
            return self::AUTOMATION_LABELS[$this->automation] ?? $this->automation;
        }

        return $this->campaign?->name ?? 'Campanha';
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function statusTone(): string
    {
        return match ($this->status) {
            self::SENT => 'success',
            self::FAILED => 'danger',
            self::PENDING => 'primary',
            default => 'secondary',
        };
    }
}
