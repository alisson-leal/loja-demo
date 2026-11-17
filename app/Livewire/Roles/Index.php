<?php

namespace App\Livewire\Roles;

use App\Exceptions\BusinessRuleException;
use App\Services\RoleService;
use App\Support\PermissionCatalog;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Role;

#[Title('Perfis de acesso')]
class Index extends Component
{
    public function mount(): void
    {
        Gate::authorize('perfis.ver');
    }

    public function delete(int $id, RoleService $roles): void
    {
        Gate::authorize('perfis.excluir');

        try {
            $roles->delete(Role::findOrFail($id));
            session()->now('success', 'Perfil excluído.');
        } catch (BusinessRuleException $exception) {
            session()->now('error', $exception->getMessage());
        }
    }

    public function render(): View
    {
        return view('livewire.roles.index', [
            'roles' => Role::withCount(['users', 'permissions'])->orderBy('name')->get(),
            'adminRole' => PermissionCatalog::ADMIN_ROLE,
        ]);
    }
}
