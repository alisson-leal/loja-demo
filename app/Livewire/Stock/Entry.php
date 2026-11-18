<?php

namespace App\Livewire\Stock;

use App\Exceptions\BusinessRuleException;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Services\StockEntryService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Entrada de mercadorias')]
class Entry extends Component
{
    public string $supplierId = '';

    public string $documentNumber = '';

    public string $notes = '';

    /** @var list<array{variant_id: int, label: string, sku: string, quantity: string, unit_cost: string}> */
    public array $items = [];

    public function mount(): void
    {
        Gate::authorize('estoque.entrada');
    }

    #[On('variant-picked')]
    public function addVariant(int $id): void
    {
        foreach ($this->items as $index => $item) {
            if ($item['variant_id'] === $id) {
                $this->items[$index]['quantity'] = (string) ((int) $item['quantity'] + 1);

                return;
            }
        }

        $variant = ProductVariant::with(['product', 'values.attribute'])->findOrFail($id);

        $this->items[] = [
            'variant_id' => $variant->id,
            'label' => $variant->fullName(),
            'sku' => $variant->sku,
            'quantity' => '1',
            'unit_cost' => Gate::allows('produtos.ver_custo') ? (string) $variant->cost_price : '',
        ];
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function save(StockEntryService $entries): void
    {
        Gate::authorize('estoque.entrada');

        $this->validate([
            'supplierId' => ['nullable', 'exists:suppliers,id'],
            'documentNumber' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'items.*.unit_cost' => [Gate::allows('produtos.ver_custo') ? 'required' : 'nullable', 'numeric', 'min:0', 'max:99999999.99'],
        ], [
            'items.required' => 'Inclua ao menos um item na entrada.',
        ], [
            'items.*.quantity' => 'quantidade',
            'items.*.unit_cost' => 'custo unitário',
        ]);

        $canSeeCost = Gate::allows('produtos.ver_custo');

        try {
            $entry = $entries->register(
                Auth::user(),
                $this->supplierId === '' ? null : (int) $this->supplierId,
                $this->documentNumber === '' ? null : trim($this->documentNumber),
                $this->notes === '' ? null : $this->notes,
                array_map(fn (array $item) => [
                    'variant_id' => (int) $item['variant_id'],
                    'quantity' => (int) $item['quantity'],
                    'unit_cost' => $canSeeCost ? $item['unit_cost'] : null,
                ], $this->items),
            );
        } catch (BusinessRuleException $exception) {
            session()->now('error', $exception->getMessage());

            return;
        }

        session()->flash('success', 'Entrada registrada e estoque atualizado.');
        session()->flash('labels_entry', $entry->id);
        $this->redirectRoute('stock.history');
    }

    public function render(): View
    {
        return view('livewire.stock.entry', [
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'canSeeCost' => Gate::allows('produtos.ver_custo'),
        ]);
    }
}
