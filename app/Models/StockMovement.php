<?php

namespace App\Models;

use App\Enums\MovementType;
use App\Models\Concerns\BelongsToStore;
use App\Models\Concerns\Immutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockMovement extends Model
{
    use BelongsToStore;
    use Immutable;

    public const UPDATED_AT = null;

    protected $fillable = [
        'store_id', 'product_variant_id', 'type', 'quantity', 'balance_after', 'unit_cost',
        'reason', 'reference_type', 'reference_id', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => MovementType::class,
            'quantity' => 'integer',
            'balance_after' => 'integer',
            'unit_cost' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
