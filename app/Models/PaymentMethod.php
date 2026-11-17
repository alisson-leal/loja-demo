<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    public const CASH = 'cash';

    public const PIX = 'pix';

    public const DEBIT = 'debit';

    public const CREDIT = 'credit';

    public const OTHER = 'other';

    /** Vale-troca: não aparece como botão; só é usado ao informar o código do vale. */
    public const STORE_CREDIT = 'store_credit';

    public const TYPE_LABELS = [
        self::CASH => 'Dinheiro',
        self::PIX => 'Pix',
        self::DEBIT => 'Cartão de débito',
        self::CREDIT => 'Cartão de crédito',
        self::OTHER => 'Outra',
    ];

    protected $fillable = [
        'name', 'type', 'is_active', 'position',
        'fee_percent', 'installment_fee_percent', 'settlement_days', 'max_installments',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'fee_percent' => 'decimal:2',
            'installment_fee_percent' => 'decimal:2',
            'settlement_days' => 'integer',
            'max_installments' => 'integer',
        ];
    }

    public function isCash(): bool
    {
        return $this->type === self::CASH;
    }

    public function allowsInstallments(): bool
    {
        return $this->type === self::CREDIT && $this->max_installments > 1;
    }

    /** Taxa cobrada pela maquininha, em percentual, conforme o número de parcelas. */
    public function feeFor(int $installments): float
    {
        return (float) ($installments > 1 && $this->installment_fee_percent !== null
            ? $this->installment_fee_percent
            : $this->fee_percent);
    }

    public function scopeSelectable(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where('type', '!=', self::STORE_CREDIT)
            ->orderBy('position')
            ->orderBy('id');
    }
}
