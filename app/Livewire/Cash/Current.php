<?php

namespace App\Livewire\Cash;

use App\Enums\CashMovementType;
use App\Exceptions\BusinessRuleException;
use App\Livewire\Concerns\HandlesBusinessRules;
use App\Services\CashService;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Meu caixa')]
class Current extends Component
{
    use HandlesBusinessRules;

    public string $openingAmount = '0.00';

    public string $type = '';

    public string $amount = '';

    public string $reason = '';

    public string $countedAmount = '';

    public string $closingNotes = '';

    public function mount(): void
    {
        Gate::authorize('caixa.operar');
    }

    public function open(CashService $cash): void
    {
        Gate::authorize('caixa.operar');

        $this->validate(['openingAmount' => ['required', 'numeric', 'min:0', 'max:99999999.99']], [], ['openingAmount' => 'saldo inicial']);

        $this->attempt(fn () => $cash->open(Auth::user(), Money::toCents($this->openingAmount)), 'Caixa aberto.');
    }

    public function register(CashService $cash): void
    {
        Gate::authorize('caixa.operar');

        $validated = $this->validate([
            'type' => ['required', Rule::enum(CashMovementType::class)->only(CashMovementType::manualTypes())],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ], [], ['type' => 'tipo', 'amount' => 'valor', 'reason' => 'motivo']);

        $done = $this->attempt(
            fn () => $cash->registerManual(Auth::user(), CashMovementType::from($validated['type']), Money::toCents($validated['amount']), $validated['reason']),
            'Movimentação registrada.',
        );

        if ($done) {
            $this->reset('type', 'amount', 'reason');
        }
    }

    public function close(CashService $cash): void
    {
        Gate::authorize('caixa.operar');

        $this->validate([
            'countedAmount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'closingNotes' => ['nullable', 'string', 'max:255'],
        ], [], ['countedAmount' => 'valor contado', 'closingNotes' => 'observação']);

        $session = $cash->currentFor(Auth::user());

        if (! $session) {
            return;
        }

        try {
            $closed = $cash->close(Auth::user(), $session, Money::toCents($this->countedAmount), trim($this->closingNotes) ?: null);
        } catch (BusinessRuleException $exception) {
            session()->now('error', $exception->getMessage());

            return;
        }

        session()->flash('success', 'Caixa fechado.');
        $this->redirectRoute('cash.report', ['session' => $closed->id]);
    }

    public function render(CashService $cash): View
    {
        $session = $cash->currentFor(Auth::user());

        return view('livewire.cash.current', [
            'session' => $session,
            'expected' => $session ? $cash->expectedCents($session) : 0,
            'methodTotals' => $session ? $cash->totalsByMethod($session) : collect(),
            'movements' => $session ? $session->movements()->latest('id')->limit(30)->get() : collect(),
            'types' => CashMovementType::manualTypes(),
        ]);
    }
}
