<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalePayment extends Model
{
    protected $fillable = ['sale_id', 'payment_method_id', 'store_credit_id', 'amount', 'received_amount', 'installments'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'received_amount' => 'decimal:2', 'installments' => 'integer'];
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    public function storeCredit(): BelongsTo
    {
        return $this->belongsTo(StoreCredit::class);
    }
}
