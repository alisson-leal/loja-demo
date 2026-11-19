<?php

namespace Database\Seeders;

use App\Models\VariationAttribute;
use App\Support\StoreSegment;
use Illuminate\Database\Seeder;

/**
 * Atributos iniciais, conforme o segmento da loja (StoreSegment). Os valores só
 * são criados junto com o atributo, para que exclusões feitas pelo painel não
 * sejam desfeitas em publicações futuras.
 */
class VariationAttributesSeeder extends Seeder
{
    public function run(): void
    {
        $position = 0;

        foreach (StoreSegment::variationPreset() as $name => $values) {
            $position++;
            $attribute = VariationAttribute::firstOrCreate(['name' => $name], ['position' => $position]);

            if ($attribute->wasRecentlyCreated) {
                foreach ($values as $index => $value) {
                    $attribute->values()->create(['value' => $value, 'position' => $index + 1]);
                }
            }
        }
    }
}
