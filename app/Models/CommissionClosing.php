<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Comissão de um vendedor em um mês já fechado: valores congelados no fechamento. */
class CommissionClosing extends Model
{
    protected $fillable = [
        'period', 'user_id', 'sales_total', 'returns_total', 'base_total', 'percent', 'amount', 'payable_id', 'closed_by',
    ];

    protected function casts(): array
    {
        return [
            'period' => 'date',
            'sales_total' => 'decimal:2',
            'returns_total' => 'decimal:2',
            'base_total' => 'decimal:2',
            'percent' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payable(): BelongsTo
    {
        return $this->belongsTo(Payable::class);
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
