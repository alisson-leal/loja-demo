<?php

namespace App\Livewire\Sales;

use App\Models\Sale;
use App\Models\SerialNumber;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Consulta de garantia: pelo número de série da unidade ou pelo número da venda. */
#[Title('Garantias e números de série')]
class Warranty extends Component
{
    #[Url(as: 'busca')]
    public string $search = '';

    public function mount(): void
    {
        Gate::authorize('vendas.garantia');
    }

    public function render(): View
    {
        $term = Str::upper(trim($this->search));
        $searching = mb_strlen($term) >= 3 || ($term !== '' && ctype_digit(ltrim($term, '#')));

        $units = mb_strlen($term) >= 3
            ? SerialNumber::where('serial', 'like', '%'.addcslashes($term, '%_\\').'%')
                ->with(['variant.product', 'variant.values.attribute', 'store', 'saleItem.sale.customer', 'events.store', 'events.user'])
                ->orderBy('serial')
                ->limit(20)
                ->get()
            : collect();

        $saleNumber = ltrim($term, '#');
        $sale = $saleNumber !== '' && ctype_digit($saleNumber)
            ? Sale::with(['items' => fn ($query) => $query->where(fn ($q) => $q->whereNotNull('warranty_until')->orWhereNotNull('serials')), 'customer'])->find((int) $saleNumber)
            : null;

        return view('livewire.sales.warranty', [
            'searching' => $searching,
            'units' => $units,
            'sale' => $sale?->items->isNotEmpty() ? $sale : null,
        ]);
    }
}
