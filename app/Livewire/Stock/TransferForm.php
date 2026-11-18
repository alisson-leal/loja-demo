<?php

namespace App\Livewire\Stock;

use App\Livewire\Concerns\HandlesBusinessRules;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Services\StockTransferService;
use App\Support\CurrentStore;
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

    /** @var array<int, int> id da variação => quantidade */
    public array $items = [];

    public string $toStoreId = '';

    public string $notes = '';

    public function mount(): void
    {
        Gate::authorize('estoque.transferir');
    }

    #[On('variant-picked')]
    public function addVariant(int $id): void
    {
        $this->items[$id] = ($this->items[$id] ?? 0) + 1;
    }

    public function remove(int $id): void
    {
        unset($this->items[$id]);
    }

    public function send(StockTransferService $transfers): void
    {
        Gate::authorize('estoque.transferir');

        if (! ctype_digit($this->toStoreId)) {
            $this->addError('toStoreId', 'Escolha a loja de destino.');

            return;
        }

        $items = collect($this->items)->map(fn ($qty, $id) => ['variant_id' => (int) $id, 'quantity' => max(0, (int) $qty)])->values()->all();

        if ($this->attempt(fn () => $transfers->send(Auth::user(), CurrentStore::id(), (int) $this->toStoreId, $items, trim($this->notes) ?: null), 'Transferência enviada.')) {
            session()->flash('success', 'Transferência enviada. As peças saíram do estoque desta loja e entram no destino quando ele confirmar o recebimento.');
            $this->redirectRoute('stock.transfers', ['ver' => 'enviadas']);
        }
    }

    public function render(): View
    {
        $from = CurrentStore::id();

        return view('livewire.stock.transfer-form', [
            'from' => Store::find($from),
            'destinations' => Store::active()->ordered()->whereKeyNot($from)->get(),
            'variants' => ProductVariant::with(['product', 'values.attribute', 'storeStocks'])->whereIn('id', array_keys($this->items))->get()->keyBy('id'),
        ]);
    }
}
