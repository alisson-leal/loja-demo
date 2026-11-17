<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Concede uma única vez as permissões da Fase 4 ao perfil Gerente, se ele existir.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role = Role::where('name', 'Gerente')->where('guard_name', 'web')->first();

        if ($role) {
            foreach (['clientes.ver', 'clientes.editar', 'clientes.exportar'] as $permission) {
                $role->givePermissionTo(Permission::findOrCreate($permission, 'web'));
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Permissões concedidas permanecem: removê-las poderia desfazer ajustes manuais.
    }
};
