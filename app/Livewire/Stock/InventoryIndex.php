<?php

namespace App\Livewire\Stock;

use App\Exceptions\BusinessRuleException;
use App\Models\Category;
use App\Models\Inventory;
use App\Services\InventoryService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Inventário')]
class InventoryIndex extends Component
{
    use WithPagination;

    public string $categoryId = '';

    public string $notes = '';

    public function mount(): void
    {
        Gate::authorize('estoque.inventario');
    }

    public function open(InventoryService $inventories): void
    {
        Gate::authorize('estoque.inventario');

        $this->validate([
            'categoryId' => ['nullable', 'exists:categories,id'],
            'notes' => ['nullable', 'string', 'max:255'],
        ], [], ['categoryId' => 'categoria', 'notes' => 'observação']);

        try {
            $inventory = $inventories->open(
                Auth::user(),
                $this->categoryId === '' ? null : (int) $this->categoryId,
                $this->notes === '' ? null : $this->notes,
            );
        } catch (BusinessRuleException $exception) {
            session()->now('error', $exception->getMessage());

            return;
        }

        $this->redirectRoute('stock.inventory', ['inventory' => $inventory->id]);
    }

    public function render(): View
    {
        return view('livewire.stock.inventory-index', [
            'inventories' => Inventory::with(['category', 'opener:id,name'])->withCount('items')->latest('id')->paginate(15),
            'categories' => Category::ordered(),
        ]);
    }
}
