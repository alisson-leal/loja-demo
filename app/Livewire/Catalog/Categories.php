<?php

namespace App\Livewire\Catalog;

use App\Livewire\Concerns\HandlesBusinessRules;
use App\Models\Category;
use App\Services\CatalogService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Categorias')]
class Categories extends Component
{
    use HandlesBusinessRules;

    #[Locked]
    public ?int $editingId = null;

    public string $name = '';

    public string $parentId = '';

    public bool $isActive = true;

    public function mount(): void
    {
        Gate::authorize('cadastros.ver');
    }

    public function edit(int $id): void
    {
        Gate::authorize('cadastros.gerir');

        $category = Category::findOrFail($id);
        $this->editingId = $category->id;
        $this->name = $category->name;
        $this->parentId = (string) $category->parent_id;
        $this->isActive = $category->is_active;
        $this->resetValidation();
    }

    public function cancel(): void
    {
        $this->reset('editingId', 'name', 'parentId', 'isActive');
        $this->resetValidation();
    }

    public function save(CatalogService $catalog): void
    {
        Gate::authorize('cadastros.gerir');

        $this->name = trim($this->name);
        $parentId = $this->parentId === '' ? null : (int) $this->parentId;

        $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:80', Rule::unique('categories', 'name')
                ->ignore($this->editingId)
                ->where(fn ($query) => $parentId ? $query->where('parent_id', $parentId) : $query->whereNull('parent_id'))],
            'parentId' => ['nullable', 'exists:categories,id'],
        ], [], ['name' => 'nome', 'parentId' => 'categoria principal']);

        $category = $this->editingId ? Category::findOrFail($this->editingId) : null;

        if ($this->attempt(fn () => $catalog->saveCategory($category, $this->name, $parentId, $this->isActive), 'Categoria salva.')) {
            $this->reset('editingId', 'name', 'parentId', 'isActive');
        }
    }

    public function delete(int $id, CatalogService $catalog): void
    {
        Gate::authorize('cadastros.gerir');

        $this->attempt(fn () => $catalog->deleteCategory(Category::findOrFail($id)), 'Categoria excluída.');
    }

    public function render(): View
    {
        return view('livewire.catalog.categories', [
            'categories' => Category::ordered()->loadCount('products'),
            'roots' => Category::whereNull('parent_id')->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
