<?php

namespace App\Livewire\Sales;

use App\Models\Consignment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Condicionais')]
class ConsignmentIndex extends Component
{
    use WithPagination;

    #[Url(as: 'ver')]
    public string $view = 'abertas';

    #[Url(as: 'busca')]
    public string $search = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->canAny(['condicional.criar', 'condicional.acertar']), 403);
    }

    public function updated(string $property): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $term = trim($this->search);

        $consignments = Consignment::with('customer:id,name,whatsapp,phone', 'user:id,name')
            ->withSum('items as pieces', 'quantity')
            ->when($this->view === 'abertas', fn (Builder $q) => $q->whereIn('status', [Consignment::OPEN, Consignment::AWAITING_SALE]))
            ->when($this->view === 'vencidas', fn (Builder $q) => $q->where('status', Consignment::OPEN)->whereDate('due_date', '<', today()))
            ->when($this->view === 'encerradas', fn (Builder $q) => $q->where('status', Consignment::CLOSED))
            ->when($term !== '', fn (Builder $q) => $q->whereHas('customer', fn (Builder $c) => $c->search($term)))
            ->orderByRaw("status = 'closed'")
            ->orderBy('due_date')
            ->paginate(20);

        return view('livewire.sales.consignment-index', [
            'consignments' => $consignments,
            'overdue' => Consignment::where('status', Consignment::OPEN)->whereDate('due_date', '<', today())->count(),
        ]);
    }
}
