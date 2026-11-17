<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingCampaign extends Model
{
    public const DRAFT = 'draft';

    public const SENDING = 'sending';

    public const PAUSED = 'paused';

    public const FINISHED = 'finished';

    public const CANCELLED = 'cancelled';

    public const STATUS_LABELS = [
        self::DRAFT => 'Rascunho',
        self::SENDING => 'Enviando',
        self::PAUSED => 'Pausada',
        self::FINISHED => 'Concluída',
        self::CANCELLED => 'Cancelada',
    ];

    public const CHANNEL_LABELS = [
        'email' => 'E-mail',
        'whatsapp' => 'WhatsApp',
    ];

    protected $fillable = ['name', 'channel', 'segment_name', 'filters', 'subject', 'body', 'created_by'];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(MarketingMessage::class, 'campaign_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isDraft(): bool
    {
        return $this->status === self::DRAFT;
    }

    public function isEmail(): bool
    {
        return $this->channel === 'email';
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function channelLabel(): string
    {
        return self::CHANNEL_LABELS[$this->channel] ?? $this->channel;
    }

    public function statusTone(): string
    {
        return match ($this->status) {
            self::SENDING => 'primary',
            self::PAUSED => 'warning',
            self::FINISHED => 'success',
            self::CANCELLED => 'danger',
            default => 'secondary',
        };
    }
}
