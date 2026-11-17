<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    protected $fillable = [
        'name', 'document', 'contact_name', 'phone', 'whatsapp', 'email',
        'address', 'city', 'state', 'zip_code', 'notes', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(StockEntry::class);
    }
}
