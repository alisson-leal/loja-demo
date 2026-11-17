<?php

namespace App\Livewire\Stock;

use App\Exceptions\BusinessRuleException;
use App\Livewire\Concerns\HandlesBusinessRules;
use App\Models\Inventory;
use App\Services\InventoryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Contagem de inventário')]
class InventoryCount extends Component
{
    use HandlesBusinessRules, WithPagination;

    #[Locked]
    public int $inventoryId;

    public string $search = '';

    public bool $onlyPending = false;

    public string $code = '';

    public function mount($inventory): void
    {
        Gate::authorize('estoque.inventario');
        $this->inventoryId = Inventory::findOrFail($inventory)->id;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedOnlyPending(): void
    {
        $this->resetPage();
    }

    private function inventory(): Inventory
    {
        return Inventory::findOrFail($this->inventoryId);
    }

    public function saveCount(int $itemId, string $value, InventoryService $inventories): void
    {
        Gate::authorize('estoque.inventario');

        $value = trim($value);

        if ($value !== '' && ! ctype_digit($value)) {
            session()->now('error', 'Informe um número inteiro, sem sinal.');

            return;
        }

        $item = $this->inventory()->items()->findOrFail($itemId);

        try {
            $inventories->setCount($item, $value === '' ? null : (int) $value);
        } catch (BusinessRuleException $exception) {
            session()->now('error', $exception->getMessage());
        }
    }

    public function scan(InventoryService $inventories): void
    {
        Gate::authorize('estoque.inventario');

        $code = trim($this->code);
        $this->code = '';

        if ($code !== '') {
            $this->attempt(fn () => $inventories->scan($this->inventory(), $code), "Código {$code} contado.");
        }
    }

    public function finish(InventoryService $inventories): void
    {
        Gate::authorize('estoque.inventario');

        try {
            $adjusted = $inventories->finish(Auth::user(), $this->inventory());
            session()->now('success', "Inventário concluído. {$adjusted} item(ns) com saldo ajustado.");
        } catch (BusinessRuleException $exception) {
            session()->now('error', $exception->getMessage());
        }
    }

    public function cancel(InventoryService $inventories): void
    {
        Gate::authorize('estoque.inventario');

        $this->attempt(fn () => $inventories->cancel(Auth::user(), $this->inventory()), 'Inventário cancelado.');
    }

    public function render(): View
    {
        $inventory = $this->inventory();
        $term = trim($this->search);

        $items = $inventory->items()
            ->with(['variant.product', 'variant.values.attribute'])
            ->when($term !== '', fn (Builder $query) => $query->whereHas('variant', fn (Builder $variant) => $variant->search($term)))
            ->when($this->onlyPending, fn (Builder $query) => $query->whereNull('counted_quantity'))
            ->orderBy('product_variant_id')
            ->paginate(30);

        return view('livewire.stock.inventory-count', [
            'inventory' => $inventory,
            'items' => $items,
            'counted' => $inventory->items()->whereNotNull('counted_quantity')->count(),
            'total' => $inventory->items()->count(),
        ]);
    }
}
