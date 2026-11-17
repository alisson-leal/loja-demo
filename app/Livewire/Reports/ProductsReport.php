<?php

namespace App\Livewire\Reports;

use App\Livewire\Concerns\HasReportPeriod;
use App\Services\FinanceReports;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Produtos e estoque')]
class ProductsReport extends Component
{
    use HasReportPeriod;

    #[Url(as: 'parados_ha')]
    public int $stalledDays = 90;

    public function mount(): void
    {
        Gate::authorize('relatorios.produtos');
        $this->initPeriod();
    }

    public function render(FinanceReports $reports): View
    {
        [$from, $to, $error] = $this->period();
        $days = max(15, min(365, $this->stalledDays));

        return view('livewire.reports.products-report', [
            'rangeError' => $error,
            'periodFrom' => $from,
            'periodTo' => $to,
            'top' => $reports->topProducts($from, $to),
            'stalled' => $reports->stalledStock($days),
            'days' => $days,
            'stock' => $reports->stockValue(),
            'showCost' => Auth::user()->can('produtos.ver_custo'),
        ]);
    }
}
