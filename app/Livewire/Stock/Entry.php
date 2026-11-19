<?php

namespace App\Livewire\Stock;

use App\Exceptions\BusinessRuleException;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Services\SerialService;
use App\Services\StockEntryService;
use App\Support\Quantity;
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
                if (! $item['tracks'] && ! ($item['unit'] ?? null)) {
                    $this->items[$index]['quantity'] = (string) ((int) $item['quantity'] + 1);
                }

                return;
            }
        }

        $variant = ProductVariant::with(['product', 'values.attribute'])->findOrFail($id);

        $this->items[] = [
            'variant_id' => $variant->id,
            'label' => $variant->fullName(),
            'sku' => $variant->sku,
            'quantity' => $variant->product->tracks_serial ? '0' : ($variant->product->isFractional() ? '' : '1'),
            'unit_cost' => Gate::allows('produtos.ver_custo') ? (string) $variant->cost_price : '',
            'tracks' => $variant->product->tracks_serial,
            'serials' => '',
            // Por metro ou quilo: "m" ou "kg"; nulo para produtos por unidade.
            'unit' => $variant->product->isFractional() ? $variant->product->unit : null,
        ];
    }

    /** Quantidade do item na escala guardada (milésimos por metro ou quilo). */
    private function quantityOf(array $item): int
    {
        return ($item['unit'] ?? null) ? Quantity::parse($item['quantity'], $item['unit']) : (int) $item['quantity'];
    }

    /** Com número de série, a quantidade é a dos números digitados. */
    public function updatedItems($value, ?string $key = null): void
    {
        [$index, $field] = array_pad(explode('.', (string) $key, 2), 2, null);

        if ($field === 'serials' && isset($this->items[$index]) && $this->items[$index]['tracks']) {
            $this->items[$index]['quantity'] = (string) SerialService::count($value);
        }
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function save(StockEntryService $entries): void
    {
        Gate::authorize('estoque.entrada');

        foreach ($this->items as $index => $item) {
            if ($item['tracks']) {
                $this->items[$index]['quantity'] = (string) SerialService::count($item['serials']);
            }
        }

        $rules = [
            'supplierId' => ['nullable', 'exists:suppliers,id'],
            'documentNumber' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.unit_cost' => [Gate::allows('produtos.ver_custo') ? 'required' : 'nullable', 'numeric', 'min:0', 'max:99999999.99'],
        ];

        foreach ($this->items as $index => $item) {
            $rules["items.{$index}.quantity"] = ($item['unit'] ?? null)
                ? ['required', fn ($attribute, $value, $fail) => Quantity::parse($value, $item['unit']) > 0 && Quantity::parse($value, $item['unit']) <= 1_000_000_000
                    ? null
                    : $fail('Informe a quantidade em '.($item['unit'] === Quantity::METER ? 'metros' : 'quilos').', com até 3 casas (ex.: 12,5).')]
                : ['required', 'integer', 'min:1', 'max:1000000'];
        }

        $this->validate($rules, [
            'items.required' => 'Inclua ao menos um item na entrada.',
            'items.*.quantity.min' => 'Informe a quantidade (com número de série, digite os números).',
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
                    'quantity' => $this->quantityOf($item),
                    'unit_cost' => $canSeeCost ? $item['unit_cost'] : null,
                    'serials' => $item['tracks'] ? SerialService::parse($item['serials']) : [],
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
