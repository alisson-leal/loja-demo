<?php

namespace App\Livewire;

use App\Models\Category;
use App\Models\User;
use App\Services\DashboardMetrics;
use App\Services\SalesMetrics;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

#[Title('Painel')]
class Dashboard extends Component
{
    private const MAX_RANGE_DAYS = 366;

    #[Url(as: 'de')]
    public string $from = '';

    #[Url(as: 'ate')]
    public string $to = '';

    #[Url(as: 'usuario')]
    public string $userId = '';

    #[Url(as: 'categoria')]
    public string $categoryId = '';

    public function mount(): void
    {
        if ($this->from === '' || $this->to === '') {
            $this->setPeriod(30);
        }
    }

    public function setPeriod(int $days): void
    {
        $days = max(1, min($days, self::MAX_RANGE_DAYS));
        $this->from = today()->subDays($days - 1)->toDateString();
        $this->to = today()->toDateString();
    }

    public function render(DashboardMetrics $metrics, SalesMetrics $salesMetrics): View
    {
        $user = Auth::user();

        if (! $user->can('dashboard.ver')) {
            return view('livewire.dashboard', ['allowed' => false]);
        }

        [$from, $to, $rangeError] = $this->resolveRange();
        $userId = ctype_digit($this->userId) ? (int) $this->userId : null;
        $categoryId = ctype_digit($this->categoryId) ? (int) $this->categoryId : null;

        return view('livewire.dashboard', [
            'allowed' => true,
            'rangeError' => $rangeError,
            'summary' => $metrics->summary(),
            'totals' => $metrics->accessTotals($from, $to, $userId),
            'chart' => $metrics->loginsPerDay($from, $to, $userId),
            'recent' => $user->can('auditoria.ver') ? $metrics->recentActivity(8, $userId) : null,
            'users' => User::orderBy('name')->get(['id', 'name']),
            'canSeeSales' => $canSeeSales = $user->can('vendas.ver'),
            'sales' => $canSeeSales ? $salesMetrics->summary($from, $to, $userId) : null,
            'salesChart' => $canSeeSales ? $salesMetrics->perDay($from, $to, $userId) : null,
            'byMethod' => $canSeeSales ? $salesMetrics->byPaymentMethod($from, $to, $userId) : null,
            'topProducts' => $canSeeSales ? $salesMetrics->topProducts(8, $from, $to, $userId, $categoryId) : null,
            'recentSales' => $canSeeSales ? $salesMetrics->recent(6, $userId) : null,
            'customers' => $user->can('clientes.ver') ? $metrics->customerSummary($from, $to) : null,
            'canSeeStock' => $canSeeStock = $user->can('estoque.ver'),
            'stock' => $canSeeStock ? $metrics->stockSummary($categoryId) : null,
            'lowStock' => $canSeeStock ? $metrics->lowStockItems(8, $categoryId) : null,
            'categories' => ($canSeeStock || $canSeeSales) ? Category::ordered() : null,
        ]);
    }

    /** @return array{CarbonInterface, CarbonInterface, ?string} */
    private function resolveRange(): array
    {
        try {
            $from = Carbon::createFromFormat('!Y-m-d', $this->from);
            $to = Carbon::createFromFormat('!Y-m-d', $this->to);
        } catch (Throwable) {
            return [today()->subDays(29), today(), 'Período inválido. Exibindo os últimos 30 dias.'];
        }

        if ($from->gt($to) || $from->diffInDays($to) > self::MAX_RANGE_DAYS) {
            return [today()->subDays(29), today(), 'O período deve ter início antes do fim e até 1 ano. Exibindo os últimos 30 dias.'];
        }

        return [$from, $to, null];
    }
}
