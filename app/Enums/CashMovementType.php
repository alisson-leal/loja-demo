<?php

namespace App\Enums;

enum CashMovementType: string
{
    case Opening = 'opening';
    case Supply = 'supply';
    case Income = 'income';
    case Sale = 'sale';
    case Bleed = 'bleed';
    case Withdrawal = 'withdrawal';
    case Refund = 'refund';

    public function label(): string
    {
        return match ($this) {
            self::Opening => 'Saldo inicial',
            self::Supply => 'Suprimento',
            self::Income => 'Entrada',
            self::Sale => 'Venda em dinheiro',
            self::Bleed => 'Sangria',
            self::Withdrawal => 'Retirada',
            self::Refund => 'Devolução de dinheiro',
        };
    }

    public function isInbound(): bool
    {
        return in_array($this, [self::Opening, self::Supply, self::Income, self::Sale], true);
    }

    /** @return list<self> */
    public static function manualTypes(): array
    {
        return [self::Supply, self::Income, self::Bleed, self::Withdrawal];
    }
}
