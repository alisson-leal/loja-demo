<?php

namespace App\Livewire\Reports;

use App\Livewire\Concerns\HasReportPeriod;
use App\Livewire\Concerns\HasStoreFilter;
use App\Services\FinanceReports;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Resultado')]
class ResultReport extends Component
{
    use HasReportPeriod;
    use HasStoreFilter;

    public function mount(): void
    {
        Gate::authorize('relatorios.resultado');
        $this->initPeriod();
    }

    public function render(FinanceReports $reports): View
    {
        [$from, $to, $error] = $this->period();
        $reports = $reports->forStore($this->storeFilterId());

        return view('livewire.reports.result-report', [
            'rangeError' => $error,
            'periodFrom' => $from,
            'periodTo' => $to,
            'storeOptions' => $this->storeFilterOptions(),
            'r' => $reports->result($from, $to),
        ]);
    }
}
