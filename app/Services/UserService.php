<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Regras de cadastro, bloqueio e concessão de acesso dos usuários.
 */
class UserService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly UserSessionService $sessions,
    ) {}

    /**
     * @param  array{name: string, email: string, phone: ?string, password: string, roles: list<string>, permissions: list<string>}  $data
     */
    public function create(User $actor, array $data): User
    {
        $this->assertCanGrant($actor, $data['roles'], $data['permissions']);

        return DB::transaction(function () use ($data) {
            $user = new User([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => $data['password'],
            ]);
            // A senha inicial é conhecida por quem cadastrou, então deve ser trocada.
            $user->forceFill(['must_change_password' => true])->save();

            $user->syncRoles($data['roles']);
            $user->syncPermissions($data['permissions']);

            $this->audit->record('user.created', "Usuário {$user->name} cadastrado.", $user, [
                'email' => $user->email,
                'perfis' => $data['roles'],
                'permissoes_adicionais' => $data['permissions'],
            ]);

            return $user;
        });
    }

    /**
     * @param  array{name: string, email: string, phone: ?string, password: ?string, roles: list<string>, permissions: list<string>}  $data
     */
    public function update(User $actor, User $target, array $data): User
    {
        $this->assertCanManage($actor, $target);

        $currentRoles = $target->getRoleNames()->all();
        $currentPermissions = $target->getDirectPermissions()->pluck('name')->all();
        $accessChanged = array_diff($currentRoles, $data['roles']) !== []
            || array_diff($data['roles'], $currentRoles) !== []
            || array_diff($currentPermissions, $data['permissions']) !== []
            || array_diff($data['permissions'], $currentPermissions) !== [];

        if ($accessChanged && $actor->is($target)) {
            throw new BusinessRuleException('Você não pode alterar os próprios perfis e permissões.');
        }

        $this->assertCanGrant(
            $actor,
            array_values(array_diff($data['roles'], $currentRoles)),
            array_values(array_diff($data['permissions'], $currentPermissions)),
        );

        return DB::transaction(function () use ($target, $data, $currentRoles, $currentPermissions) {
            if (in_array(PermissionCatalog::ADMIN_ROLE, $currentRoles, true)
                && ! in_array(PermissionCatalog::ADMIN_ROLE, $data['roles'], true)) {
                $this->assertAnotherActiveAdminExists($target);
            }

            $target->fill([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
            ]);

            $passwordReset = filled($data['password']);

            if ($passwordReset) {
                $target->forceFill([
                    'password' => $data['password'],
                    'must_change_password' => true,
                ]);
            }

            $changedFields = array_keys($target->getDirty());
            $target->save();

            $target->syncRoles($data['roles']);
            $target->syncPermissions($data['permissions']);

            if ($passwordReset) {
                $this->sessions->revokeAllFor($target->id);
            }

            $this->audit->record('user.updated', "Usuário {$target->name} alterado.", $target, [
                // Registra apenas os nomes dos campos, nunca o valor da senha.
                'campos_alterados' => $changedFields,
                'perfis_antes' => $currentRoles,
                'perfis_depois' => $data['roles'],
                'permissoes_antes' => $currentPermissions,
                'permissoes_depois' => $data['permissions'],
            ]);

            return $target;
        });
    }

    public function block(User $actor, User $target, string $reason): void
    {
        $this->assertCanManage($actor, $target);

        if ($actor->is($target)) {
            throw new BusinessRuleException('Você não pode bloquear o próprio usuário.');
        }

        if ($target->is_blocked) {
            throw new BusinessRuleException('Este usuário já está bloqueado.');
        }

        DB::transaction(function () use ($actor, $target, $reason) {
            if ($target->isAdministrator()) {
                $this->assertAnotherActiveAdminExists($target);
            }

            $target->forceFill([
                'is_blocked' => true,
                'blocked_at' => now(),
                'blocked_reason' => $reason,
                'blocked_by' => $actor->id,
            ])->save();

            $this->sessions->revokeAllFor($target->id);

            $this->audit->record('user.blocked', "Usuário {$target->name} bloqueado.", $target, [
                'motivo' => $reason,
            ]);
        });
    }

    public function unblock(User $actor, User $target): void
    {
        $this->assertCanManage($actor, $target);

        if (! $target->is_blocked) {
            throw new BusinessRuleException('Este usuário não está bloqueado.');
        }

        DB::transaction(function () use ($target) {
            $target->forceFill([
                'is_blocked' => false,
                'blocked_at' => null,
                'blocked_reason' => null,
                'blocked_by' => null,
            ])->save();

            $this->audit->record('user.unblocked', "Usuário {$target->name} desbloqueado.", $target);
        });
    }

    private function assertCanManage(User $actor, User $target): void
    {
        if ($target->isDemoAccount()) {
            throw new BusinessRuleException('Na demonstração, os usuários de teste não podem ser alterados. Cadastre um usuário novo para testar.');
        }

        if (! $target->canBeManagedBy($actor)) {
            throw new BusinessRuleException('Somente um administrador pode gerenciar outro administrador.');
        }
    }

    /**
     * Impede escalada de privilégios: ninguém concede um acesso que não possui.
     *
     * @param  list<string>  $roles
     * @param  list<string>  $permissions
     */
    private function assertCanGrant(User $actor, array $roles, array $permissions): void
    {
        if ($actor->isAdministrator()) {
            return;
        }

        if (in_array(PermissionCatalog::ADMIN_ROLE, $roles, true)) {
            throw new BusinessRuleException('Somente um administrador pode conceder o perfil Administrador.');
        }

        $requested = collect($permissions);

        if ($roles !== []) {
            $requested = $requested->merge(
                Role::whereIn('name', $roles)->with('permissions')->get()->flatMap->permissions->pluck('name')
            );
        }

        $missing = $requested->unique()->diff($actor->getAllPermissions()->pluck('name'));

        if ($missing->isNotEmpty()) {
            throw new BusinessRuleException('Você não pode conceder permissões que não possui: '.$missing->implode(', ').'.');
        }
    }

    /** Deve rodar dentro de transação: o lock serializa remoções simultâneas. */
    private function assertAnotherActiveAdminExists(User $target): void
    {
        $others = User::role(PermissionCatalog::ADMIN_ROLE)
            ->active()
            ->whereKeyNot($target->id)
            ->lockForUpdate()
            ->count();

        if ($others === 0) {
            throw new BusinessRuleException('O sistema precisa manter ao menos um administrador ativo.');
        }
    }
}
