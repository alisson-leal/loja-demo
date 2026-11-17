<?php

namespace App\Support;

/**
 * Links "clique para conversar" (wa.me). A loja não usa a API oficial: o
 * atendente abre a conversa com a mensagem pronta e envia pelo próprio WhatsApp.
 */
final class WhatsApp
{
    /** Número no formato internacional (55 + DDD + número) ou null se inválido. */
    public static function normalize(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        if (strlen($digits) === 10 || strlen($digits) === 11) {
            $digits = "55{$digits}";
        }

        return strlen($digits) >= 12 && strlen($digits) <= 13 ? $digits : null;
    }

    public static function url(?string $phone, string $text): ?string
    {
        $number = self::normalize($phone);

        if ($number && config('loja.demo.enabled')) {
            // Telefones da demonstração são fictícios: o WhatsApp abre para escolher o contato.
            return 'https://wa.me/?text='.rawurlencode($text);
        }

        return $number ? "https://wa.me/{$number}?text=".rawurlencode($text) : null;
    }

    /** Exibe 5586999899079 como (86) 99989-9079. */
    public static function format(?string $phone): string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        $local = str_starts_with($digits, '55') && strlen($digits) >= 12 ? substr($digits, 2) : $digits;

        return match (strlen($local)) {
            11 => sprintf('(%s) %s-%s', substr($local, 0, 2), substr($local, 2, 5), substr($local, 7)),
            10 => sprintf('(%s) %s-%s', substr($local, 0, 2), substr($local, 2, 4), substr($local, 6)),
            default => (string) $phone,
        };
    }
}
