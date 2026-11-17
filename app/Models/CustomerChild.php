<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerChild extends Model
{
    protected $table = 'customer_children';

    protected $fillable = ['customer_id', 'name', 'birth_date', 'clothing_size'];

    protected function casts(): array
    {
        return ['birth_date' => 'date'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function ageLabel(): string
    {
        if (! $this->birth_date) {
            return '-';
        }

        $months = (int) floor($this->birth_date->diffInMonths(now()));

        if ($months < 24) {
            return $months === 1 ? '1 mês' : "{$months} meses";
        }

        return ((int) floor($months / 12)).' anos';
    }
}
