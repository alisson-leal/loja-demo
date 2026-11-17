<?php

namespace App\Livewire\Roles;

use App\Exceptions\BusinessRuleException;
use App\Services\RoleService;
use App\Support\PermissionCatalog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Spatie\Permission\Models\Role;

class Form extends Component
{
    #[Locked]
    public ?int $roleId = null;

    public string $name = '';

    /** @var list<string> */
    public array $permissions = [];

    public function mount($role = null): void
    {
        if ($role === null) {
            Gate::authorize('perfis.criar');

            return;
        }

        Gate::authorize('perfis.editar');

        $target = Role::findOrFail($role);
        abort_if($target->name === PermissionCatalog::ADMIN_ROLE, 403);

        $this->roleId = $target->id;
        $this->name = $target->name;
        $this->permissions = $target->permissions->pluck('name')->all();
    }

    public function save(RoleService $roles): void
    {
        Gate::authorize($this->roleId ? 'perfis.editar' : 'perfis.criar');

        $this->name = trim($this->name);

        $validated = $this->validate([
            'name' => ['required', 'string', 'min:3', 'max:60', Rule::unique('roles', 'name')->ignore($this->roleId)],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(PermissionCatalog::names())],
        ], [], ['name' => 'nome do perfil']);

        $permissions = array_values($validated['permissions'] ?? []);

        try {
            if ($this->roleId) {
                $roles->update(Auth::user(), Role::findOrFail($this->roleId), $validated['name'], $permissions);
                session()->flash('success', 'Perfil atualizado.');
            } else {
                $roles->create(Auth::user(), $validated['name'], $permissions);
                session()->flash('success', 'Perfil cadastrado.');
            }
        } catch (BusinessRuleException $exception) {
            session()->now('error', $exception->getMessage());

            return;
        }

        $this->redirectRoute('roles.index');
    }

    public function render(): View
    {
        $actor = Auth::user();

        return view('livewire.roles.form', [
            'catalog' => PermissionCatalog::modules(),
            'grantable' => $actor->isAdministrator() ? null : $actor->getAllPermissions()->pluck('name')->all(),
            'nameLocked' => $this->roleId && PermissionCatalog::isDefaultRole($this->name),
        ])->title($this->roleId ? 'Editar perfil' : 'Novo perfil');
    }
}
