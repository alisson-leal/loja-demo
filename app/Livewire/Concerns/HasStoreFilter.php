<?php

namespace App\Livewire\Concerns;

use App\Support\CurrentStore;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;

/**
 * Filtro de loja dos relatórios. Quem vê todas as lojas escolhe uma loja ou a
 * rede inteira; os demais veem sempre a loja em que estão trabalhando.
 */
trait HasStoreFilter
{
    /** Vazio: rede inteira (para quem pode) ou loja atual; número: uma loja. */
    #[Url(as: 'loja')]
    public string $storeFilter = '';

    protected function storeFilterId(): ?int
    {
        $user = Auth::user();

        if (! CurrentStore::multiple()) {
            return null;
        }

        if (! $user->can('lojas.ver_todas')) {
            return CurrentStore::id();
        }

        return ctype_digit($this->storeFilter) && in_array((int) $this->storeFilter, CurrentStore::allowedIds($user), true)
            ? (int) $this->storeFilter
            : null;
    }

    /** Opções do seletor; vazio quando não há o que escolher. */
    protected function storeFilterOptions(): Collection
    {
        return CurrentStore::multiple() && Auth::user()->can('lojas.ver_todas')
            ? CurrentStore::allowed(Auth::user())
            : collect();
    }
}
