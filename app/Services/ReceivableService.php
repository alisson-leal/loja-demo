<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\PaymentMethod;
use App\Models\Receivable;
use App\Models\Sale;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Contas a receber das vendas. Cada pagamento vira uma ou mais parcelas com a
 * data em que o dinheiro cai e a taxa da maquininha, calculadas no momento
 * da venda (mudar a taxa depois não altera vendas antigas).
 */
class ReceivableService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** Chamado dentro da transação da venda. */
    public function createForSale(Sale $sale): void
    {
        $saleDate = $sale->created_at->copy()->startOfDay();

        foreach ($sale->payments()->with('method')->get() as $payment) {
            $method = $payment->method;
            $cents = Money::toCents($payment->amount);

            if ($method->type === PaymentMethod::STORE_CREDIT || $cents <= 0) {
                continue;
            }

            $installments = max(1, (int) $payment->installments);
            $fee = $method->feeFor($installments);
            $base = intdiv($cents, $installments);

            for ($i = 1; $i <= $installments; $i++) {
                // A sobra dos centavos fica na primeira parcela.
                $gross = $base + ($i === 1 ? $cents - $base * $installments : 0);
                $feeCents = (int) round($gross * $fee / 100);
                $due = $saleDate->copy()->addDays($method->settlement_days + 30 * ($i - 1));
                $immediate = $due->lte($saleDate);

                Receivable::create([
                    'sale_id' => $sale->id,
                    'sale_payment_id' => $payment->id,
                    'payment_method_id' => $method->id,
                    'installment' => $i,
                    'installments' => $installments,
                    'due_date' => $due,
                    'gross_amount' => Money::fromCents($gross),
                    'fee_amount' => Money::fromCents($feeCents),
                    'net_amount' => Money::fromCents($gross - $feeCents),
                    'status' => $immediate ? Receivable::RECEIVED : Receivable::PENDING,
                    'received_at' => $immediate ? $saleDate : null,
                ]);
            }
        }
    }

    /** Venda cancelada: o dinheiro é devolvido ou estornado, então nada mais entra. */
    public function cancelForSale(Sale $sale): void
    {
        Receivable::where('sale_id', $sale->id)->where('status', '!=', Receivable::CANCELLED)
            ->update(['status' => Receivable::CANCELLED, 'updated_at' => now()]);
    }

    /**
     * Confirma que o valor caiu na conta.
     *
     * @param  list<int>  $ids
     */
    public function confirm(array $ids, CarbonInterface $receivedAt, User $actor): int
    {
        if ($ids === []) {
            throw new BusinessRuleException('Selecione ao menos um recebimento.');
        }

        if ($receivedAt->isFuture()) {
            throw new BusinessRuleException('A data do recebimento não pode ser futura.');
        }

        return DB::transaction(function () use ($ids, $receivedAt, $actor) {
            $updated = Receivable::whereIn('id', $ids)->pending()->update([
                'status' => Receivable::RECEIVED,
                'received_at' => $receivedAt->toDateString(),
                'received_by' => $actor->id,
                'updated_at' => now(),
            ]);

            $this->audit->record('finance.received', "{$updated} recebimento(s) de cartão confirmado(s).", null, [
                'data' => $receivedAt->format('d/m/Y'),
            ]);

            return $updated;
        });
    }
}
