<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\MarketingMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Fila de WhatsApp: o atendente abre cada conversa com a mensagem pronta e
 * registra aqui o resultado.
 */
class WhatsAppQueueService
{
    public function __construct(
        private readonly CampaignService $campaigns,
        private readonly ConsentService $consents,
    ) {}

    public function markSent(MarketingMessage $message, User $actor): void
    {
        $this->close($message, $actor, MarketingMessage::SENT);
    }

    public function skip(MarketingMessage $message, User $actor): void
    {
        $this->close($message, $actor, MarketingMessage::SKIPPED, 'Pulada pelo atendente.');
    }

    /** O cliente pediu para não receber mais mensagens pelo WhatsApp. */
    public function optOut(MarketingMessage $message, User $actor): void
    {
        DB::transaction(function () use ($message, $actor) {
            $this->close($message, $actor, MarketingMessage::SKIPPED, 'Cliente pediu para não receber.');
            $this->consents->set($message->customer, 'whatsapp', false, 'pedido_cliente');
        });
    }

    private function close(MarketingMessage $message, User $actor, string $status, ?string $note = null): void
    {
        $updated = MarketingMessage::whereKey($message->id)
            ->where('channel', 'whatsapp')
            ->where('status', MarketingMessage::PENDING)
            ->update([
                'status' => $status,
                'sent_at' => $status === MarketingMessage::SENT ? now() : null,
                'handled_by' => $actor->id,
                'error' => $note,
                'updated_at' => now(),
            ]);

        if ($updated === 0) {
            throw new BusinessRuleException('Esta mensagem já foi tratada por outra pessoa.');
        }

        $this->campaigns->finishIfDone($message->campaign);
    }
}
