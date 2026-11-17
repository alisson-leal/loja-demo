<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Customer;
use App\Models\MarketingCampaign;
use App\Models\MarketingMessage;
use App\Models\User;
use App\Support\CustomerFilter;
use App\Support\MessageTemplate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Campanhas: rascunho, disparo e controle. No disparo, a lista de clientes é
 * congelada em mensagens individuais, só para quem consentiu no canal.
 */
class CampaignService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** Clientes alcançáveis pelos filtros e canal (consentimento e contato válidos). */
    public function audience(array $filters, string $channel): Builder
    {
        return CustomerFilter::apply(Customer::query(), $filters)->reachableBy($channel);
    }

    /**
     * @param  array{name: string, channel: string, segment_name: ?string, filters: array, subject: ?string, body: string}  $data
     */
    public function save(?MarketingCampaign $campaign, array $data, User $actor): MarketingCampaign
    {
        if ($campaign && ! $campaign->isDraft()) {
            throw new BusinessRuleException('Só é possível editar campanhas em rascunho.');
        }

        $campaign ??= new MarketingCampaign(['created_by' => $actor->id]);
        $campaign->fill([
            'name' => $data['name'],
            'channel' => $data['channel'],
            'segment_name' => $data['segment_name'],
            'filters' => CustomerFilter::normalize($data['filters']),
            'subject' => $data['channel'] === 'email' ? $data['subject'] : null,
            'body' => $data['body'],
        ])->save();

        $this->audit->record('marketing.campaign_saved', "Campanha {$campaign->name} salva.", $campaign);

        return $campaign;
    }

    public function launch(MarketingCampaign $campaign, User $actor): int
    {
        return DB::transaction(function () use ($campaign, $actor) {
            $campaign = MarketingCampaign::lockForUpdate()->findOrFail($campaign->id);

            if (! $campaign->isDraft()) {
                throw new BusinessRuleException('Esta campanha já foi iniciada.');
            }

            $total = 0;

            $this->audience($campaign->filters, $campaign->channel)
                ->chunkById(200, function ($customers) use ($campaign, &$total) {
                    $rows = [];

                    foreach ($customers as $customer) {
                        $rows[] = [
                            'campaign_id' => $campaign->id,
                            'customer_id' => $customer->id,
                            'channel' => $campaign->channel,
                            'destination' => $customer->marketingDestination($campaign->channel),
                            'subject' => $campaign->subject ? MessageTemplate::render($campaign->subject, $customer) : null,
                            'body' => MessageTemplate::forChannel($campaign->channel, MessageTemplate::render($campaign->body, $customer)),
                            'status' => MarketingMessage::PENDING,
                            'dedupe_key' => "campanha:{$campaign->id}:{$customer->id}",
                            'expires_at' => null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }

                    $total += MarketingMessage::insertOrIgnore(array_filter($rows, fn ($row) => $row['destination'] !== null));
                });

            if ($total === 0) {
                throw new BusinessRuleException('Nenhum cliente deste público autorizou receber mensagens por '.$campaign->channelLabel().'.');
            }

            $campaign->forceFill([
                'status' => MarketingCampaign::SENDING,
                'started_by' => $actor->id,
                'started_at' => now(),
            ])->save();

            $this->audit->record('marketing.campaign_started', "Campanha {$campaign->name} iniciada para {$total} cliente(s).", $campaign, [
                'destinatarios' => $total,
            ]);

            return $total;
        });
    }

    public function pause(MarketingCampaign $campaign): void
    {
        $this->transition($campaign, [MarketingCampaign::SENDING], MarketingCampaign::PAUSED, 'pausada');
    }

    public function resume(MarketingCampaign $campaign): void
    {
        $this->transition($campaign, [MarketingCampaign::PAUSED], MarketingCampaign::SENDING, 'retomada');
        $this->finishIfDone($campaign->fresh());
    }

    public function cancel(MarketingCampaign $campaign): void
    {
        DB::transaction(function () use ($campaign) {
            $this->transition($campaign, [MarketingCampaign::SENDING, MarketingCampaign::PAUSED], MarketingCampaign::CANCELLED, 'cancelada');

            $campaign->messages()->where('status', MarketingMessage::PENDING)
                ->update(['status' => MarketingMessage::CANCELLED, 'updated_at' => now()]);
        });
    }

    public function delete(MarketingCampaign $campaign): void
    {
        if (! $campaign->isDraft()) {
            throw new BusinessRuleException('Só é possível excluir campanhas em rascunho.');
        }

        $this->audit->record('marketing.campaign_deleted', "Campanha {$campaign->name} excluída.");
        $campaign->delete();
    }

    /** Conclui a campanha quando não há mais mensagens pendentes. */
    public function finishIfDone(?MarketingCampaign $campaign): void
    {
        if ($campaign?->status !== MarketingCampaign::SENDING) {
            return;
        }

        if (! $campaign->messages()->where('status', MarketingMessage::PENDING)->exists()) {
            $campaign->forceFill(['status' => MarketingCampaign::FINISHED, 'finished_at' => now()])->save();
        }
    }

    /** @param list<string> $from */
    private function transition(MarketingCampaign $campaign, array $from, string $to, string $verb): void
    {
        $updated = MarketingCampaign::whereKey($campaign->id)->whereIn('status', $from)
            ->update(['status' => $to, 'updated_at' => now()] + ($to === MarketingCampaign::CANCELLED ? ['finished_at' => now()] : []));

        if ($updated === 0) {
            throw new BusinessRuleException('A situação da campanha mudou. Atualize a página.');
        }

        $this->audit->record('marketing.campaign_'.$verb, "Campanha {$campaign->name} {$verb}.", $campaign);
    }
}
