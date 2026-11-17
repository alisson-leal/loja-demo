<?php

namespace App\Livewire\Users;

use App\Exceptions\BusinessRuleException;
use App\Models\User;
use App\Services\UserService;
use App\Support\PermissionCatalog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Spatie\Permission\Models\Role;

class Form extends Component
{
    #[Locked]
    public ?int $userId = null;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $password = '';

    public string $password_confirmation = '';

    /** @var list<string> */
    public array $roles = [];

    /** @var list<string> */
    public array $permissions = [];

    public function mount($user = null): void
    {
        if ($user === null) {
            Gate::authorize('usuarios.criar');

            return;
        }

        Gate::authorize('usuarios.editar');

        $target = User::findOrFail($user);
        abort_unless($target->canBeManagedBy(Auth::user()), 403);

        $this->userId = $target->id;
        $this->name = $target->name;
        $this->email = $target->email;
        $this->phone = (string) $target->phone;
        $this->roles = $target->getRoleNames()->all();
        $this->permissions = $target->getDirectPermissions()->pluck('name')->all();
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($this->userId)],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => [$this->userId ? 'nullable' : 'required', 'confirmed', Password::defaults()],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in(Role::pluck('name')->all())],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(PermissionCatalog::names())],
        ];
    }

    protected function validationAttributes(): array
    {
        return ['name' => 'nome', 'phone' => 'telefone', 'password' => 'senha', 'roles' => 'perfil'];
    }

    public function save(UserService $users): void
    {
        Gate::authorize($this->userId ? 'usuarios.editar' : 'usuarios.criar');

        $this->name = trim($this->name);
        $this->email = Str::lower(trim($this->email));
        $validated = $this->validate();

        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?: null,
            'password' => $validated['password'] ?: null,
            'roles' => array_values($validated['roles']),
            'permissions' => array_values($validated['permissions'] ?? []),
        ];

        try {
            if ($this->userId) {
                $users->update(Auth::user(), User::findOrFail($this->userId), $data);
                session()->flash('success', 'Usuário atualizado.');
            } else {
                $users->create(Auth::user(), $data);
                session()->flash('success', 'Usuário cadastrado. A senha provisória deverá ser trocada no primeiro acesso.');
            }
        } catch (BusinessRuleException $exception) {
            session()->now('error', $exception->getMessage());

            return;
        }

        $this->redirectRoute('users.index');
    }

    public function render(): View
    {
        $actor = Auth::user();
        $isAdmin = $actor->isAdministrator();

        return view('livewire.users.form', [
            'availableRoles' => Role::orderBy('name')->pluck('name')
                ->reject(fn (string $name) => ! $isAdmin && $name === PermissionCatalog::ADMIN_ROLE),
            'catalog' => PermissionCatalog::modules(),
            'grantable' => $isAdmin ? null : $actor->getAllPermissions()->pluck('name')->all(),
            'isSelf' => $this->userId === $actor->id,
        ])->title($this->userId ? 'Editar usuário' : 'Novo usuário');
    }
}
