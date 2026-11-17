<?php

namespace App\Livewire\Finance;

use App\Livewire\Concerns\HasReportPeriod;
use App\Services\FinanceReports;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Fluxo de caixa')]
class CashFlow extends Component
{
    use HasReportPeriod;

    #[Url(as: 'todos_os_dias')]
    public bool $allDays = false;

    public function mount(): void
    {
        Gate::authorize('financeiro.ver');
        $this->initPeriod();
    }

    public function render(FinanceReports $reports): View
    {
        [$from, $to, $error] = $this->period();
        $flow = $reports->cashFlow($from, $to);

        return view('livewire.finance.cash-flow', [
            'flow' => $flow,
            'days' => $this->allDays ? $flow['days'] : array_values(array_filter($flow['days'],
                fn (array $d) => $d['received'] || $d['expected'] || $d['paid'] || $d['open'])),
            'rangeError' => $error,
            'periodFrom' => $from,
            'periodTo' => $to,
        ]);
    }
}
