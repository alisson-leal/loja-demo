<?php

namespace App\Livewire\Users;

use App\Exceptions\BusinessRuleException;
use App\Models\User;
use App\Services\PlanService;
use App\Services\UserService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Usuários')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'busca')]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Locked]
    public ?int $blockingId = null;

    public string $blockReason = '';

    public function mount(): void
    {
        Gate::authorize('usuarios.ver');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function askBlock(int $id): void
    {
        Gate::authorize('usuarios.bloquear');
        $this->blockingId = $id;
        $this->blockReason = '';
        $this->resetValidation();
    }

    public function cancelBlock(): void
    {
        $this->blockingId = null;
        $this->blockReason = '';
    }

    public function confirmBlock(UserService $users): void
    {
        Gate::authorize('usuarios.bloquear');

        $this->validate(['blockReason' => ['required', 'string', 'min:3', 'max:255']], [], ['blockReason' => 'motivo']);

        $target = User::findOrFail($this->blockingId);

        $this->attempt(fn () => $users->block(Auth::user(), $target, $this->blockReason), 'Usuário bloqueado e sessões encerradas.');
        $this->cancelBlock();
    }

    public function unblock(int $id, UserService $users): void
    {
        Gate::authorize('usuarios.bloquear');

        $this->attempt(fn () => $users->unblock(Auth::user(), User::findOrFail($id)), 'Usuário desbloqueado.');
    }

    private function attempt(callable $action, string $successMessage): void
    {
        try {
            $action();
            session()->now('success', $successMessage);
        } catch (BusinessRuleException $exception) {
            session()->now('error', $exception->getMessage());
        }
    }

    public function render(): View
    {
        $term = trim($this->search);

        $users = User::with('roles')
            ->when($term !== '', fn (Builder $query) => $query->where(
                fn (Builder $inner) => $inner->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")
            ))
            ->when($this->status === 'ativos', fn (Builder $query) => $query->where('is_blocked', false))
            ->when($this->status === 'bloqueados', fn (Builder $query) => $query->where('is_blocked', true))
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.users.index', [
            'users' => $users,
            'blocking' => $this->blockingId ? User::find($this->blockingId) : null,
            'userPlan' => app(PlanService::class)->users(),
        ]);
    }
}
