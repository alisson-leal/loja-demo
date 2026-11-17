<?php

namespace App\Models;

use App\Models\Concerns\Immutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Histórico de consentimento (LGPD): somente inclusão. Cada linha é uma
 * concessão ou revogação, com origem, responsável e data.
 */
class CustomerConsent extends Model
{
    use Immutable;

    public const UPDATED_AT = null;

    public const CHANNEL_LABELS = [
        'email' => 'E-mail',
        'whatsapp' => 'WhatsApp',
    ];

    public const SOURCE_LABELS = [
        'cadastro' => 'Cadastro de clientes',
        'pdv' => 'Frente de caixa',
        'descadastro' => 'Link de descadastro do e-mail',
        'pedido_cliente' => 'Pedido do cliente pelo WhatsApp',
    ];

    protected $fillable = ['customer_id', 'channel', 'granted', 'source', 'user_id', 'ip_address'];

    protected function casts(): array
    {
        return [
            'granted' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function channelLabel(): string
    {
        return self::CHANNEL_LABELS[$this->channel] ?? $this->channel;
    }

    public function sourceLabel(): string
    {
        return self::SOURCE_LABELS[$this->source] ?? $this->source;
    }
}
