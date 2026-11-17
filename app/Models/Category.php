<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = ['parent_id', 'name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('name');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function fullName(): string
    {
        return $this->parent ? "{$this->parent->name} › {$this->name}" : $this->name;
    }

    /** Lista plana, com cada subcategoria logo abaixo da categoria principal. */
    public static function ordered(): Collection
    {
        $roots = self::whereNull('parent_id')->with('children')->orderBy('name')->get();

        return new Collection($roots->flatMap(fn (self $root) => [$root, ...$root->children])->all());
    }

    /** @return list<int> */
    public static function idsIncludingChildren(int $id): array
    {
        return self::where('id', $id)->orWhere('parent_id', $id)->pluck('id')->all();
    }
}
