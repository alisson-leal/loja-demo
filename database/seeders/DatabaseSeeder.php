<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            VariationAttributesSeeder::class,
            PaymentMethodsSeeder::class,
        ]);

        // Na demonstração os usuários e os dados fictícios vêm do DemoDataSeeder.
        $this->call(config('loja.demo.enabled') ? DemoDataSeeder::class : AdminUserSeeder::class);
    }
}
