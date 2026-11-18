<?php

namespace App\Livewire\Finance;

use App\Livewire\Concerns\HandlesBusinessRules;
use App\Models\PaymentMethod;
use App\Models\Receivable;
use App\Services\CrediarioService;
use App\Support\Money;
use App\Support\WhatsApp;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Crediário')]
class Crediario extends Component
{
    use HandlesBusinessRules;
    use WithPagination;

    #[Url(as: 'ver')]
    public string $view = 'abertas';

    #[Url(as: 'cliente')]
    public string $search = '';

    public ?int $receivingId = null;

    public string $methodId = '';

    public bool $waive = false;

    public function mount(): void
    {
        abort_unless(Auth::user()->canAny(['crediario.receber', 'crediario.gerir', 'financeiro.ver']), 403);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['view', 'search'], true)) {
            $this->resetPage();
        }
    }

    public function startReceive(int $id): void
    {
        Gate::authorize('crediario.receber');
        $this->receivingId = $id;
        $this->methodId = (string) PaymentMethod::where('type', PaymentMethod::CASH)->where('is_active', true)->value('id');
        $this->waive = false;
    }

    public function receive(CrediarioService $crediario): void
    {
        Gate::authorize('crediario.receber');
        $total = 0;

        if ($this->attempt(function () use ($crediario, &$total) {
            $total = $crediario->receive(Receivable::findOrFail($this->receivingId), (int) $this->methodId, $this->waive, Auth::user());
        }, 'Parcela recebida.')) {
            session()->now('success', 'Parcela recebida: '.Money::format($total / 100).'.');
            $this->reset('receivingId', 'waive');
        }
    }

    public function render(CrediarioService $crediario): View
    {
        $today = today()->toDateString();
        $term = trim($this->search);

        $installments = Receivable::crediario()->with('sale.customer', 'method')
            ->when($this->view === 'abertas', fn (Builder $q) => $q->pending())
            ->when($this->view === 'vencidas', fn (Builder $q) => $q->pending()->where('due_date', '<', $today))
            ->when($this->view === 'semana', fn (Builder $q) => $q->pending()->whereBetween('due_date', [$today, today()->addDays(7)->toDateString()]))
            ->when($this->view === 'recebidas', fn (Builder $q) => $q->where('status', Receivable::RECEIVED))
            ->when($term !== '', fn (Builder $q) => $q->whereHas('sale.customer', fn (Builder $c) => $c->search($term)))
            ->orderBy($this->view === 'recebidas' ? 'received_at' : 'due_date', $this->view === 'recebidas' ? 'desc' : 'asc')
            ->orderBy('id')
            ->paginate(25);

        $pending = fn () => Receivable::crediario()->pending();

        return view('livewire.finance.crediario', [
            'installments' => $installments,
            'charges' => $installments->mapWithKeys(fn (Receivable $r) => [$r->id => $r->status === Receivable::PENDING ? $crediario->charges($r) : null]),
            'methods' => PaymentMethod::where('is_active', true)->whereIn('type', PaymentMethod::CREDIARIO_PAYMENT_TYPES)->orderBy('position')->get(['id', 'name']),
            'cards' => [
                'open' => Money::toCents($pending()->sum('net_amount')),
                'overdue' => Money::toCents($pending()->where('due_date', '<', $today)->sum('net_amount')),
                'week' => Money::toCents($pending()->whereBetween('due_date', [$today, today()->addDays(7)->toDateString()])->sum('net_amount')),
                'received' => Money::toCents(Receivable::crediario()->where('status', Receivable::RECEIVED)->where('received_at', '>=', today()->startOfMonth()->toDateString())->sum('received_amount')),
            ],
            'reminder' => fn (Receivable $r) => WhatsApp::url(
                $r->sale->customer?->whatsappNumber(),
                "Olá, {$r->sale->customer?->firstName()}! A parcela {$r->installment}/{$r->installments} do seu crediário na ".config('loja.store.name')
                ." venceu em {$r->due_date->format('d/m')}, no valor de ".Money::format($r->net_amount).'. Pode passar na loja ou pagar por Pix?',
            ),
        ]);
    }
}
