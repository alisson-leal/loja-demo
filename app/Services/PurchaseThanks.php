<?php

namespace App\Services;

use App\Models\MarketingAutomation;
use App\Models\MarketingMessage;
use App\Models\Sale;
use App\Support\MessageTemplate;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Agradecimento pela compra: ao concluir uma venda com cliente identificado,
 * prepara a mensagem nos canais ativos da automação, para quem consentiu.
 * O WhatsApp vai para a fila de envio manual; o e-mail sai pelo agendador.
 */
class PurchaseThanks
{
    /** Agenda para depois da gravação da venda: uma falha aqui nunca desfaz a venda. */
    public function queueAfterCommit(Sale $sale): void
    {
        if ($sale->customer_id === null) {
            return;
        }

        DB::afterCommit(function () use ($sale) {
            try {
                $this->queue($sale);
            } catch (Throwable $e) {
                report($e);
            }
        });
    }

    public function queue(Sale $sale): int
    {
        // Relida do banco: a situação "concluída" vem do valor padrão da coluna.
        $sale = Sale::with('customer')->find($sale->id);
        $automation = MarketingAutomation::where('key', MarketingAutomation::PURCHASE_THANKS)->where('enabled', true)->first();
        $customer = $sale?->customer;

        if (! $automation || ! $customer || ! $sale->isCompleted()) {
            return 0;
        }

        $extra = ['{numero_venda}' => $sale->number(), '{valor_compra}' => Money::format($sale->total)];
        $created = 0;

        foreach ($automation->channels() as $channel) {
            $destination = $customer->marketingDestination($channel);

            if ($destination === null) {
                continue;
            }

            $template = $channel === 'email' ? $automation->email_body : $automation->whatsapp_body;
            $created += MarketingMessage::insertOrIgnore([
                'automation' => $automation->key,
                'customer_id' => $customer->id,
                'channel' => $channel,
                'destination' => $destination,
                'subject' => $channel === 'email' ? MessageTemplate::render($automation->email_subject, $customer, null, null, $extra) : null,
                'body' => MessageTemplate::forChannel($channel, MessageTemplate::render($template, $customer, null, null, $extra)),
                'status' => MarketingMessage::PENDING,
                'dedupe_key' => $this->key($sale, $channel),
                'expires_at' => $channel === 'whatsapp'
                    ? today()->addDays(max(1, (int) config('loja.marketing.whatsapp_expires_days')))->endOfDay()
                    : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $created;
    }

    /** Venda cancelada: o agradecimento ainda não enviado não faz mais sentido. */
    public function cancelForSale(Sale $sale): void
    {
        MarketingMessage::whereIn('dedupe_key', [$this->key($sale, 'whatsapp'), $this->key($sale, 'email')])
            ->where('status', MarketingMessage::PENDING)
            ->update(['status' => MarketingMessage::CANCELLED, 'error' => 'Venda cancelada.', 'updated_at' => now()]);
    }

    private function key(Sale $sale, string $channel): string
    {
        return MarketingAutomation::PURCHASE_THANKS.":{$sale->id}:{$channel}";
    }
}
