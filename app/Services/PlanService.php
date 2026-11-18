<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Store;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Database\Eloquent\Builder;

/**
 * Limites do plano contratado com a DevZero1 (config loja.plan, vinda do .env).
 * Usuários: cota total = usuários por loja x lojas ativas. Administradores e
 * usuários bloqueados não contam.
 */
class PlanService
{
    /** @return array{per_store: int, max: ?int, used: int, available: ?int} */
    public function users(): array
    {
        $perStore = (int) config('loja.plan.users_per_store');
        $max = $perStore > 0 ? $perStore * Store::active()->count() : null;
        $used = $this->countedUsers()->count();

        return [
            'per_store' => $perStore,
            'max' => $max,
            'used' => $used,
            'available' => $max === null ? null : max(0, $max - $used),
        ];
    }

    /**
     * Confere se cabe mais um usuário ativo que conta no plano. Deve rodar
     * dentro de transação: o lock evita dois cadastros passarem do limite juntos.
     */
    public function assertCanAddUser(?User $ignore = null): void
    {
        $perStore = (int) config('loja.plan.users_per_store');

        if ($perStore === 0) {
            return;
        }

        $max = $perStore * Store::active()->count();
        $used = $this->countedUsers()
            ->when($ignore, fn (Builder $query) => $query->whereKeyNot($ignore->id))
            ->lockForUpdate()
            ->count();

        if ($used >= $max) {
            throw new BusinessRuleException("Seu plano permite {$max} usuários ativos ({$perStore} por loja; administradores não contam). "
                .'Bloqueie um usuário que não usa mais o sistema ou fale com a DevZero1 sobre ampliar o plano.');
        }
    }

    /** Usuários ativos que contam no plano. */
    private function countedUsers(): Builder
    {
        return User::active()->whereDoesntHave('roles', fn (Builder $role) => $role->where('name', PermissionCatalog::ADMIN_ROLE));
    }
}
