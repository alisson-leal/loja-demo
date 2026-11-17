<?php

namespace App\Livewire\Sales;

use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Vendas')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'busca')]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url(as: 'operador')]
    public string $userId = '';

    #[Url(as: 'forma')]
    public string $methodId = '';

    #[Url(as: 'de')]
    public string $from = '';

    #[Url(as: 'ate')]
    public string $to = '';

    public function mount(): void
    {
        abort_unless(Gate::any(['pdv.vender', 'vendas.ver']), 403);
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $user = Auth::user();
        $term = trim($this->search);
        $isDate = fn (string $value) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;

        $sales = Sale::visibleTo($user)
            ->with(['user:id,name', 'customer:id,name', 'payments.method'])
            ->when($term !== '', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->when(ctype_digit($term), fn (Builder $byId) => $byId->orWhere('id', (int) $term))
                ->orWhereHas('customer', fn (Builder $customer) => $customer->search($term))))
            ->when(in_array($this->status, [Sale::COMPLETED, Sale::CANCELLED], true), fn (Builder $query) => $query->where('status', $this->status))
            ->when(ctype_digit($this->userId) && $user->can('vendas.ver'), fn (Builder $query) => $query->where('user_id', (int) $this->userId))
            ->when(ctype_digit($this->methodId), fn (Builder $query) => $query->whereHas('payments', fn (Builder $payment) => $payment->where('payment_method_id', (int) $this->methodId)))
            ->when($isDate($this->from), fn (Builder $query) => $query->where('created_at', '>=', $this->from.' 00:00:00'))
            ->when($isDate($this->to), fn (Builder $query) => $query->where('created_at', '<=', $this->to.' 23:59:59'))
            ->latest('id')
            ->paginate(20);

        return view('livewire.sales.index', [
            'sales' => $sales,
            'seesAll' => $user->can('vendas.ver'),
            'users' => $user->can('vendas.ver') ? User::orderBy('name')->get(['id', 'name']) : collect(),
            'methods' => PaymentMethod::orderBy('position')->get(['id', 'name']),
        ]);
    }
}
