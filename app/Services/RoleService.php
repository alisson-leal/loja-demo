<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class RoleService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param list<string> $permissions */
    public function create(User $actor, string $name, array $permissions): Role
    {
        $this->assertCanGrant($actor, $permissions);

        return DB::transaction(function () use ($name, $permissions) {
            $role = Role::create(['name' => $name, 'guard_name' => 'web']);
            $role->syncPermissions($permissions);

            $this->audit->record('role.created', "Perfil {$name} cadastrado.", $role, [
                'permissoes' => $permissions,
            ]);

            return $role;
        });
    }

    /** @param list<string> $permissions */
    public function update(User $actor, Role $role, string $name, array $permissions): Role
    {
        $this->assertNotAdministrator($role);

        if (PermissionCatalog::isDefaultRole($role->name) && $name !== $role->name) {
            throw new BusinessRuleException('Os perfis padrão não podem ser renomeados.');
        }

        $before = $role->permissions->pluck('name')->all();
        $this->assertCanGrant($actor, array_values(array_diff($permissions, $before)));

        return DB::transaction(function () use ($role, $name, $permissions, $before) {
            $oldName = $role->name;
            $role->update(['name' => $name]);
            $role->syncPermissions($permissions);

            $this->audit->record('role.updated', "Perfil {$name} alterado.", $role, [
                'nome_anterior' => $oldName,
                'permissoes_antes' => $before,
                'permissoes_depois' => $permissions,
            ]);

            return $role;
        });
    }

    public function delete(Role $role): void
    {
        $this->assertNotAdministrator($role);

        if (PermissionCatalog::isDefaultRole($role->name)) {
            throw new BusinessRuleException('Os perfis padrão não podem ser excluídos.');
        }

        DB::transaction(function () use ($role) {
            if ($role->users()->lockForUpdate()->exists()) {
                throw new BusinessRuleException('Este perfil está atribuído a usuários e não pode ser excluído.');
            }

            $this->audit->record('role.deleted', "Perfil {$role->name} excluído.", null, [
                'permissoes' => $role->permissions->pluck('name')->all(),
            ]);

            $role->delete();
        });
    }

    private function assertNotAdministrator(Role $role): void
    {
        if ($role->name === PermissionCatalog::ADMIN_ROLE) {
            throw new BusinessRuleException('O perfil Administrador não pode ser alterado.');
        }
    }

    /** @param list<string> $permissions */
    private function assertCanGrant(User $actor, array $permissions): void
    {
        if ($actor->isAdministrator()) {
            return;
        }

        $missing = collect($permissions)->diff($actor->getAllPermissions()->pluck('name'));

        if ($missing->isNotEmpty()) {
            throw new BusinessRuleException('Você não pode conceder permissões que não possui: '.$missing->implode(', ').'.');
        }
    }
}
