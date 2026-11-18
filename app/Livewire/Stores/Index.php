<?php

namespace App\Livewire\Stores;

use App\Livewire\Concerns\HandlesBusinessRules;
use App\Models\Store;
use App\Models\User;
use App\Services\StoreService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Lojas')]
class Index extends Component
{
    use HandlesBusinessRules;

    #[Locked]
    public ?int $editingId = null;

    public bool $showForm = false;

    public string $name = '';

    public string $code = '';

    public string $phone = '';

    public string $address = '';

    public string $city = '';

    public bool $isActive = true;

    /** @var list<string> */
    public array $team = [];

    public function mount(): void
    {
        Gate::authorize('lojas.gerir');
    }

    public function create(): void
    {
        $this->reset('editingId', 'name', 'code', 'phone', 'address', 'city', 'isActive', 'team');
        $this->showForm = true;
        $this->resetValidation();
    }

    public function edit(int $id): void
    {
        $store = Store::with('users:id')->findOrFail($id);
        $this->editingId = $store->id;
        $this->name = $store->name;
        $this->code = $store->code;
        $this->phone = (string) $store->phone;
        $this->address = (string) $store->address;
        $this->city = (string) $store->city;
        $this->isActive = $store->is_active;
        $this->team = $store->users->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->showForm = true;
        $this->resetValidation();
    }

    public function save(StoreService $stores): void
    {
        Gate::authorize('lojas.gerir');
        $this->name = trim($this->name);

        $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:80', Rule::unique('stores', 'name')->ignore($this->editingId)],
            'code' => ['required', 'string', 'max:10', 'regex:/^[A-Za-z0-9]+$/', Rule::unique('stores', 'code')->ignore($this->editingId)],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:190'],
            'city' => ['nullable', 'string', 'max:100'],
            'team' => ['array'],
            'team.*' => ['integer', Rule::exists('users', 'id')],
        ], ['code.regex' => 'Use só letras e números no código.'], ['name' => 'nome', 'code' => 'código', 'phone' => 'telefone', 'address' => 'endereço', 'city' => 'cidade']);

        $ok = $this->attempt(function () use ($stores) {
            $store = $stores->save($this->editingId ? Store::findOrFail($this->editingId) : null, [
                'name' => $this->name,
                'code' => $this->code,
                'phone' => trim($this->phone) ?: null,
                'address' => trim($this->address) ?: null,
                'city' => trim($this->city) ?: null,
                'is_active' => $this->isActive,
            ], Auth::user());
            $stores->syncUsers($store, array_map('intval', $this->team), Auth::user());
        }, 'Loja salva.');

        if ($ok) {
            $this->reset('editingId', 'showForm', 'name', 'code', 'phone', 'address', 'city', 'isActive', 'team');
        }
    }

    public function render(StoreService $stores): View
    {
        return view('livewire.stores.index', [
            'stores' => Store::ordered()->withCount('users')->get(),
            'plan' => $stores->plan(),
            'users' => User::where('is_blocked', false)->orderBy('name')->get(['id', 'name', 'email'])
                ->reject(fn (User $user) => $user->isAdministrator()),
        ]);
    }
}
