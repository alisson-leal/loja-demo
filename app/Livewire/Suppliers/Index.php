<?php

namespace App\Livewire\Suppliers;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Fornecedores')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'busca')]
    public string $search = '';

    public function mount(): void
    {
        Gate::authorize('fornecedores.ver');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $term = trim($this->search);

        return view('livewire.suppliers.index', [
            'suppliers' => Supplier::withCount(['products', 'entries'])
                ->when($term !== '', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('contact_name', 'like', "%{$term}%")
                    ->orWhere('document', 'like', "%{$term}%")))
                ->orderBy('name')
                ->paginate(15),
        ]);
    }
}
