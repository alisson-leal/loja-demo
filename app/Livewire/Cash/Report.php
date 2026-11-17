<?php

namespace App\Livewire\Cash;

use App\Exceptions\BusinessRuleException;
use App\Models\CashSession;
use App\Services\CashService;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Relatório de caixa')]
class Report extends Component
{
    #[Locked]
    public int $sessionId;

    public string $countedAmount = '';

    public string $closingNotes = '';

    public function mount($session): void
    {
        $model = CashSession::findOrFail($session);
        abort_unless($model->isVisibleTo(Auth::user()), 403);
        $this->sessionId = $model->id;
    }

    /** Permite ao gerente fechar o caixa que um operador deixou aberto. */
    public function closeForOperator(CashService $cash): void
    {
        $this->validate([
            'countedAmount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'closingNotes' => ['nullable', 'string', 'max:255'],
        ], [], ['countedAmount' => 'valor contado', 'closingNotes' => 'observação']);

        try {
            $cash->close(Auth::user(), CashSession::findOrFail($this->sessionId), Money::toCents($this->countedAmount), trim($this->closingNotes) ?: null);
            session()->now('success', 'Caixa fechado.');
        } catch (BusinessRuleException $exception) {
            session()->now('error', $exception->getMessage());
        }
    }

    public function render(CashService $cash): View
    {
        $session = CashSession::with(['user:id,name', 'closer:id,name'])->findOrFail($this->sessionId);

        return view('livewire.cash.report', [
            'session' => $session,
            'expected' => $cash->expectedCents($session),
            'methodTotals' => $cash->totalsByMethod($session),
            'movements' => $session->movements()->with('user:id,name')->orderBy('id')->get(),
            'salesCount' => $session->sales()->where('status', 'completed')->count(),
        ]);
    }
}
