<?php

namespace App\Support;

final class Money
{
    public static function format(float|string|null $value): string
    {
        return 'R$ '.number_format((float) $value, 2, ',', '.');
    }

    /** Margem sobre o preço de venda, em percentual. Nula quando não há preço. */
    public static function margin(float|string|null $cost, float|string|null $price): ?float
    {
        $price = (float) $price;

        return $price > 0 ? round(($price - (float) $cost) / $price * 100, 1) : null;
    }

    public static function formatMargin(float|string|null $cost, float|string|null $price): string
    {
        $margin = self::margin($cost, $price);

        return $margin === null ? '-' : number_format($margin, 1, ',', '.').'%';
    }

    /** Valor em centavos, para cálculos sem erro de arredondamento. Entrada inválida vale zero. */
    public static function toCents(float|int|string|null $value): int
    {
        return is_numeric($value) ? (int) round((float) $value * 100) : 0;
    }

    public static function fromCents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    public static function equals(float|string|null $a, float|string|null $b): bool
    {
        return abs((float) $a - (float) $b) < 0.005;
    }
}
