<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VariationAttribute extends Model
{
    protected $fillable = ['name', 'position'];

    public function values(): HasMany
    {
        return $this->hasMany(VariationValue::class)->orderBy('position')->orderBy('id');
    }
}
