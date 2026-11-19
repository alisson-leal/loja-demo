<?php

namespace App\Livewire\Stock;

use App\Models\ProductVariant;
use App\Models\SerialNumber;
use App\Support\CurrentStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Busca de variação por nome, SKU ou leitor de código de barras.
 * Avisa o componente pai com o evento "variant-picked". Com $serials, lê
 * também o número de série de uma unidade desta loja ("serial-picked").
 */
class VariantPicker extends Component
{
    public string $term = '';

    public string $notFound = '';

    #[Locked]
    public bool $serials = false;

    public function pick(int $id): void
    {
        if (ProductVariant::operational()->whereKey($id)->exists()) {
            $this->dispatch('variant-picked', id: $id);
        }

        $this->reset('term', 'notFound');
    }

    /** Disparado pelo Enter, que é o que o leitor de código de barras envia. */
    public function pickExact(): void
    {
        $term = trim($this->term);

        if ($term === '') {
            return;
        }

        $exact = ProductVariant::operational()
            ->where(fn (Builder $query) => $query->where('barcode', $term)->orWhere('sku', $term))
            ->first();

        if (! $exact && $this->serials && $unit = $this->unitInStock($term)) {
            $this->dispatch('serial-picked', id: $unit->product_variant_id, serial: $unit->serial);
            $this->reset('term', 'notFound');

            return;
        }

        $matches = $exact ? collect([$exact]) : ProductVariant::operational()->search($term)->limit(2)->get();

        if ($matches->count() === 1) {
            $this->pick($matches->first()->id);

            return;
        }

        $this->notFound = $matches->isEmpty() ? "Nenhum item encontrado para \"{$term}\"." : '';
    }

    private function unitInStock(string $term): ?SerialNumber
    {
        return SerialNumber::where('serial', Str::upper($term))
            ->where('status', SerialNumber::IN_STOCK)
            ->where('store_id', CurrentStore::id())
            ->whereHas('variant', fn (Builder $query) => $query->operational())
            ->first();
    }

    public function render(): View
    {
        $term = trim($this->term);

        return view('livewire.stock.variant-picker', [
            'results' => mb_strlen($term) >= 2
                ? ProductVariant::operational()->search($term)
                    ->with(['product', 'values.attribute', 'storeStocks'])
                    ->orderBy('id')
                    ->limit(10)
                    ->get()
                : collect(),
        ]);
    }
}
