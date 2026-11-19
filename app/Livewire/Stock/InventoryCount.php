<?php

namespace App\Livewire\Stock;

use App\Exceptions\BusinessRuleException;
use App\Livewire\Concerns\HandlesBusinessRules;
use App\Models\Inventory;
use App\Services\InventoryService;
use App\Support\Quantity;
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
        $item = $this->inventory()->items()->with('variant.product')->findOrFail($itemId);
        $unit = $item->variant->product->unit;

        // Por metro ou quilo, aceita até 3 casas decimais (ex.: 12,5).
        $valid = Quantity::isFractional($unit)
            ? preg_match('/^\d{1,9}([.,]\d{0,3})?$/', $value)
            : ctype_digit($value);

        if ($value !== '' && ! $valid) {
            session()->now('error', Quantity::isFractional($unit) ? 'Informe a quantidade com até 3 casas decimais, sem sinal.' : 'Informe um número inteiro, sem sinal.');

            return;
        }

        try {
            $inventories->setCount($item, $value === '' ? null : Quantity::parse($value, $unit));
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
            ->with(['variant.product', 'variant.values.attribute', 'variant.storeStocks'])
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
