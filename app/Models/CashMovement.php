<?php

namespace App\Models;

use App\Enums\CashMovementType;
use App\Models\Concerns\Immutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashMovement extends Model
{
    use Immutable;

    public const UPDATED_AT = null;

    protected $fillable = ['cash_session_id', 'type', 'amount', 'reason', 'reference_type', 'reference_id', 'user_id'];

    protected function casts(): array
    {
        return [
            'type' => CashMovementType::class,
            'amount' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
