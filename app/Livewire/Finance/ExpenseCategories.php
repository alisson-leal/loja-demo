<?php

namespace App\Livewire\Finance;

use App\Models\ExpenseCategory;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Categorias de despesa')]
class ExpenseCategories extends Component
{
    #[Locked]
    public ?int $editingId = null;

    public string $name = '';

    public bool $isActive = true;

    public function mount(): void
    {
        Gate::authorize('financeiro.pagar');
    }

    public function edit(int $id): void
    {
        $category = ExpenseCategory::findOrFail($id);
        $this->editingId = $category->id;
        $this->name = $category->name;
        $this->isActive = $category->is_active;
        $this->resetValidation();
    }

    public function cancel(): void
    {
        $this->reset('editingId', 'name', 'isActive');
        $this->resetValidation();
    }

    public function save(AuditLogger $audit): void
    {
        Gate::authorize('financeiro.pagar');
        $this->name = trim($this->name);

        $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:60', Rule::unique('expense_categories', 'name')->ignore($this->editingId)],
        ], [], ['name' => 'nome']);

        $category = $this->editingId ? ExpenseCategory::findOrFail($this->editingId) : new ExpenseCategory;
        // Categorias com regra própria no resultado ficam sempre ativas.
        $category->fill(['name' => $this->name, 'is_active' => $category->system_key ? true : $this->isActive])->save();

        $audit->record('finance.category_saved', "Categoria de despesa {$category->name} salva.", $category);
        session()->now('success', 'Categoria salva.');
        $this->reset('editingId', 'name', 'isActive');
    }

    public function render(): View
    {
        return view('livewire.finance.expense-categories', [
            'categories' => ExpenseCategory::ordered()->withCount('payables')->get(),
        ]);
    }
}
