<?php

namespace App\Support;

use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Loja em que o usuário está trabalhando agora (escolhida no topo da tela).
 * Sem sessão (terminal, agendador, testes) vale a loja principal.
 */
final class CurrentStore
{
    private const SESSION_KEY = 'current_store_id';

    private static ?int $override = null;

    public static function id(): int
    {
        if (self::$override !== null) {
            return self::$override;
        }

        $user = Auth::user();

        if (! $user || ! app()->bound('session') || ! request()->hasSession()) {
            return self::mainId();
        }

        $allowed = self::allowedIds($user);
        $chosen = (int) session(self::SESSION_KEY);

        return in_array($chosen, $allowed, true) ? $chosen : ($allowed[0] ?? self::mainId());
    }

    public static function store(): Store
    {
        return Store::findOrFail(self::id());
    }

    public static function switch(int $storeId): bool
    {
        $user = Auth::user();

        if (! $user || ! in_array($storeId, self::allowedIds($user), true)) {
            return false;
        }

        session([self::SESSION_KEY => $storeId]);

        return true;
    }

    /** Lojas ativas em que o usuário trabalha. Administrador e quem vê todas: todas as ativas. */
    public static function allowed(User $user): Collection
    {
        $active = Store::active()->ordered();

        if ($user->isAdministrator() || $user->can('lojas.ver_todas')) {
            return $active->get();
        }

        $linked = (clone $active)->whereHas('users', fn ($q) => $q->whereKey($user->id))->get();

        // Usuário sem loja definida trabalha na principal (instalação com uma loja só).
        return $linked->isNotEmpty() ? $linked : $active->where('is_main', true)->get();
    }

    /** @return list<int> */
    public static function allowedIds(User $user): array
    {
        return self::allowed($user)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public static function mainId(): int
    {
        static $cache = [];

        return $cache[spl_object_id(app())] ??= (int) Store::where('is_main', true)->value('id');
    }

    /** Há mais de uma loja ativa? Esconde seletores e colunas de loja quando não há. */
    public static function multiple(): bool
    {
        return Store::active()->count() > 1;
    }

    /** Para o gerador de dados e os testes: força uma loja sem sessão. */
    public static function use(?int $storeId): void
    {
        self::$override = $storeId;
    }
}
