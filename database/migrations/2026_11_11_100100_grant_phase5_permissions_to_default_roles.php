<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Concede uma única vez as permissões da Fase 5 aos perfis padrão existentes.
 */
return new class extends Migration
{
    private const GRANTS = [
        'Gerente' => ['marketing.ver', 'marketing.campanhas', 'marketing.disparar', 'marketing.whatsapp', 'marketing.automacoes'],
        'Vendedor' => ['marketing.whatsapp'],
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::GRANTS as $roleName => $permissions) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();

            foreach ($role ? $permissions : [] as $permission) {
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
