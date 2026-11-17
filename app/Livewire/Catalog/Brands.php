<?php

namespace App\Livewire\Catalog;

use App\Livewire\Concerns\HandlesBusinessRules;
use App\Models\Brand;
use App\Services\CatalogService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Marcas')]
class Brands extends Component
{
    use HandlesBusinessRules;

    #[Locked]
    public ?int $editingId = null;

    public string $name = '';

    public bool $isActive = true;

    public function mount(): void
    {
        Gate::authorize('cadastros.ver');
    }

    public function edit(int $id): void
    {
        Gate::authorize('cadastros.gerir');

        $brand = Brand::findOrFail($id);
        $this->editingId = $brand->id;
        $this->name = $brand->name;
        $this->isActive = $brand->is_active;
        $this->resetValidation();
    }

    public function cancel(): void
    {
        $this->reset('editingId', 'name', 'isActive');
        $this->resetValidation();
    }

    public function save(CatalogService $catalog): void
    {
        Gate::authorize('cadastros.gerir');

        $this->name = trim($this->name);

        $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:80', Rule::unique('brands', 'name')->ignore($this->editingId)],
        ], [], ['name' => 'nome']);

        $brand = $this->editingId ? Brand::findOrFail($this->editingId) : null;

        if ($this->attempt(fn () => $catalog->saveBrand($brand, $this->name, $this->isActive), 'Marca salva.')) {
            $this->reset('editingId', 'name', 'isActive');
        }
    }

    public function delete(int $id, CatalogService $catalog): void
    {
        Gate::authorize('cadastros.gerir');

        $this->attempt(fn () => $catalog->deleteBrand(Brand::findOrFail($id)), 'Marca excluída.');
    }

    public function render(): View
    {
        return view('livewire.catalog.brands', [
            'brands' => Brand::withCount('products')->orderBy('name')->get(),
        ]);
    }
}
