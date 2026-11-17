<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerSegment extends Model
{
    protected $fillable = ['name', 'filters', 'created_by'];

    protected function casts(): array
    {
        return ['filters' => 'array'];
    }
}
