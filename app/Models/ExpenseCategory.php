<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseCategory extends Model
{
    /** Compra de mercadorias: sai do caixa, mas o custo entra no resultado pelo CMV. */
    public const MERCHANDISE = 'mercadorias';

    /** Reembolsos de devoluções: já descontados da receita no resultado. */
    public const REFUNDS = 'devolucoes';

    protected $fillable = ['name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function payables(): HasMany
    {
        return $this->hasMany(Payable::class);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('name');
    }

    public static function refunds(): self
    {
        return self::where('system_key', self::REFUNDS)->firstOrFail();
    }
}
