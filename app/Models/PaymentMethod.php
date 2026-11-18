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

    /** Crediário próprio da loja: parcelas pagas pelo cliente na loja (carnê). */
    public const CREDIARIO = 'crediario';

    /** Vale-troca: não aparece como botão; só é usado ao informar o código do vale. */
    public const STORE_CREDIT = 'store_credit';

    public const TYPE_LABELS = [
        self::CASH => 'Dinheiro',
        self::PIX => 'Pix',
        self::DEBIT => 'Cartão de débito',
        self::CREDIT => 'Cartão de crédito',
        self::CREDIARIO => 'Crediário',
        self::OTHER => 'Outra',
    ];

    /** Meios aceitos para pagar uma parcela do crediário no caixa. */
    public const CREDIARIO_PAYMENT_TYPES = [self::CASH, self::PIX];

    protected $fillable = [
        'name', 'type', 'is_active', 'position',
        'fee_percent', 'installment_fee_percent', 'settlement_days', 'max_installments',
        'late_fee_percent', 'interest_percent',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'fee_percent' => 'decimal:2',
            'installment_fee_percent' => 'decimal:2',
            'settlement_days' => 'integer',
            'max_installments' => 'integer',
            'late_fee_percent' => 'decimal:2',
            'interest_percent' => 'decimal:2',
        ];
    }

    public function isCash(): bool
    {
        return $this->type === self::CASH;
    }

    public function isCrediario(): bool
    {
        return $this->type === self::CREDIARIO;
    }

    public function allowsInstallments(): bool
    {
        return in_array($this->type, [self::CREDIT, self::CREDIARIO], true) && $this->max_installments > 1;
    }

    /** Taxa cobrada pela maquininha, em percentual, conforme o número de parcelas. */
    public function feeFor(int $installments): float
    {
        if ($this->isCrediario()) {
            return 0.0;
        }

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
