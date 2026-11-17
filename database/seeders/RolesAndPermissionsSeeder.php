<?php

namespace Database\Seeders;

use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Idempotente: pode rodar a cada publicação para incluir permissões novas.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionCatalog::names() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        Role::findOrCreate(PermissionCatalog::ADMIN_ROLE, 'web');

        foreach (PermissionCatalog::defaultRoles() as $name => $permissions) {
            $role = Role::where('name', $name)->where('guard_name', 'web')->first();

            if (! $role) {
                Role::create(['name' => $name, 'guard_name' => 'web'])->syncPermissions($permissions);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
