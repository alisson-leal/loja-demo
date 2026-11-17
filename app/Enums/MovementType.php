<?php

namespace App\Enums;

enum MovementType: string
{
    case Entry = 'entry';
    case Return = 'return';
    case AdjustmentIn = 'adjustment_in';
    case Exit = 'exit';
    case Loss = 'loss';
    case Damage = 'damage';
    case AdjustmentOut = 'adjustment_out';
    case Inventory = 'inventory';
    case Sale = 'sale';
    case SaleCancellation = 'sale_cancel';

    public function label(): string
    {
        return match ($this) {
            self::Entry => 'Entrada',
            self::Return => 'Devolução',
            self::AdjustmentIn => 'Ajuste (acréscimo)',
            self::Exit => 'Saída',
            self::Loss => 'Perda',
            self::Damage => 'Avaria',
            self::AdjustmentOut => 'Ajuste (redução)',
            self::Inventory => 'Inventário',
            self::Sale => 'Venda',
            self::SaleCancellation => 'Cancelamento de venda',
        };
    }

    public function isInbound(): bool
    {
        return in_array($this, [self::Entry, self::Return, self::AdjustmentIn, self::SaleCancellation], true);
    }

    /** Permissão exigida para registrar este tipo pela tela de movimentação. */
    public function permission(): string
    {
        return in_array($this, [self::AdjustmentIn, self::AdjustmentOut], true)
            ? 'estoque.ajustar'
            : 'estoque.movimentar';
    }

    /** @return list<self> */
    public static function manualTypes(): array
    {
        return [self::Exit, self::Return, self::Loss, self::Damage, self::AdjustmentIn, self::AdjustmentOut];
    }
}
