<?php

namespace App\Livewire\Stock;

use App\Models\ProductVariant;
use App\Models\SerialNumber;
use App\Models\Store;
use App\Support\CurrentStore;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Detalhe do SKU')]
class VariantDetail extends Component
{
    use WithPagination;

    #[Locked]
    public int $variantId;

    public function mount($variant): void
    {
        Gate::authorize('estoque.ver');
        $this->variantId = ProductVariant::findOrFail($variant)->id;
    }

    public function render(): View
    {
        $variant = ProductVariant::with(['product', 'values.attribute', 'storeStocks'])->findOrFail($this->variantId);
        $canSeeCost = Auth::user()->can('produtos.ver_custo');
        $multiple = CurrentStore::multiple();

        return view('livewire.stock.variant-detail', [
            'variant' => $variant,
            'multiple' => $multiple,
            'stores' => $multiple ? Store::active()->ordered()->get() : collect(),
            'movements' => $variant->movements()->with('user:id,name', 'store:id,name')->latest('id')->paginate(15),
            'canSeeCost' => $canSeeCost,
            'units' => $variant->product->tracks_serial
                ? SerialNumber::where('product_variant_id', $variant->id)
                    ->whereIn('status', [SerialNumber::IN_STOCK, SerialNumber::IN_TRANSIT])
                    ->with('store:id,name')->orderBy('serial')->get()
                : collect(),
            'canSeeWarranty' => Gate::allows('vendas.garantia'),
            'costs' => $canSeeCost
                ? $variant->costHistory()->with(['user:id,name', 'supplier:id,name'])->latest('id')->limit(20)->get()
                : collect(),
        ]);
    }
}
