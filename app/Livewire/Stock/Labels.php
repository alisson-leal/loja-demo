<?php

namespace App\Livewire\Stock;

use App\Models\ProductVariant;
use App\Models\StockEntry;
use App\Support\LabelPrinter;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Monta a lista de etiquetas (por produto ou a partir de uma entrada de
 * mercadorias) e abre a página de impressão.
 */
#[Title('Etiquetas')]
class Labels extends Component
{
    /** @var array<int, int> id da variação => quantidade de etiquetas */
    public array $items = [];

    public string $format = 'a4';

    public bool $showPrice = true;

    public string $entryId = '';

    public function mount(): void
    {
        Gate::authorize('produtos.ver');

        if (ctype_digit((string) request('entrada'))) {
            $this->entryId = (string) request('entrada');
            $this->loadEntry();

            return;
        }

        // Ao voltar da impressão, a lista anterior continua montada.
        if ($last = session('labels')) {
            $this->items = $last['items'];
            $this->format = $last['format'];
            $this->showPrice = $last['price'];
        }
    }

    #[On('variant-picked')]
    public function addVariant(int $id): void
    {
        $this->items[$id] = ($this->items[$id] ?? 0) + 1;
    }

    public function loadEntry(): void
    {
        $entry = StockEntry::with('movements')->find((int) $this->entryId);

        if (! $entry) {
            return;
        }

        foreach ($entry->movements as $movement) {
            $this->items[$movement->product_variant_id] = ($this->items[$movement->product_variant_id] ?? 0) + (int) $movement->quantity;
        }

        session()->now('success', 'Itens da entrada incluídos, uma etiqueta por peça recebida.');
    }

    public function remove(int $id): void
    {
        unset($this->items[$id]);
    }

    public function clear(): void
    {
        $this->reset('items', 'entryId');
    }

    public function print()
    {
        $items = collect($this->items)
            ->map(fn ($quantity) => max(0, min(500, (int) $quantity)))
            ->filter()
            ->all();

        if ($items === []) {
            session()->now('error', 'Inclua ao menos um produto com quantidade.');

            return null;
        }

        if (array_sum($items) > LabelPrinter::MAX_LABELS) {
            session()->now('error', 'Imprima no máximo '.LabelPrinter::MAX_LABELS.' etiquetas por vez.');

            return null;
        }

        session()->put('labels', [
            'items' => $items,
            'format' => array_key_exists($this->format, LabelPrinter::FORMATS) ? $this->format : 'a4',
            'price' => $this->showPrice,
        ]);

        return $this->redirectRoute('stock.labels.print');
    }

    public function render(): View
    {
        return view('livewire.stock.labels', [
            'variants' => ProductVariant::with(['product', 'values.attribute'])->whereIn('id', array_keys($this->items))->get()->keyBy('id'),
            'entries' => StockEntry::with('supplier:id,name')->latest('id')->limit(20)->get(),
            'formats' => LabelPrinter::FORMATS,
            'total' => array_sum(array_map('intval', $this->items)),
        ]);
    }
}
