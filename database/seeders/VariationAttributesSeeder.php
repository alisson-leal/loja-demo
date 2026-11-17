<?php

namespace Database\Seeders;

use App\Models\VariationAttribute;
use Illuminate\Database\Seeder;

/**
 * Atributos iniciais. Os valores só são criados junto com o atributo, para que
 * exclusões feitas pelo painel não sejam desfeitas em publicações futuras.
 */
class VariationAttributesSeeder extends Seeder
{
    private const ATTRIBUTES = [
        'Tamanho' => ['RN', 'P', 'M', 'G', 'GG', '1', '2', '3', '4', '6', '8', '10', '12', '14'],
        'Cor' => ['Rosa', 'Azul', 'Branco', 'Amarelo', 'Vermelho', 'Verde'],
        'Estampa' => [],
        'Modelo' => [],
        'Numeração' => [],
    ];

    public function run(): void
    {
        $position = 0;

        foreach (self::ATTRIBUTES as $name => $values) {
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
