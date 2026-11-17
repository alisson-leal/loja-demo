<?php

namespace App\Support;

final class UserAgent
{
    /** Resumo legível, como "Chrome em Windows". */
    public static function describe(?string $agent): string
    {
        if (blank($agent)) {
            return 'Dispositivo desconhecido';
        }

        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') => 'Opera',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Safari/') => 'Safari',
            default => 'Navegador',
        };

        $system = match (true) {
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPhone'), str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Mac OS') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => 'sistema desconhecido',
        };

        return "{$browser} em {$system}";
    }
}
