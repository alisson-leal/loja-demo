<?php

namespace App\Support;

/**
 * Quantidades por unidade de venda. Produtos vendidos por metro ou quilo guardam
 * a quantidade em milésimos (milímetros e gramas), sempre como inteiro, como os
 * centavos do dinheiro. Produtos por unidade continuam com a quantidade inteira.
 */
final class Quantity
{
    public const UNIT = 'un';

    public const METER = 'm';

    public const KILO = 'kg';

    /** Milésimos por metro ou quilo. */
    public const SCALE = 1000;

    public const UNITS = [
        self::UNIT => 'Unidade',
        self::METER => 'Metro',
        self::KILO => 'Quilo',
    ];

    /** Rótulo curto do preço: "R$ 12,90/m". */
    public const PER = [self::UNIT => '', self::METER => '/m', self::KILO => '/kg'];

    public static function isFractional(?string $unit): bool
    {
        return $unit === self::METER || $unit === self::KILO;
    }

    public static function scale(?string $unit): int
    {
        return self::isFractional($unit) ? self::SCALE : 1;
    }

    /**
     * Converte o que foi digitado ("1,5", "0.35", "2") para a quantidade guardada.
     * Aceita vírgula ou ponto como separador decimal. Entrada inválida vale zero.
     */
    public static function parse(mixed $input, ?string $unit): int
    {
        $text = str_replace(',', '.', trim((string) $input));

        if (! preg_match('/^\d{1,9}(\.\d*)?$|^\.\d+$/', $text)) {
            return 0;
        }

        if (! self::isFractional($unit)) {
            return str_contains($text, '.') && (float) $text !== floor((float) $text) ? 0 : (int) $text;
        }

        return (int) round((float) $text * self::SCALE);
    }

    /** Quantidade sem formatação de milhar, para preencher campos ("1.5"). */
    public static function toInput(int $quantity, ?string $unit): string
    {
        if (! self::isFractional($unit)) {
            return (string) $quantity;
        }

        return rtrim(rtrim(number_format($quantity / self::SCALE, 3, '.', ''), '0'), '.');
    }

    /** "1,5 m", "0,35 kg" ou "3" (por unidade, sem sufixo). */
    public static function format(int|float|string|null $quantity, ?string $unit, bool $withUnit = true): string
    {
        $quantity = (int) $quantity;

        if (! self::isFractional($unit)) {
            return number_format($quantity, 0, ',', '.');
        }

        $text = rtrim(rtrim(number_format($quantity / self::SCALE, 3, ',', '.'), '0'), ',');

        return $withUnit ? "{$text} {$unit}" : $text;
    }

    /** Valor da quantidade em centavos: preço por unidade × quantidade, arredondado. */
    public static function amount(int $unitCents, int $quantity, ?string $unit): int
    {
        return (int) round($unitCents * $quantity / self::scale($unit));
    }

    /** Expressão SQL da escala para uma coluna de unidade (ex.: sale_items.unit). */
    public static function scaleSql(string $unitColumn): string
    {
        return "(CASE WHEN {$unitColumn} IN ('".self::METER."', '".self::KILO."') THEN ".self::SCALE.' ELSE 1 END)';
    }

    /** Quantidade que conta como "peças": cada linha por metro ou quilo com saldo conta 1. */
    public static function piecesSql(string $quantitySql, string $unitColumn): string
    {
        return "(CASE WHEN {$unitColumn} IN ('".self::METER."', '".self::KILO."') THEN (CASE WHEN {$quantitySql} > 0 THEN 1 ELSE 0 END) ELSE {$quantitySql} END)";
    }
}
