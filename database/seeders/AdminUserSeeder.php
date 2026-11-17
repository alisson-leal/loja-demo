<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Cria o primeiro administrador. Não faz nada se já existir algum.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        if (User::role(PermissionCatalog::ADMIN_ROLE)->exists()) {
            return;
        }

        $config = config('loja.admin');

        if (blank($config['email'])) {
            throw new RuntimeException('Defina ADMIN_EMAIL no arquivo .env antes de rodar o seeder.');
        }

        $password = $config['password'] ?: Str::password(16, symbols: false);

        $admin = new User([
            'name' => $config['name'],
            'email' => $config['email'],
            'password' => $password,
        ]);
        $admin->forceFill(['must_change_password' => true])->save();
        $admin->assignRole(PermissionCatalog::ADMIN_ROLE);

        if (! $config['password']) {
            $this->command?->warn("Administrador criado: {$config['email']}");
            $this->command?->warn("Senha provisória (exibida só agora): {$password}");
        }
    }
}
