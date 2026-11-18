<?php

namespace App\Services;

use App\Enums\CashMovementType;
use App\Exceptions\BusinessRuleException;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Receivable;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Crediário próprio da loja (carnê). As parcelas são recebíveis com a forma
 * de pagamento "Crediário"; o cliente paga na loja, com multa e juros se
 * atrasar, conforme definido na forma de pagamento.
 */
class CrediarioService
{
    public function __construct(
        private readonly CashService $cash,
        private readonly AuditLogger $audit,
    ) {}

    /** @return array{open: int, overdue: int, oldest_overdue_days: int, limit: ?int, available: ?int} */
    public function position(Customer $customer): array
    {
        $pending = Receivable::crediario()->pending()->whereHas('sale', fn ($q) => $q->where('customer_id', $customer->id))->get(['net_amount', 'due_date']);
        $overdue = $pending->filter(fn (Receivable $r) => $r->due_date->lt(today()));
        $open = $pending->sum(fn ($r) => Money::toCents($r->net_amount));
        $limit = $customer->credit_limit === null ? null : Money::toCents($customer->credit_limit);

        return [
            'open' => $open,
            'overdue' => $overdue->sum(fn ($r) => Money::toCents($r->net_amount)),
            'oldest_overdue_days' => $overdue->isEmpty() ? 0 : (int) $overdue->min('due_date')->diffInDays(today()),
            'limit' => $limit,
            'available' => $limit === null ? null : max(0, $limit - $open),
        ];
    }

    /** Confere se o cliente pode comprar este valor no crediário. Chamado na finalização da venda. */
    public function assertCanBuy(?int $customerId, int $cents, User $actor): void
    {
        if (! $actor->can('crediario.vender')) {
            throw new BusinessRuleException('Você não tem permissão para vender no crediário.');
        }

        $customer = $customerId ? Customer::find($customerId) : null;

        if (! $customer) {
            throw new BusinessRuleException('Para vender no crediário, identifique o cliente na venda.');
        }

        if ($actor->can('crediario.gerir')) {
            return;
        }

        $position = $this->position($customer);
        $blockDays = (int) config('loja.credit_block_overdue_days');

        if ($position['overdue'] > 0 && $position['oldest_overdue_days'] > $blockDays) {
            throw new BusinessRuleException("{$customer->name} tem parcela do crediário vencida há {$position['oldest_overdue_days']} dias. Peça a um gerente.");
        }

        if ($position['limit'] === null) {
            throw new BusinessRuleException("{$customer->name} não tem limite de crediário definido. Peça a um gerente.");
        }

        if ($cents > $position['available']) {
            throw new BusinessRuleException('A compra ultrapassa o limite de crediário disponível ('.Money::format($position['available'] / 100).'). Peça a um gerente.');
        }
    }

    /**
     * Multa (uma vez) e juros por dia de atraso, sobre o valor da parcela.
     *
     * @return array{days: int, fee: int, interest: int, total: int}
     */
    public function charges(Receivable $installment, ?CarbonInterface $on = null): array
    {
        $on = ($on ?? today())->copy()->startOfDay();
        $days = $installment->due_date->lt($on) ? (int) $installment->due_date->diffInDays($on) : 0;
        $value = Money::toCents($installment->net_amount);
        $method = $installment->method;

        $fee = $days > 0 ? (int) round($value * (float) $method->late_fee_percent / 100) : 0;
        $interest = $days > 0 ? (int) round($value * (float) $method->interest_percent / 100 / 30 * $days) : 0;

        return ['days' => $days, 'fee' => $fee, 'interest' => $interest, 'total' => $value + $fee + $interest];
    }

    /** Recebe a parcela no caixa (dinheiro ou Pix). Dispensar multa e juros exige permissão. */
    public function receive(Receivable $installment, int $methodId, bool $waiveCharges, User $actor): int
    {
        if (! $actor->can('crediario.receber')) {
            throw new BusinessRuleException('Você não tem permissão para receber parcelas.');
        }

        if ($waiveCharges && ! $actor->can('crediario.gerir')) {
            throw new BusinessRuleException('Só um gerente pode dispensar multa e juros.');
        }

        $method = PaymentMethod::where('is_active', true)->whereIn('type', PaymentMethod::CREDIARIO_PAYMENT_TYPES)->find($methodId);

        if (! $method) {
            throw new BusinessRuleException('Receba a parcela em dinheiro ou Pix.');
        }

        return DB::transaction(function () use ($installment, $method, $waiveCharges, $actor) {
            $locked = Receivable::with('method', 'sale')->whereKey($installment->id)->lockForUpdate()->firstOrFail();

            if (! $locked->method->isCrediario() || $locked->status !== Receivable::PENDING) {
                throw new BusinessRuleException('Esta parcela já foi recebida ou cancelada.');
            }

            $charges = $this->charges($locked);
            $total = $waiveCharges ? Money::toCents($locked->net_amount) : $charges['total'];
            $label = "Crediário venda #{$locked->sale->number()} parcela {$locked->installment}/{$locked->installments}";

            if ($method->isCash()) {
                $this->cash->registerManual($actor, CashMovementType::Income, $total, $label);
            }

            $locked->forceFill([
                'status' => Receivable::RECEIVED,
                'received_at' => today(),
                'received_amount' => Money::fromCents($total),
                'charges_waived' => $waiveCharges && $charges['total'] > Money::toCents($locked->net_amount),
                'received_method_id' => $method->id,
                'received_by' => $actor->id,
            ])->save();

            $this->audit->record('crediario.received', "{$label} recebida: ".Money::format($total / 100).($waiveCharges ? ' (encargos dispensados)' : '').'.', $locked);

            return $total;
        });
    }

    public function setLimit(Customer $customer, ?float $limit, User $actor): void
    {
        if (! $actor->can('crediario.gerir')) {
            throw new BusinessRuleException('Só um gerente define o limite de crediário.');
        }

        $customer->forceFill(['credit_limit' => $limit])->save();
        $this->audit->record('crediario.limit', "Limite de crediário de {$customer->name}: ".($limit === null ? 'sem limite definido' : Money::format($limit)).'.', $customer);
    }
}
