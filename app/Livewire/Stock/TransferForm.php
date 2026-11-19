<?php

namespace App\Livewire\Stock;

use App\Livewire\Concerns\HandlesBusinessRules;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Services\SerialService;
use App\Services\StockTransferService;
use App\Support\CurrentStore;
use App\Support\Quantity;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Nova transferência')]
class TransferForm extends Component
{
    use HandlesBusinessRules;

    /** @var array<int, int|string> id da variação => quantidade (por metro ou quilo, como digitada) */
    public array $items = [];

    /** @var array<int, string> id da variação => números de série (produtos com controle de série) */
    public array $serials = [];

    public string $toStoreId = '';

    public string $notes = '';

    public function mount(): void
    {
        Gate::authorize('estoque.transferir');
    }

    #[On('variant-picked')]
    public function addVariant(int $id): void
    {
        $product = ProductVariant::with('product')->find($id)?->product;

        if ($product?->tracks_serial) {
            $this->items[$id] = SerialService::count($this->serials[$id] ?? '');
            $this->serials[$id] ??= '';

            return;
        }

        // Por metro ou quilo, a quantidade é digitada (ex.: 12,5).
        if ($product?->isFractional()) {
            $this->items[$id] ??= '';

            return;
        }

        $this->items[$id] = ((int) ($this->items[$id] ?? 0)) + 1;
    }

    #[On('serial-picked')]
    public function addSerial(int $id, string $serial): void
    {
        $current = preg_split('/[\s,;]+/', trim($this->serials[$id] ?? ''), -1, PREG_SPLIT_NO_EMPTY);

        if (! in_array($serial, $current, true)) {
            $current[] = $serial;
        }

        $this->serials[$id] = implode("\n", $current);
        $this->items[$id] = count($current);
    }

    public function updatedSerials($value, $key = null): void
    {
        if ($key !== null && ctype_digit((string) $key)) {
            $this->items[(int) $key] = SerialService::count($value);
        }
    }

    public function remove(int $id): void
    {
        unset($this->items[$id], $this->serials[$id]);
    }

    public function send(StockTransferService $transfers): void
    {
        Gate::authorize('estoque.transferir');

        if (! ctype_digit($this->toStoreId)) {
            $this->addError('toStoreId', 'Escolha a loja de destino.');

            return;
        }

        $units = $this->units();

        $done = $this->attempt(function () use ($transfers, $units) {
            $items = collect($this->items)->map(fn ($qty, $id) => [
                'variant_id' => (int) $id,
                'quantity' => match (true) {
                    array_key_exists($id, $this->serials) => SerialService::count($this->serials[$id]),
                    isset($units[$id]) => Quantity::parse($qty, $units[$id]),
                    default => max(0, (int) $qty),
                },
                'serials' => array_key_exists($id, $this->serials) ? SerialService::parse($this->serials[$id]) : [],
            ])->values()->all();

            return $transfers->send(Auth::user(), CurrentStore::id(), (int) $this->toStoreId, $items, trim($this->notes) ?: null);
        }, 'Transferência enviada.');

        if ($done) {
            session()->flash('success', 'Transferência enviada. As peças saíram do estoque desta loja e entram no destino quando ele confirmar o recebimento.');
            $this->redirectRoute('stock.transfers', ['ver' => 'enviadas']);
        }
    }

    /** @return array<int, string> id da variação => unidade, só dos itens por metro ou quilo */
    private function units(): array
    {
        return ProductVariant::with('product')->whereIn('id', array_keys($this->items))->get()
            ->filter(fn (ProductVariant $variant) => $variant->product->isFractional())
            ->mapWithKeys(fn (ProductVariant $variant) => [$variant->id => $variant->product->unit])
            ->all();
    }

    public function render(): View
    {
        $from = CurrentStore::id();
        $variants = ProductVariant::with(['product', 'values.attribute', 'storeStocks'])->whereIn('id', array_keys($this->items))->get()->keyBy('id');

        // Quantidade de cada item na escala guardada; por metro ou quilo, a linha conta como uma peça.
        $quantities = collect($this->items)->map(fn ($qty, $id) => isset($variants[$id]) && $variants[$id]->product->isFractional()
            ? Quantity::parse($qty, $variants[$id]->product->unit)
            : (int) $qty);

        return view('livewire.stock.transfer-form', [
            'from' => Store::find($from),
            'destinations' => Store::active()->ordered()->whereKeyNot($from)->get(),
            'variants' => $variants,
            'quantities' => $quantities,
            'pieces' => $quantities->map(fn ($qty, $id) => isset($variants[$id]) && $variants[$id]->product->isFractional() ? ($qty > 0 ? 1 : 0) : $qty)->sum(),
        ]);
    }
}
