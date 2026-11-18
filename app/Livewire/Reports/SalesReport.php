<?php

namespace App\Livewire\Reports;

use App\Livewire\Concerns\HasReportPeriod;
use App\Livewire\Concerns\HasStoreFilter;
use App\Services\FinanceReports;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Relatório de vendas')]
class SalesReport extends Component
{
    use HasReportPeriod;
    use HasStoreFilter;

    public function mount(): void
    {
        Gate::authorize('relatorios.vendas');
        $this->initPeriod();
    }

    public function render(FinanceReports $reports): View
    {
        [$from, $to, $error] = $this->period();
        $reports = $reports->forStore($this->storeFilterId());

        return view('livewire.reports.sales-report', [
            'rangeError' => $error,
            'periodFrom' => $from,
            'periodTo' => $to,
            'storeOptions' => $this->storeFilterOptions(),
            'summary' => $reports->salesSummary($from, $to),
            'byMethod' => $reports->salesByPaymentMethod($from, $to),
            'bySeller' => $reports->salesBySeller($from, $to),
            'byCategory' => $reports->salesByCategory($from, $to),
            'showCost' => Auth::user()->can('produtos.ver_custo'),
        ]);
    }
}
