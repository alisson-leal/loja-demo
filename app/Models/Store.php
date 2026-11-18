<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Loja (filial). A loja principal guarda os saldos antigos e recebe o que não tiver loja. */
class Store extends Model
{
    protected $fillable = ['name', 'code', 'is_active', 'phone', 'address', 'city'];

    protected function casts(): array
    {
        return ['is_main' => 'boolean', 'is_active' => 'boolean'];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByDesc('is_main')->orderBy('name');
    }

    public static function main(): self
    {
        return self::where('is_main', true)->firstOrFail();
    }
}
