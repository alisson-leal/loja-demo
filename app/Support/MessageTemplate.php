<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\CustomerChild;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Personaliza textos de campanhas e automações. Campos desconhecidos ficam
 * como foram escritos, para o erro aparecer na pré-visualização.
 */
final class MessageTemplate
{
    public const FIELDS = [
        '{nome}' => 'Nome completo do cliente',
        '{primeiro_nome}' => 'Primeiro nome do cliente',
        '{filho}' => 'Nome do filho (só na automação de aniversário do filho)',
        '{idade}' => 'Idade que o filho vai completar',
        '{data_aniversario}' => 'Dia e mês do aniversário do filho',
        '{numero_venda}' => 'Número da venda (só no agradecimento pela compra)',
        '{valor_compra}' => 'Valor da compra (só no agradecimento pela compra)',
        '{loja}' => 'Nome da loja',
        '{instagram}' => 'Instagram da loja',
        '{whatsapp_loja}' => 'WhatsApp da loja',
    ];

    public const WHATSAPP_OPT_OUT = 'Para não receber mais mensagens, responda SAIR.';

    /** @param array<string, string> $extra campos adicionais, como {numero_venda} */
    public static function render(string $template, Customer $customer, ?CustomerChild $child = null, ?Carbon $on = null, array $extra = []): string
    {
        $store = config('loja.store');
        $values = [
            '{nome}' => $customer->name,
            '{primeiro_nome}' => $customer->firstName(),
            '{loja}' => $store['name'],
            '{instagram}' => $store['instagram'],
            '{whatsapp_loja}' => $store['whatsapp'],
        ];

        if ($child) {
            $birthday = self::nextBirthday($child->birth_date, $on ?? now());
            $values['{filho}'] = self::firstName($child->name);
            $values['{idade}'] = (string) ($birthday->year - $child->birth_date->year);
            $values['{data_aniversario}'] = $birthday->format('d/m');
        }

        return strtr($template, $extra + $values);
    }

    /** No WhatsApp, toda mensagem termina com a instrução de descadastro. */
    public static function forChannel(string $channel, string $text): string
    {
        return $channel === 'whatsapp' ? rtrim($text)."\n\n".self::WHATSAPP_OPT_OUT : $text;
    }

    /** Próximo aniversário a partir de $from (inclusive). 29/02 vira 28/02 em anos não bissextos. */
    public static function nextBirthday(Carbon $birthDate, Carbon $from): Carbon
    {
        $from = $from->copy()->startOfDay();
        $date = self::birthdayInYear($birthDate, $from->year);

        return $date->lt($from) ? self::birthdayInYear($birthDate, $from->year + 1) : $date;
    }

    public static function birthdayInYear(Carbon $birthDate, int $year): Carbon
    {
        $day = $birthDate->month === 2 && $birthDate->day === 29 && ! Carbon::create($year)->isLeapYear() ? 28 : $birthDate->day;

        return Carbon::create($year, $birthDate->month, $day)->startOfDay();
    }

    private static function firstName(string $name): string
    {
        return Str::of($name)->trim()->before(' ')->toString();
    }
}
