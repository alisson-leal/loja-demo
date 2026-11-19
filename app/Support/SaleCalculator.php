<?php

namespace App\Support;

/**
 * Cálculo de totais em centavos. Usado pela tela do PDV e pela finalização,
 * para que o valor exibido seja sempre o valor registrado.
 */
final class SaleCalculator
{
    /**
     * Produtos por metro ou quilo (unit "m"/"kg") têm a quantidade em milésimos e
     * o preço por metro ou quilo; o valor da linha é arredondado ao centavo.
     *
     * @param  list<array{unit_price: int, quantity: int, discount: int, unit?: string}>  $lines  Valores em centavos.
     * @return array{subtotal: int, discount_total: int, total: int, lines: list<array{gross: int, discount: int, total: int}>}
     */
    public static function calculate(array $lines, int $orderDiscount): array
    {
        $result = [];
        $subtotal = 0;
        $afterLineDiscounts = 0;

        foreach ($lines as $line) {
            $gross = Quantity::amount($line['unit_price'], $line['quantity'], $line['unit'] ?? Quantity::UNIT);
            $discount = max(0, min($line['discount'], $gross));
            $subtotal += $gross;
            $afterLineDiscounts += $gross - $discount;
            $result[] = ['gross' => $gross, 'discount' => $discount, 'total' => $gross - $discount];
        }

        $orderDiscount = max(0, min($orderDiscount, $afterLineDiscounts));
        $remaining = $orderDiscount;
        $lastIndex = array_key_last($result);

        // Rateia o desconto geral entre os itens, para devoluções reembolsarem o valor pago.
        foreach ($result as $index => $line) {
            $share = $index === $lastIndex || $afterLineDiscounts === 0
                ? $remaining
                : min($remaining, (int) round($orderDiscount * $line['total'] / $afterLineDiscounts));

            $share = min($share, $line['total']);
            $result[$index]['discount'] += $share;
            $result[$index]['total'] -= $share;
            $remaining -= $share;
        }

        $total = $afterLineDiscounts - $orderDiscount;

        return [
            'subtotal' => $subtotal,
            'discount_total' => $subtotal - $total,
            'total' => $total,
            'lines' => $result,
        ];
    }
}
