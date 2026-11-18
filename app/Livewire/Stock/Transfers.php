<?php

namespace App\Livewire\Stock;

use App\Livewire\Concerns\HandlesBusinessRules;
use App\Models\StockTransfer;
use App\Services\StockTransferService;
use App\Support\CurrentStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Transferências entre lojas')]
class Transfers extends Component
{
    use HandlesBusinessRules;
    use WithPagination;

    #[Url(as: 'ver')]
    public string $view = 'a_receber';

    public ?int $openId = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->canAny(['estoque.transferir', 'estoque.ver']), 403);
    }

    public function updatedView(): void
    {
        $this->resetPage();
    }

    public function toggle(int $id): void
    {
        $this->openId = $this->openId === $id ? null : $id;
    }

    public function receive(int $id, StockTransferService $transfers): void
    {
        Gate::authorize('estoque.transferir');
        $this->attempt(fn () => $transfers->receive(Auth::user(), StockTransfer::findOrFail($id)), 'Transferência recebida. As peças entraram no estoque desta loja.');
    }

    public function cancel(int $id, StockTransferService $transfers): void
    {
        Gate::authorize('estoque.transferir');
        $this->attempt(fn () => $transfers->cancel(Auth::user(), StockTransfer::findOrFail($id)), 'Transferência cancelada. As peças voltaram para a loja de origem.');
    }

    public function render(): View
    {
        $mine = CurrentStore::allowedIds(Auth::user());
        $current = CurrentStore::id();

        $transfers = StockTransfer::with('from', 'to', 'user:id,name', 'receiver:id,name', 'items.variant.product', 'items.variant.values.attribute')
            ->withSum('items as pieces', 'quantity')
            ->where(fn (Builder $q) => $q->whereIn('from_store_id', $mine)->orWhereIn('to_store_id', $mine))
            ->when($this->view === 'a_receber', fn (Builder $q) => $q->where('status', StockTransfer::IN_TRANSIT)->where('to_store_id', $current))
            ->when($this->view === 'enviadas', fn (Builder $q) => $q->where('from_store_id', $current))
            ->when($this->view === 'recebidas', fn (Builder $q) => $q->where('to_store_id', $current)->where('status', StockTransfer::RECEIVED))
            ->latest('id')
            ->paginate(20);

        return view('livewire.stock.transfers', [
            'transfers' => $transfers,
            'current' => $current,
            'mine' => $mine,
            'incoming' => StockTransfer::where('status', StockTransfer::IN_TRANSIT)->where('to_store_id', $current)->count(),
        ]);
    }
}
