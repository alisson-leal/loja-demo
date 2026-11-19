<?php

namespace App\Models;

use App\Models\Concerns\Immutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Histórico de uma unidade com número de série: entrada, venda, devolução, transferência. */
class SerialNumberEvent extends Model
{
    use Immutable;

    public const UPDATED_AT = null;

    protected $fillable = ['serial_number_id', 'type', 'description', 'store_id', 'reference_type', 'reference_id', 'user_id'];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
