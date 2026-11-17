<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class VariationValue extends Model
{
    protected $fillable = ['variation_attribute_id', 'value', 'position'];

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(VariationAttribute::class, 'variation_attribute_id');
    }

    public function variants(): BelongsToMany
    {
        return $this->belongsToMany(ProductVariant::class, 'product_variant_value');
    }
}
