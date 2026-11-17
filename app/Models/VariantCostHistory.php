<?php

namespace App\Models;

use App\Models\Concerns\Immutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VariantCostHistory extends Model
{
    use Immutable;

    public const UPDATED_AT = null;

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_ENTRY = 'entrada';

    protected $table = 'variant_cost_history';

    protected $fillable = ['product_variant_id', 'previous_cost', 'new_cost', 'source', 'supplier_id', 'user_id'];

    protected function casts(): array
    {
        return [
            'previous_cost' => 'decimal:2',
            'new_cost' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
