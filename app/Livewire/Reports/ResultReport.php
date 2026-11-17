<?php

namespace App\Livewire\Reports;

use App\Livewire\Concerns\HasReportPeriod;
use App\Services\FinanceReports;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Resultado')]
class ResultReport extends Component
{
    use HasReportPeriod;

    public function mount(): void
    {
        Gate::authorize('relatorios.resultado');
        $this->initPeriod();
    }

    public function render(FinanceReports $reports): View
    {
        [$from, $to, $error] = $this->period();

        return view('livewire.reports.result-report', [
            'rangeError' => $error,
            'periodFrom' => $from,
            'periodTo' => $to,
            'r' => $reports->result($from, $to),
        ]);
    }
}
