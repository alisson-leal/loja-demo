<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Concede uma única vez as permissões da Fase 3 aos perfis que já existem,
 * preservando ajustes feitos depois pelo painel.
 */
return new class extends Migration
{
    private const GRANTS = [
        'Gerente' => [
            'pdv.vender', 'pdv.desconto', 'pdv.desconto_livre', 'caixa.operar', 'caixa.gerir',
            'vendas.ver', 'vendas.cancelar', 'vendas.devolver', 'clientes.criar',
        ],
        'Vendedor' => ['pdv.vender', 'pdv.desconto', 'caixa.operar', 'clientes.criar'],
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::GRANTS as $roleName => $permissions) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();

            if (! $role) {
                continue;
            }

            foreach ($permissions as $permission) {
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
