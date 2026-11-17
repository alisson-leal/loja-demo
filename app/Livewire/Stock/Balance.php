<?php

namespace App\Livewire\Stock;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
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

        $variants = ProductVariant::operational()
            ->with(['product', 'values.attribute'])
            ->when($term !== '', fn ($query) => $query->search($term))
            ->when(ctype_digit($this->categoryId), fn ($query) => $query->inCategory((int) $this->categoryId))
            ->when($this->situation === 'baixo', fn ($query) => $query->lowStock())
            ->when($this->situation === 'sem', fn ($query) => $query->outOfStock())
            ->orderBy(Product::select('name')->whereColumn('products.id', 'product_variants.product_id'))
            ->orderBy('id')
            ->paginate(25);

        return view('livewire.stock.balance', [
            'variants' => $variants,
            'categories' => Category::ordered(),
        ]);
    }
}
