<?php

namespace App\Livewire\Reports;

use App\Livewire\Concerns\HandlesBusinessRules;
use App\Models\User;
use App\Services\CommissionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

#[Title('Comissões')]
class Commissions extends Component
{
    use HandlesBusinessRules;

    /** Mês no formato AAAA-MM. */
    #[Url(as: 'mes')]
    public string $month = '';

    public string $dueDate = '';

    /** @var array<int, string> percentuais em edição, por vendedor */
    public array $percents = [];

    public function mount(): void
    {
        abort_unless(Auth::user()->canAny(['comissoes.ver', 'comissoes.ver_propria']), 403);

        if (! preg_match('/^\d{4}-\d{2}$/', $this->month)) {
            $this->month = now()->format('Y-m');
        }
    }

    private function monthDate(): Carbon
    {
        try {
            return Carbon::createFromFormat('!Y-m', $this->month)->startOfMonth();
        } catch (Throwable) {
            return now()->startOfMonth();
        }
    }

    public function savePercent(int $userId, CommissionService $commissions): void
    {
        Gate::authorize('comissoes.ver');
        $raw = str_replace(',', '.', trim($this->percents[$userId] ?? ''));

        if ($raw !== '' && ! is_numeric($raw)) {
            $this->addError("percents.{$userId}", 'Informe um número.');

            return;
        }

        $this->attempt(
            fn () => $commissions->setPercent(User::findOrFail($userId), $raw === '' ? null : (float) $raw, Auth::user()),
            'Percentual salvo. Vale para os meses ainda não fechados.',
        );
    }

    public function close(CommissionService $commissions): void
    {
        Gate::authorize('comissoes.fechar');

        try {
            $due = Carbon::createFromFormat('!Y-m-d', $this->dueDate);
        } catch (Throwable) {
            $this->addError('dueDate', 'Informe o vencimento do pagamento.');

            return;
        }

        $count = 0;
        if ($this->attempt(function () use ($commissions, $due, &$count) {
            $count = $commissions->close($this->monthDate(), $due, Auth::user());
        }, 'Mês fechado.')) {
            session()->now('success', "Mês fechado: {$count} vendedor(es). As comissões foram lançadas em Contas a pagar.");
        }
    }

    public function reopen(CommissionService $commissions): void
    {
        Gate::authorize('comissoes.fechar');
        $this->attempt(fn () => $commissions->reopen($this->monthDate(), Auth::user()), 'Fechamento desfeito. As contas a pagar das comissões foram canceladas.');
    }

    public function render(CommissionService $commissions): View
    {
        $user = Auth::user();
        $month = $this->monthDate();
        $seeAll = $user->can('comissoes.ver');
        $rows = $commissions->report($month, $seeAll ? null : $user->id);

        foreach ($rows as $row) {
            $this->percents[$row->user->id] ??= $row->user->commission_percent === null ? '' : (string) (float) $row->user->commission_percent;
        }

        if ($this->dueDate === '') {
            $this->dueDate = $month->copy()->addMonthNoOverflow()->day(5)->toDateString();
        }

        return view('livewire.reports.commissions', [
            'rows' => $rows,
            'seeAll' => $seeAll,
            'closed' => $rows->contains(fn ($row) => $row->closing !== null),
            'canClose' => $user->can('comissoes.fechar') && $month->lt(now()->startOfMonth()),
            'monthLabel' => $month->locale('pt_BR')->translatedFormat('F \d\e Y'),
        ]);
    }
}
