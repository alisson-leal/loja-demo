<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreCredit extends Model
{
    protected $fillable = ['code', 'initial_amount', 'balance', 'customer_id'];

    protected function casts(): array
    {
        return ['initial_amount' => 'decimal:2', 'balance' => 'decimal:2'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
