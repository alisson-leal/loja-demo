<?php

namespace App\Livewire\Stock;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\CurrentStore;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Saldos de estoque')]
class Balance extends Component
{
    use WithPagination;

    #[Url(as: 'busca')]
    public string $search = '';

    #[Url(as: 'categoria')]
    public string $categoryId = '';

    #[Url(as: 'situacao')]
    public string $situation = '';

    /** Vazio: loja atual; "rede": soma de todas as lojas; número: uma loja. */
    #[Url(as: 'loja')]
    public string $store = '';

    public function mount(): void
    {
        Gate::authorize('estoque.ver');
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $term = trim($this->search);
        $allowed = CurrentStore::allowed(Auth::user());
        $network = $this->store === 'rede' && Auth::user()->can('lojas.ver_todas');
        $storeId = ctype_digit($this->store) && $allowed->contains('id', (int) $this->store) ? (int) $this->store : CurrentStore::id();

        $variants = ProductVariant::operational()
            ->with(['product', 'values.attribute'])
            ->when(! $network, fn ($query) => $query->withStoreStock($storeId))
            ->when($term !== '', fn ($query) => $query->search($term))
            ->when(ctype_digit($this->categoryId), fn ($query) => $query->inCategory((int) $this->categoryId))
            ->when($this->situation === 'baixo', fn ($query) => $network ? $query->lowStock() : $query->storeLowStock($storeId))
            ->when($this->situation === 'sem', fn ($query) => $network ? $query->outOfStock() : $query->storeOutOfStock($storeId))
            ->orderBy(Product::select('name')->whereColumn('products.id', 'product_variants.product_id'))
            ->orderBy('product_variants.id')
            ->paginate(25);

        return view('livewire.stock.balance', [
            'variants' => $variants,
            'categories' => Category::ordered(),
            'network' => $network,
            'multiple' => CurrentStore::multiple(),
            'stores' => $allowed,
            'canSeeNetwork' => Auth::user()->can('lojas.ver_todas'),
        ]);
    }
}
