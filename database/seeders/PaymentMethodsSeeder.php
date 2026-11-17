<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

/**
 * Formas iniciais. Só cria as que faltam, preservando ajustes feitos pelo painel.
 */
class PaymentMethodsSeeder extends Seeder
{
    private const METHODS = [
        ['Dinheiro', PaymentMethod::CASH],
        ['Pix', PaymentMethod::PIX],
        ['Cartão de débito', PaymentMethod::DEBIT],
        ['Cartão de crédito', PaymentMethod::CREDIT],
        ['Vale-troca', PaymentMethod::STORE_CREDIT],
    ];

    public function run(): void
    {
        foreach (self::METHODS as $position => [$name, $type]) {
            if (! PaymentMethod::where('type', $type)->exists()) {
                PaymentMethod::create(['name' => $name, 'type' => $type, 'position' => $position + 1]);
            }
        }
    }
}
