<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Em instalações existentes, os perfis padrão já foram criados na Fase 1 e o
 * seeder não os altera. Esta migration concede as permissões novas uma única vez,
 * preservando qualquer ajuste feito depois pelo painel.
 */
return new class extends Migration
{
    private const GRANTS = [
        'Gerente' => [
            'produtos.ver', 'produtos.criar', 'produtos.editar', 'produtos.ver_custo',
            'cadastros.ver', 'cadastros.gerir',
            'fornecedores.ver', 'fornecedores.criar', 'fornecedores.editar',
            'estoque.ver', 'estoque.entrada', 'estoque.movimentar', 'estoque.ajustar', 'estoque.inventario',
        ],
        'Vendedor' => ['produtos.ver', 'estoque.ver'],
        'Estoquista' => [
            'produtos.ver', 'produtos.ver_custo', 'cadastros.ver', 'fornecedores.ver',
            'estoque.ver', 'estoque.entrada', 'estoque.movimentar', 'estoque.ajustar', 'estoque.inventario',
        ],
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
