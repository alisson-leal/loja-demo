<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\CommissionClosing;
use App\Models\ExpenseCategory;
use App\Models\Payable;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Comissão = percentual do vendedor sobre as vendas líquidas do mês: vendas
 * concluídas por ele menos as devoluções dessas vendas feitas no mês. Vendas
 * canceladas não contam. No fechamento os valores ficam congelados e cada
 * vendedor ganha uma conta a pagar na categoria "Comissões".
 */
class CommissionService
{
    public function __construct(
        private readonly PayableService $payables,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return Collection<int, object{user: User, sales: int, returns: int, base: int, percent: float, amount: int, closing: ?CommissionClosing}>
     */
    public function report(CarbonInterface $month, ?int $onlyUserId = null): Collection
    {
        $start = CarbonImmutable::instance($month)->startOfMonth();
        $end = $start->endOfMonth();
        $closings = CommissionClosing::with('payable')->whereDate('period', $start->toDateString())->get()->keyBy('user_id');

        $sales = Sale::where('status', Sale::COMPLETED)->whereBetween('created_at', [$start, $end])
            ->groupBy('user_id')->selectRaw('user_id, SUM(total) as total')->pluck('total', 'user_id');
        $returns = SaleReturn::join('sales', 'sales.id', '=', 'sale_returns.sale_id')
            ->whereBetween('sale_returns.created_at', [$start, $end])
            ->groupBy('sales.user_id')->selectRaw('sales.user_id as user_id, SUM(sale_returns.total) as total')->pluck('total', 'user_id');

        $userIds = collect($sales->keys())->merge($returns->keys())->merge($closings->keys())
            ->merge(User::whereNotNull('commission_percent')->where('is_blocked', false)->pluck('id'))
            ->unique()
            ->when($onlyUserId, fn ($ids) => $ids->filter(fn ($id) => $id === $onlyUserId));

        return User::whereIn('id', $userIds)->orderBy('name')->get()->map(function (User $user) use ($sales, $returns, $closings) {
            if ($closing = $closings->get($user->id)) {
                return (object) [
                    'user' => $user,
                    'sales' => Money::toCents($closing->sales_total),
                    'returns' => Money::toCents($closing->returns_total),
                    'base' => Money::toCents($closing->base_total),
                    'percent' => (float) $closing->percent,
                    'amount' => Money::toCents($closing->amount),
                    'closing' => $closing,
                ];
            }

            $sold = Money::toCents($sales[$user->id] ?? 0);
            $returned = Money::toCents($returns[$user->id] ?? 0);
            $base = max(0, $sold - $returned);
            $percent = (float) ($user->commission_percent ?? 0);

            return (object) [
                'user' => $user,
                'sales' => $sold,
                'returns' => $returned,
                'base' => $base,
                'percent' => $percent,
                'amount' => (int) round($base * $percent / 100),
                'closing' => null,
            ];
        })->values();
    }

    public function setPercent(User $seller, ?float $percent, User $actor): void
    {
        if ($percent !== null && ($percent < 0 || $percent > 50)) {
            throw new BusinessRuleException('Informe um percentual entre 0 e 50.');
        }

        $before = $seller->commission_percent;
        $seller->forceFill(['commission_percent' => $percent])->save();

        $this->audit->record('commission.percent', "Comissão de {$seller->name} alterada para ".($percent === null ? 'nenhuma' : number_format($percent, 2, ',', '.').'%').'.', $seller, [
            'antes' => $before,
        ]);
    }

    /** Fecha o mês: congela os valores e lança uma conta a pagar por vendedor com comissão. */
    public function close(CarbonInterface $month, CarbonInterface $dueDate, User $actor): int
    {
        $start = CarbonImmutable::instance($month)->startOfMonth();

        if ($start->gte(now()->startOfMonth())) {
            throw new BusinessRuleException('Só é possível fechar meses que já terminaram.');
        }

        if (CommissionClosing::whereDate('period', $start->toDateString())->exists()) {
            throw new BusinessRuleException('Este mês já foi fechado.');
        }

        $rows = $this->report($start)->filter(fn ($row) => $row->amount > 0 || $row->sales > 0);

        if ($rows->isEmpty()) {
            throw new BusinessRuleException('Não há vendas com comissão neste mês.');
        }

        $label = $start->locale('pt_BR')->translatedFormat('F/Y');
        $category = ExpenseCategory::where('name', 'Comissões')->value('id') ?? ExpenseCategory::where('name', 'Salários e encargos')->value('id');

        return DB::transaction(function () use ($rows, $start, $dueDate, $actor, $label, $category) {
            foreach ($rows as $row) {
                $payable = null;

                if ($row->amount > 0) {
                    [$payable] = $this->payables->create([
                        'description' => "Comissão {$label} - {$row->user->name}",
                        'expense_category_id' => $category,
                        'supplier_id' => null,
                        'document' => null,
                        'due_date' => $dueDate->toDateString(),
                        'amount_cents' => $row->amount,
                        'notes' => 'Base '.Money::format($row->base / 100).' x '.number_format($row->percent, 2, ',', '.').'%.',
                    ], 1, false, $actor);
                }

                CommissionClosing::create([
                    'period' => $start->toDateString(),
                    'user_id' => $row->user->id,
                    'sales_total' => Money::fromCents($row->sales),
                    'returns_total' => Money::fromCents($row->returns),
                    'base_total' => Money::fromCents($row->base),
                    'percent' => $row->percent,
                    'amount' => Money::fromCents($row->amount),
                    'payable_id' => $payable?->id,
                    'closed_by' => $actor->id,
                ]);
            }

            $this->audit->record('commission.closed', "Comissões de {$label} fechadas: ".Money::format($rows->sum('amount') / 100).'.');

            return $rows->count();
        });
    }

    /** Desfaz o fechamento enquanto nenhuma comissão do mês foi paga. */
    public function reopen(CarbonInterface $month, User $actor): void
    {
        $start = CarbonImmutable::instance($month)->startOfMonth();
        $closings = CommissionClosing::with('payable')->whereDate('period', $start->toDateString())->get();

        if ($closings->isEmpty()) {
            throw new BusinessRuleException('Este mês não está fechado.');
        }

        if ($closings->contains(fn ($c) => $c->payable && $c->payable->status === Payable::PAID)) {
            throw new BusinessRuleException('Há comissões deste mês já pagas. Desfaça o pagamento em Contas a pagar antes de reabrir.');
        }

        DB::transaction(function () use ($closings, $actor, $start) {
            foreach ($closings as $closing) {
                if ($closing->payable && $closing->payable->isOpen()) {
                    $this->payables->cancel($closing->payable, 'Fechamento de comissão reaberto', $actor);
                }
                $closing->delete();
            }

            $this->audit->record('commission.reopened', 'Comissões de '.$start->locale('pt_BR')->translatedFormat('F/Y').' reabertas.');
        });
    }
}
