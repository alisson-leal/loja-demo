<?php

namespace App\Livewire\Finance;

use App\Livewire\Concerns\HandlesBusinessRules;
use App\Models\PaymentMethod;
use App\Models\Receivable;
use App\Services\ReceivableService;
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

#[Title('Recebimentos de cartão')]
class Receivables extends Component
{
    use HandlesBusinessRules;
    use WithPagination;

    /** pendentes até hoje | futuros | recebidos */
    #[Url(as: 'ver')]
    public string $view = 'ate_hoje';

    #[Url(as: 'forma')]
    public string $methodId = '';

    /** @var list<int|string> */
    public array $selected = [];

    public string $receivedDate = '';

    public function mount(): void
    {
        Gate::authorize('financeiro.ver');
        $this->receivedDate = today()->toDateString();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['view', 'methodId'], true)) {
            $this->resetPage();
            $this->selected = [];
        }
    }

    public function selectAllDue(): void
    {
        $this->selected = $this->baseQuery()->pending()->where('due_date', '<=', today()->toDateString())
            ->pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    public function confirm(ReceivableService $receivables): void
    {
        Gate::authorize('financeiro.receber');

        try {
            $date = Carbon::createFromFormat('!Y-m-d', $this->receivedDate);
        } catch (Throwable) {
            $this->addError('receivedDate', 'Informe a data do recebimento.');

            return;
        }

        $count = 0;
        $ids = array_values(array_map('intval', array_filter($this->selected, fn ($id) => ctype_digit((string) $id))));

        if ($this->attempt(function () use ($receivables, $ids, $date, &$count) {
            $count = $receivables->confirm($ids, $date, Auth::user());
        }, 'Recebimentos confirmados.')) {
            session()->now('success', "{$count} recebimento(s) confirmado(s).");
            $this->selected = [];
        }
    }

    private function baseQuery(): Builder
    {
        return Receivable::query()
            ->when(ctype_digit($this->methodId), fn (Builder $q) => $q->where('payment_method_id', (int) $this->methodId));
    }

    public function render(): View
    {
        $today = today()->toDateString();

        $query = $this->baseQuery()->with('method:id,name', 'sale:id,created_at,status')
            ->when($this->view === 'ate_hoje', fn (Builder $q) => $q->pending()->where('due_date', '<=', $today)->orderBy('due_date'))
            ->when($this->view === 'futuros', fn (Builder $q) => $q->pending()->where('due_date', '>', $today)->orderBy('due_date'))
            ->when($this->view === 'recebidos', fn (Builder $q) => $q->where('status', Receivable::RECEIVED)
                ->whereHas('method', fn (Builder $m) => $m->whereIn('type', [PaymentMethod::DEBIT, PaymentMethod::CREDIT, PaymentMethod::OTHER]))
                ->orderByDesc('received_at'))
            ->orderBy('id');

        $pending = fn () => Receivable::pending();

        return view('livewire.finance.receivables', [
            'receivables' => $query->paginate(30),
            'methods' => PaymentMethod::whereIn('type', [PaymentMethod::DEBIT, PaymentMethod::CREDIT, PaymentMethod::PIX, PaymentMethod::OTHER])->orderBy('position')->get(['id', 'name']),
            'cards' => [
                'late' => Money::toCents($pending()->where('due_date', '<', $today)->sum('net_amount')),
                'today' => Money::toCents($pending()->where('due_date', $today)->sum('net_amount')),
                'week' => Money::toCents($pending()->whereBetween('due_date', [today()->addDay()->toDateString(), today()->addDays(7)->toDateString()])->sum('net_amount')),
                'month' => Money::toCents($pending()->whereBetween('due_date', [today()->addDay()->toDateString(), today()->addDays(30)->toDateString()])->sum('net_amount')),
            ],
        ]);
    }
}
