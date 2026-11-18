<?php

namespace App\Models\Concerns;

use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Registro de uma loja (filial). */
trait BelongsToStore
{
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** Filtra por loja; null mostra todas. */
    public function scopeInStore(Builder $query, ?int $storeId): Builder
    {
        return $storeId === null ? $query : $query->where($this->qualifyColumn('store_id'), $storeId);
    }
}
