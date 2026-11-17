<?php

namespace App\Livewire\Cash;

use App\Models\CashSession;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Histórico de caixas')]
class History extends Component
{
    use WithPagination;

    #[Url(as: 'operador')]
    public string $userId = '';

    public function mount(): void
    {
        abort_unless(Gate::any(['caixa.operar', 'caixa.gerir']), 403);
    }

    public function updatedUserId(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $user = Auth::user();
        $seesAll = $user->can('caixa.gerir');

        return view('livewire.cash.history', [
            'seesAll' => $seesAll,
            'sessions' => CashSession::with('user:id,name')
                ->when(! $seesAll, fn ($query) => $query->where('user_id', $user->id))
                ->when($seesAll && ctype_digit($this->userId), fn ($query) => $query->where('user_id', (int) $this->userId))
                ->latest('id')
                ->paginate(20),
            'users' => $seesAll ? User::orderBy('name')->get(['id', 'name']) : collect(),
        ]);
    }
}
