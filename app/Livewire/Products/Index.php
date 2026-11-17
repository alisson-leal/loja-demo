<?php

namespace App\Livewire\Products;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Produtos')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'busca')]
    public string $search = '';

    #[Url(as: 'categoria')]
    public string $categoryId = '';

    #[Url(as: 'marca')]
    public string $brandId = '';

    #[Url]
    public string $status = 'ativos';

    public function mount(): void
    {
        Gate::authorize('produtos.ver');
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $term = trim($this->search);

        $products = Product::with(['category.parent', 'brand', 'images'])
            ->withSum('variants as stock_total', 'stock_on_hand')
            ->withMin('variants as price_min', 'sale_price')
            ->withMax('variants as price_max', 'sale_price')
            ->withCount('variants')
            ->when($term !== '', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->where('name', 'like', "%{$term}%")
                ->orWhere('internal_code', 'like', "%{$term}%")
                ->orWhere('sku', 'like', "%{$term}%")
                ->orWhereHas('variants', fn (Builder $variant) => $variant
                    ->where('sku', 'like', "%{$term}%")
                    ->orWhere('barcode', $term))))
            ->when(ctype_digit($this->categoryId), fn (Builder $query) => $query
                ->whereIn('category_id', Category::idsIncludingChildren((int) $this->categoryId)))
            ->when(ctype_digit($this->brandId), fn (Builder $query) => $query->where('brand_id', (int) $this->brandId))
            ->when($this->status === 'ativos', fn (Builder $query) => $query->where('is_active', true))
            ->when($this->status === 'inativos', fn (Builder $query) => $query->where('is_active', false))
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.products.index', [
            'products' => $products,
            'categories' => Category::ordered(),
            'brands' => Brand::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
