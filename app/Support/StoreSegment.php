<?php

namespace App\Support;

/**
 * Segmento da loja (config loja.segment). Lojas infantis e mistas usam os
 * recursos de filhos dos clientes; lojas adultas não os veem.
 */
final class StoreSegment
{
    public const LABELS = [
        'infantil' => 'Moda infantil',
        'adulto' => 'Moda adulta',
        'misto' => 'Moda adulta e infantil',
    ];

    public static function current(): string
    {
        $segment = (string) config('loja.segment');

        return array_key_exists($segment, self::LABELS) ? $segment : 'infantil';
    }

    public static function childrenEnabled(): bool
    {
        return self::current() !== 'adulto';
    }
}
