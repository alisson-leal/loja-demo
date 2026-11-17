<?php

namespace App\Livewire\Finance;

use App\Livewire\Concerns\HandlesBusinessRules;
use App\Livewire\Concerns\HasReportPeriod;
use App\Models\ExpenseCategory;
use App\Models\Payable;
use App\Services\PayableService;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

#[Title('Contas a pagar')]
class Payables extends Component
{
    use HandlesBusinessRules;
    use HasReportPeriod;
    use WithPagination;

    public const VIEWS = [
        'a_pagar' => 'A pagar (inclui vencidas)',
        'pagas' => 'Pagas no período',
        'todas' => 'Todas com vencimento no período',
        'canceladas' => 'Canceladas',
    ];

    #[Url(as: 'ver')]
    public string $view = 'a_pagar';

    #[Url(as: 'categoria')]
    public string $categoryId = '';

    public string $paymentDate = '';

    public ?int $cancelingId = null;

    public string $cancelReason = '';

    public function mount(): void
    {
        Gate::authorize('financeiro.ver');
        $this->initPeriod();
        $this->paymentDate = today()->toDateString();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['view', 'categoryId', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public function pay(int $id, PayableService $payables): void
    {
        Gate::authorize('financeiro.pagar');

        try {
            $date = Carbon::createFromFormat('!Y-m-d', $this->paymentDate);
        } catch (Throwable) {
            $this->addError('paymentDate', 'Informe a data do pagamento.');

            return;
        }

        $this->attempt(fn () => $payables->pay(Payable::findOrFail($id), $date, Auth::user()), 'Pagamento registrado.');
    }

    public function reopen(int $id, PayableService $payables): void
    {
        Gate::authorize('financeiro.pagar');
        $this->attempt(fn () => $payables->reopen(Payable::findOrFail($id)), 'Pagamento desfeito. A conta voltou a ficar em aberto.');
    }

    public function startCancel(int $id): void
    {
        Gate::authorize('financeiro.pagar');
        $this->cancelingId = $id;
        $this->reset('cancelReason');
        $this->resetValidation();
    }

    public function confirmCancel(PayableService $payables): void
    {
        Gate::authorize('financeiro.pagar');
        $this->cancelReason = trim($this->cancelReason);
        $this->validate(['cancelReason' => ['required', 'string', 'min:3', 'max:200']], [], ['cancelReason' => 'motivo']);

        if ($this->attempt(fn () => $payables->cancel(Payable::findOrFail($this->cancelingId), $this->cancelReason, Auth::user()), 'Conta cancelada.')) {
            $this->reset('cancelingId', 'cancelReason');
        }
    }

    public function render(): View
    {
        [$from, $to, $error] = $this->period();
        [$start, $end] = [$from->toDateString(), $to->toDateString()];
        $today = today()->toDateString();

        $query = Payable::with('category:id,name', 'supplier:id,name')
            ->when(ctype_digit($this->categoryId), fn (Builder $q) => $q->where('expense_category_id', (int) $this->categoryId))
            ->when($this->view === 'a_pagar', fn (Builder $q) => $q->open()->where('due_date', '<=', $end)->orderBy('due_date'))
            ->when($this->view === 'pagas', fn (Builder $q) => $q->where('status', Payable::PAID)->whereBetween('paid_at', [$start, $end])->orderByDesc('paid_at'))
            ->when($this->view === 'todas', fn (Builder $q) => $q->whereBetween('due_date', [$start, $end])->orderBy('due_date'))
            ->when($this->view === 'canceladas', fn (Builder $q) => $q->where('status', Payable::CANCELLED)->whereBetween('due_date', [$start, $end])->orderBy('due_date'))
            ->orderBy('id');

        $listTotal = Money::toCents((clone $query)->reorder()->sum('amount'));

        return view('livewire.finance.payables', [
            'payables' => $query->paginate(25),
            'listTotal' => $listTotal,
            'rangeError' => $error,
            'categories' => ExpenseCategory::ordered()->get(['id', 'name']),
            'views' => self::VIEWS,
            'cards' => [
                'overdue' => Money::toCents(Payable::open()->where('due_date', '<', $today)->sum('amount')),
                'today' => Money::toCents(Payable::open()->where('due_date', $today)->sum('amount')),
                'week' => Money::toCents(Payable::open()->whereBetween('due_date', [today()->addDay()->toDateString(), today()->addDays(7)->toDateString()])->sum('amount')),
                'paid' => Money::toCents(Payable::where('status', Payable::PAID)->whereBetween('paid_at', [$start, $end])->sum('amount')),
            ],
        ]);
    }
}
