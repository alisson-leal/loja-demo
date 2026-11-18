<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerChild;
use App\Models\MarketingAutomation;
use App\Models\MarketingMessage;
use App\Support\MessageTemplate;
use App\Support\StoreSegment;
use Illuminate\Support\Carbon;

/**
 * Gera as mensagens de aniversário do dia. Pode rodar várias vezes: a chave
 * de duplicidade garante uma mensagem por pessoa, canal e ano.
 */
class BirthdayAutomation
{
    /** @return int mensagens criadas */
    public function run(?Carbon $today = null): int
    {
        $today = ($today ?? now())->copy()->startOfDay();
        $created = 0;

        $birthdays = StoreSegment::childrenEnabled()
            ? [MarketingAutomation::CUSTOMER_BIRTHDAY, MarketingAutomation::CHILD_BIRTHDAY]
            : [MarketingAutomation::CUSTOMER_BIRTHDAY];

        foreach (MarketingAutomation::where('enabled', true)->whereIn('key', $birthdays)->get() as $automation) {
            $target = $today->copy()->addDays($automation->days_before);

            $created += $automation->isForChildren()
                ? $this->forChildren($automation, $target, $today)
                : $this->forCustomers($automation, $target, $today);
        }

        return $created;
    }

    /** Marca como vencidas as mensagens de WhatsApp de automações que não foram enviadas a tempo. */
    public function expireStale(): int
    {
        return MarketingMessage::where('channel', 'whatsapp')
            ->where('status', MarketingMessage::PENDING)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['status' => MarketingMessage::EXPIRED, 'updated_at' => now()]);
    }

    private function forCustomers(MarketingAutomation $automation, Carbon $target, Carbon $today): int
    {
        $created = 0;

        foreach ($this->bornOn(Customer::query(), $target)->get() as $customer) {
            $created += $this->queue($automation, $customer, null, "{$automation->key}:{$target->year}:{$customer->id}", $today);
        }

        return $created;
    }

    private function forChildren(MarketingAutomation $automation, Carbon $target, Carbon $today): int
    {
        $created = 0;

        foreach ($this->bornOn(CustomerChild::with('customer'), $target)->get() as $child) {
            $created += $this->queue($automation, $child->customer, $child, "{$automation->key}:{$target->year}:{$child->id}", $today);
        }

        return $created;
    }

    /** Nascidos no dia/mês de $target; em ano não bissexto, quem nasceu em 29/02 entra no dia 28/02. */
    private function bornOn($query, Carbon $target)
    {
        $leapDay = $target->month === 2 && $target->day === 28 && ! $target->isLeapYear();

        return $query->whereNotNull('birth_date')->where(fn ($q) => $q
            ->where(fn ($d) => $d->whereMonth('birth_date', $target->month)->whereDay('birth_date', $target->day))
            ->when($leapDay, fn ($d) => $d->orWhere(fn ($l) => $l->whereMonth('birth_date', 2)->whereDay('birth_date', 29))));
    }

    private function queue(MarketingAutomation $automation, Customer $customer, ?CustomerChild $child, string $key, Carbon $today): int
    {
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
                'subject' => $channel === 'email' ? MessageTemplate::render($automation->email_subject, $customer, $child, $today) : null,
                'body' => MessageTemplate::forChannel($channel, MessageTemplate::render($template, $customer, $child, $today)),
                'status' => MarketingMessage::PENDING,
                'dedupe_key' => "{$key}:{$channel}",
                'expires_at' => $channel === 'whatsapp'
                    ? $today->copy()->addDays(max(1, (int) config('loja.marketing.whatsapp_expires_days')))->endOfDay()
                    : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $created;
    }
}
