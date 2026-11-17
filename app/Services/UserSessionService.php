<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UserSessionService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** Sessões autenticadas dentro do tempo de vida configurado. */
    public function activeQuery(): Builder
    {
        return DB::table('sessions')
            ->whereNotNull('user_id')
            ->where('last_activity', '>=', now()->subMinutes((int) config('session.lifetime'))->getTimestamp());
    }

    /** Lista sessões ativas, opcionalmente de um único usuário. */
    public function listActive(?int $userId = null): Collection
    {
        return $this->activeQuery()
            ->join('users', 'users.id', '=', 'sessions.user_id')
            ->when($userId, fn (Builder $query) => $query->where('sessions.user_id', $userId))
            ->orderByDesc('sessions.last_activity')
            ->get([
                'sessions.id', 'sessions.user_id', 'sessions.ip_address',
                'sessions.user_agent', 'sessions.last_activity', 'users.name as user_name',
            ]);
    }

    public function revoke(User $actor, string $sessionId, ?string $currentSessionId): void
    {
        if ($sessionId === $currentSessionId) {
            throw new BusinessRuleException('Para encerrar a sessão atual, use a opção Sair.');
        }

        $session = DB::table('sessions')->where('id', $sessionId)->first();

        if (! $session || ! $session->user_id) {
            throw new BusinessRuleException('Esta sessão já foi encerrada.');
        }

        $owner = User::findOrFail($session->user_id);

        if ($owner->id !== $actor->id
            && (! $actor->can('sessoes.encerrar') || ! $owner->canBeManagedBy($actor))) {
            throw new BusinessRuleException('Você não tem permissão para encerrar esta sessão.');
        }

        DB::table('sessions')->where('id', $sessionId)->delete();

        $this->audit->record('session.revoked', "Sessão de {$owner->name} encerrada.", $owner, [
            'ip_da_sessao' => $session->ip_address,
        ]);
    }

    public function revokeAllFor(int $userId, ?string $exceptSessionId = null): int
    {
        return DB::table('sessions')
            ->where('user_id', $userId)
            ->when($exceptSessionId, fn (Builder $query) => $query->where('id', '!=', $exceptSessionId))
            ->delete();
    }
}
