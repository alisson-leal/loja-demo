<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\ExpenseCategory;
use App\Models\Payable;
use App\Models\SaleReturn;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PayableService
{
    public const MAX_REPEAT = 60;

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Lança uma conta. Com $times > 1, cria uma série mensal a partir do primeiro
     * vencimento: dividindo o valor ($split, compra parcelada) ou repetindo o
     * valor inteiro (conta fixa, como aluguel).
     *
     * @param  array{description: string, expense_category_id: int, supplier_id: ?int, document: ?string, due_date: string, amount_cents: int, notes: ?string}  $data
     * @return list<Payable>
     */
    public function create(array $data, int $times, bool $split, User $actor): array
    {
        if ($data['amount_cents'] <= 0) {
            throw new BusinessRuleException('Informe um valor maior que zero.');
        }

        if ($times < 1 || $times > self::MAX_REPEAT) {
            throw new BusinessRuleException('Informe de 1 a '.self::MAX_REPEAT.' parcelas.');
        }

        if ($split && $data['amount_cents'] < $times) {
            throw new BusinessRuleException('O valor é pequeno demais para tantas parcelas.');
        }

        return DB::transaction(function () use ($data, $times, $split, $actor) {
            $first = Carbon::parse($data['due_date'])->startOfDay();
            $series = $times > 1 ? (string) Str::uuid() : null;
            $base = $split ? intdiv($data['amount_cents'], $times) : $data['amount_cents'];
            $created = [];

            for ($i = 1; $i <= $times; $i++) {
                $cents = $split && $i === 1 ? $data['amount_cents'] - $base * ($times - 1) : $base;

                $created[] = Payable::create([
                    'description' => $data['description'],
                    'expense_category_id' => $data['expense_category_id'],
                    'supplier_id' => $data['supplier_id'],
                    'document' => $data['document'],
                    // addMonthsNoOverflow: vencimento dia 31 vira o último dia dos meses mais curtos.
                    'due_date' => $first->copy()->addMonthsNoOverflow($i - 1),
                    'amount' => Money::fromCents($cents),
                    'series' => $series,
                    'installment' => $i,
                    'installments' => $times,
                    'notes' => $data['notes'],
                    'created_by' => $actor->id,
                ]);
            }

            $this->audit->record('finance.payable_created', "Conta a pagar lançada: {$data['description']}".($times > 1 ? " ({$times}x)" : '').'.', $created[0], [
                'valor' => Money::format($data['amount_cents'] / 100),
            ]);

            return $created;
        });
    }

    /** @param array{description: string, expense_category_id: int, supplier_id: ?int, document: ?string, due_date: string, amount_cents: int, notes: ?string} $data */
    public function update(Payable $payable, array $data): void
    {
        if (! $payable->isOpen()) {
            throw new BusinessRuleException('Só é possível alterar contas em aberto.');
        }

        if ($data['amount_cents'] <= 0) {
            throw new BusinessRuleException('Informe um valor maior que zero.');
        }

        $payable->fill([
            'description' => $data['description'],
            'expense_category_id' => $data['expense_category_id'],
            'supplier_id' => $data['supplier_id'],
            'document' => $data['document'],
            'due_date' => $data['due_date'],
            'amount' => Money::fromCents($data['amount_cents']),
            'notes' => $data['notes'],
        ])->save();

        $this->audit->record('finance.payable_updated', "Conta a pagar alterada: {$payable->description}.", $payable);
    }

    public function pay(Payable $payable, CarbonInterface $paidAt, User $actor): void
    {
        if ($paidAt->isFuture()) {
            throw new BusinessRuleException('A data do pagamento não pode ser futura.');
        }

        $this->transition($payable, Payable::OPEN, [
            'status' => Payable::PAID,
            'paid_at' => $paidAt->toDateString(),
            'paid_by' => $actor->id,
        ]);

        $this->audit->record('finance.payable_paid', "Conta paga: {$payable->description} (".Money::format($payable->amount).').', $payable);
    }

    /** Desfaz um pagamento registrado por engano. */
    public function reopen(Payable $payable): void
    {
        if ($payable->source_type !== null) {
            throw new BusinessRuleException('Este lançamento foi gerado por uma devolução e não pode ser reaberto.');
        }

        $this->transition($payable, Payable::PAID, ['status' => Payable::OPEN, 'paid_at' => null, 'paid_by' => null]);
        $this->audit->record('finance.payable_reopened', "Pagamento desfeito: {$payable->description}.", $payable);
    }

    public function cancel(Payable $payable, string $reason, User $actor): void
    {
        $this->transition($payable, Payable::OPEN, [
            'status' => Payable::CANCELLED,
            'cancelled_by' => $actor->id,
            'cancel_reason' => $reason,
        ]);

        $this->audit->record('finance.payable_cancelled', "Conta cancelada: {$payable->description} - {$reason}.", $payable);
    }

    /** Reembolso de devolução em dinheiro, Pix ou cartão: saída já paga no fluxo de caixa. */
    public function recordRefund(SaleReturn $return, string $label, int $cents, User $actor): void
    {
        if ($cents <= 0) {
            return;
        }

        $payable = new Payable([
            'description' => $label,
            'expense_category_id' => ExpenseCategory::refunds()->id,
            'due_date' => today(),
            'amount' => Money::fromCents($cents),
            'created_by' => $actor->id,
        ]);
        $payable->source()->associate($return);
        $payable->forceFill(['status' => Payable::PAID, 'paid_at' => today(), 'paid_by' => $actor->id])->save();
    }

    private function transition(Payable $payable, string $from, array $changes): void
    {
        $updated = Payable::whereKey($payable->id)->where('status', $from)->update($changes + ['updated_at' => now()]);

        if ($updated === 0) {
            throw new BusinessRuleException('A situação desta conta mudou. Atualize a página.');
        }

        $payable->refresh();
    }
}
