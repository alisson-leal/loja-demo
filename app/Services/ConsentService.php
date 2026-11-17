<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerConsent;
use App\Models\MarketingMessage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Único ponto que altera o consentimento de marketing (LGPD). Registra cada
 * mudança no histórico e, na revogação, descarta as mensagens ainda pendentes.
 */
class ConsentService
{
    public const CHANNELS = ['email', 'whatsapp'];

    /** Aplica o consentimento desejado; só grava o que de fato mudou. */
    public function set(Customer $customer, string $channel, bool $granted, string $source): bool
    {
        if (! in_array($channel, self::CHANNELS, true)) {
            throw new InvalidArgumentException("Canal desconhecido: {$channel}");
        }

        if ((bool) $customer->{"{$channel}_opt_in"} === $granted) {
            return false;
        }

        DB::transaction(function () use ($customer, $channel, $granted, $source) {
            $customer->forceFill([
                "{$channel}_opt_in" => $granted,
                "{$channel}_opt_in_at" => now(),
            ])->save();

            CustomerConsent::create([
                'customer_id' => $customer->id,
                'channel' => $channel,
                'granted' => $granted,
                'source' => $source,
                'user_id' => Auth::id(),
                'ip_address' => request()?->ip(),
            ]);

            if (! $granted) {
                MarketingMessage::where('customer_id', $customer->id)
                    ->where('channel', $channel)
                    ->where('status', MarketingMessage::PENDING)
                    ->update(['status' => MarketingMessage::SKIPPED, 'error' => 'Consentimento revogado.']);
            }
        });

        return true;
    }

    /** @param array<string, bool> $choices canal => consentiu */
    public function setMany(Customer $customer, array $choices, string $source): void
    {
        foreach ($choices as $channel => $granted) {
            $this->set($customer, $channel, $granted, $source);
        }
    }
}
