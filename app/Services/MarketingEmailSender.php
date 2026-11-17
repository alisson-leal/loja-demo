<?php

namespace App\Services;

use App\Mail\MarketingMail;
use App\Models\MarketingCampaign;
use App\Models\MarketingMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Envia os e-mails pendentes em pequenos lotes, chamado a cada minuto pelo
 * agendador. Respeita o limite por hora do plano de e-mail da loja.
 */
class MarketingEmailSender
{
    private const MAX_ATTEMPTS = 3;

    public function __construct(private readonly CampaignService $campaigns) {}

    /** @return array{sent: int, failed: int, skipped: int} */
    public function sendBatch(): array
    {
        $perHour = max(1, (int) config('loja.marketing.email_per_hour'));
        $sentLastHour = MarketingMessage::where('channel', 'email')->where('status', MarketingMessage::SENT)
            ->where('sent_at', '>=', now()->subHour())->count();
        $limit = min((int) ceil($perHour / 60), $perHour - $sentLastHour);
        $result = ['sent' => 0, 'failed' => 0, 'skipped' => 0];

        if ($limit <= 0) {
            return $result;
        }

        $messages = MarketingMessage::with('customer')
            ->where('channel', 'email')
            ->where('status', MarketingMessage::PENDING)
            ->where(fn (Builder $q) => $q->whereNull('campaign_id')
                ->orWhereHas('campaign', fn (Builder $c) => $c->where('status', MarketingCampaign::SENDING)))
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($messages as $message) {
            $result[$this->send($message)]++;
        }

        $messages->pluck('campaign_id')->filter()->unique()
            ->each(fn ($id) => $this->campaigns->finishIfDone(MarketingCampaign::find($id)));

        return $result;
    }

    /** @return 'sent'|'failed'|'skipped' */
    private function send(MarketingMessage $message): string
    {
        // O cliente pode ter revogado o consentimento depois do disparo.
        if ($message->customer->marketingDestination('email') === null) {
            $message->forceFill(['status' => MarketingMessage::SKIPPED, 'error' => 'Sem consentimento ou e-mail.'])->save();

            return 'skipped';
        }

        try {
            Mail::to($message->destination)->send(new MarketingMail((string) $message->subject, $message->body, $message->customer));
        } catch (Throwable $e) {
            $attempts = $message->attempts + 1;
            $message->forceFill([
                'attempts' => $attempts,
                'status' => $attempts >= self::MAX_ATTEMPTS ? MarketingMessage::FAILED : MarketingMessage::PENDING,
                'error' => Str::limit($e->getMessage(), 240),
            ])->save();
            Log::warning('Falha ao enviar e-mail de marketing', ['message_id' => $message->id, 'erro' => $e->getMessage()]);

            return 'failed';
        }

        $message->forceFill([
            'status' => MarketingMessage::SENT,
            'sent_at' => now(),
            'attempts' => $message->attempts + 1,
            'error' => null,
        ])->save();

        return 'sent';
    }
}
