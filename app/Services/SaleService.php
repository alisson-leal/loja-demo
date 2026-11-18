<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Exceptions\BusinessRuleException;
use App\Models\PaymentMethod;
use App\Models\ProductVariant;
use App\Models\Receivable;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\StoreCredit;
use App\Models\User;
use App\Support\Money;
use App\Support\SaleCalculator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SaleService
{
    public function __construct(
        private readonly StockService $stock,
        private readonly CashService $cash,
        private readonly AuditLogger $audit,
        private readonly ReceivableService $receivables,
        private readonly PurchaseThanks $thanks,
        private readonly CrediarioService $crediario,
        private readonly ConsignmentService $consignments,
    ) {}

    /**
     * Registra a venda completa em uma única transação: itens, pagamentos,
     * baixa de estoque e dinheiro no caixa. Se qualquer parte falhar, nada é gravado.
     *
     * Os preços vêm sempre do banco, nunca da tela.
     *
     * @param  list<array{variant_id: int, quantity: int, discount: int}>  $cart  Valores em centavos.
     * @param  list<array{method_id: int, amount: int, credit_code?: ?string, installments?: int}>  $payments  Valores em centavos.
     */
    public function finalize(
        User $actor,
        array $cart,
        array $payments,
        ?int $customerId,
        int $orderDiscount,
        ?string $notes,
        string $token,
        ?int $consignmentId = null,
    ): Sale {
        if ($cart === []) {
            throw new BusinessRuleException('Inclua ao menos um item na venda.');
        }

        if ($existing = Sale::where('checkout_token', $token)->first()) {
            return $existing;
        }

        try {
            return DB::transaction(fn () => $this->persist($actor, $cart, $payments, $customerId, $orderDiscount, $notes, $token, $consignmentId));
        } catch (UniqueConstraintViolationException) {
            return Sale::where('checkout_token', $token)->firstOrFail();
        }
    }

    private function persist(User $actor, array $cart, array $payments, ?int $customerId, int $orderDiscount, ?string $notes, string $token, ?int $consignmentId): Sale
    {
        $session = $this->cash->lockOpenSession($actor, 'Abra seu caixa antes de vender.');

        // Peças da condicional que o cliente ficou: a reserva sai antes da baixa da venda.
        $consignment = $consignmentId ? $this->consignments->releaseForSale($consignmentId, $customerId) : null;

        // Ordem fixa de bloqueio evita deadlock entre vendas simultâneas.
        usort($cart, fn (array $a, array $b) => $a['variant_id'] <=> $b['variant_id']);

        $variants = $this->lockVariants($cart);

        $calculation = SaleCalculator::calculate(array_map(fn (array $line) => [
            'unit_price' => Money::toCents($variants[$line['variant_id']]->sale_price),
            'quantity' => $line['quantity'],
            'discount' => $line['discount'],
        ], $cart), $orderDiscount);

        $this->assertDiscountAllowed($actor, $calculation);

        $settled = $this->settlePayments($payments, $calculation['total'], $customerId, $actor);

        $sale = Sale::create([
            'checkout_token' => $token,
            'cash_session_id' => $session->id,
            'user_id' => $actor->id,
            'customer_id' => $customerId,
            'subtotal' => Money::fromCents($calculation['subtotal']),
            'discount_total' => Money::fromCents($calculation['discount_total']),
            'total' => Money::fromCents($calculation['total']),
            'change_amount' => Money::fromCents($settled['change']),
            'notes' => $notes,
        ]);

        $reason = "Venda #{$sale->number()}";

        foreach ($cart as $index => $line) {
            $variant = $variants[$line['variant_id']];

            SaleItem::create([
                'sale_id' => $sale->id,
                'product_variant_id' => $variant->id,
                'description' => $variant->fullName(),
                'sku' => $variant->sku,
                'quantity' => $line['quantity'],
                'unit_price' => $variant->sale_price,
                'unit_cost' => $variant->cost_price,
                'discount' => Money::fromCents($calculation['lines'][$index]['discount']),
                'total' => Money::fromCents($calculation['lines'][$index]['total']),
            ]);

            $this->stock->register($variant, MovementType::Sale, $line['quantity'], $reason, $actor, reference: $sale);
        }

        foreach ($settled['rows'] as $row) {
            SalePayment::create(['sale_id' => $sale->id] + $row);
        }

        $this->receivables->createForSale($sale);
        $this->thanks->queueAfterCommit($sale);

        if ($settled['cash_applied'] > 0) {
            $this->cash->recordSale($session, $settled['cash_applied'], $actor, $sale, $reason);
        }

        if ($consignment) {
            $this->consignments->attachSale($consignment, $sale);
        }

        return $sale;
    }

    /** @return Collection<int, ProductVariant> */
    private function lockVariants(array $cart): Collection
    {
        $ids = array_column($cart, 'variant_id');

        if (count($ids) !== count(array_unique($ids))) {
            throw new BusinessRuleException('O mesmo item foi incluído mais de uma vez.');
        }

        $variants = ProductVariant::with(['product', 'values.attribute'])
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($cart as $line) {
            $variant = $variants[$line['variant_id']] ?? null;

            if (! $variant || ! $variant->is_active || ! $variant->product->is_active) {
                throw new BusinessRuleException('Há um item indisponível para venda no carrinho.');
            }

            if ($line['quantity'] <= 0) {
                throw new BusinessRuleException('A quantidade deve ser maior que zero.');
            }
        }

        return $variants;
    }

    /** @param array{subtotal: int, discount_total: int} $calculation */
    private function assertDiscountAllowed(User $actor, array $calculation): void
    {
        if ($calculation['discount_total'] === 0) {
            return;
        }

        if (! $actor->can('pdv.desconto')) {
            throw new BusinessRuleException('Você não tem permissão para dar desconto.');
        }

        $percent = $calculation['discount_total'] / $calculation['subtotal'] * 100;
        $limit = (float) config('loja.max_discount_percent');

        if ($percent > $limit + 0.0001 && ! $actor->can('pdv.desconto_livre')) {
            throw new BusinessRuleException('O desconto ultrapassa o limite de '.rtrim(rtrim(number_format($limit, 2, ',', ''), '0'), ',').'%. Peça a um gerente.');
        }
    }

    /**
     * Confere os pagamentos e consome os vales-troca. Só dinheiro pode gerar troco.
     *
     * @return array{rows: list<array<string, mixed>>, change: int, cash_applied: int}
     */
    private function settlePayments(array $payments, int $total, ?int $customerId, User $actor): array
    {
        $crediarioCents = 0;

        if ($total > 0 && $payments === []) {
            throw new BusinessRuleException('Informe a forma de pagamento.');
        }

        $methods = PaymentMethod::whereIn('id', array_column($payments, 'method_id'))->get()->keyBy('id');
        $cashRows = 0;
        $cashReceived = 0;
        $nonCash = 0;

        foreach ($payments as $payment) {
            $method = $methods[$payment['method_id']] ?? null;

            if (! $method || ! $method->is_active) {
                throw new BusinessRuleException('Forma de pagamento indisponível.');
            }

            if ($payment['amount'] <= 0) {
                throw new BusinessRuleException('Informe um valor maior que zero em cada pagamento.');
            }

            $installments = (int) ($payment['installments'] ?? 1);

            if ($installments < 1 || ($installments > 1 && (! $method->allowsInstallments() || $installments > $method->max_installments))) {
                throw new BusinessRuleException("Número de parcelas inválido para {$method->name}.");
            }

            if ($method->isCash()) {
                $cashRows++;
                $cashReceived += $payment['amount'];
            } else {
                $nonCash += $payment['amount'];
            }

            if ($method->isCrediario()) {
                $crediarioCents += $payment['amount'];
            }
        }

        if ($crediarioCents > 0) {
            $this->crediario->assertCanBuy($customerId, $crediarioCents, $actor);
        }

        if ($cashRows > 1) {
            throw new BusinessRuleException('Informe o dinheiro em um único pagamento.');
        }

        if ($nonCash > $total) {
            throw new BusinessRuleException('Os pagamentos sem dinheiro não podem ultrapassar o total da venda.');
        }

        $paid = $nonCash + $cashReceived;

        if ($paid < $total) {
            throw new BusinessRuleException('Falta receber '.Money::format(($total - $paid) / 100).'.');
        }

        $change = $paid - $total;
        $cashApplied = $cashReceived - $change;
        $rows = [];

        foreach ($payments as $payment) {
            $method = $methods[$payment['method_id']];
            $isCash = $method->isCash();
            $creditId = $method->type === PaymentMethod::STORE_CREDIT
                ? $this->consumeCredit($payment['credit_code'] ?? null, $payment['amount'])
                : null;

            $rows[] = [
                'payment_method_id' => $method->id,
                'store_credit_id' => $creditId,
                'amount' => Money::fromCents($isCash ? $cashApplied : $payment['amount']),
                'received_amount' => $isCash ? Money::fromCents($payment['amount']) : null,
                'installments' => max(1, (int) ($payment['installments'] ?? 1)),
            ];
        }

        return ['rows' => $rows, 'change' => $change, 'cash_applied' => $cashApplied];
    }

    private function consumeCredit(?string $code, int $cents): int
    {
        $credit = StoreCredit::where('code', $code)->lockForUpdate()->first();

        if (! $credit || Money::toCents($credit->balance) < $cents) {
            throw new BusinessRuleException('Vale-troca inválido ou sem saldo suficiente.');
        }

        $credit->update(['balance' => Money::fromCents(Money::toCents($credit->balance) - $cents)]);

        return $credit->id;
    }

    /** Cancela a venda inteira: devolve estoque, dinheiro e saldo de vale-troca. */
    public function cancel(User $actor, Sale $sale, string $reason, ?User $authorizedBy = null): void
    {
        DB::transaction(function () use ($actor, $sale, $reason, $authorizedBy) {
            $locked = Sale::whereKey($sale->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isCompleted()) {
                throw new BusinessRuleException('Esta venda já foi cancelada.');
            }

            if ($locked->returns()->exists()) {
                throw new BusinessRuleException('Esta venda tem devoluções registradas e não pode ser cancelada.');
            }

            if (Receivable::crediario()->where('sale_id', $locked->id)->where('status', Receivable::RECEIVED)->exists()) {
                throw new BusinessRuleException('Esta venda tem parcelas do crediário já pagas e não pode ser cancelada. Registre uma devolução.');
            }

            $label = "Cancelamento da venda #{$locked->number()}";
            $cashCents = 0;

            foreach ($locked->payments()->with('method')->get() as $payment) {
                if ($payment->method->isCash()) {
                    $cashCents += Money::toCents($payment->amount);
                } elseif ($payment->store_credit_id) {
                    $credit = StoreCredit::whereKey($payment->store_credit_id)->lockForUpdate()->firstOrFail();
                    $credit->update(['balance' => Money::fromCents(Money::toCents($credit->balance) + Money::toCents($payment->amount))]);
                }
            }

            if ($cashCents > 0) {
                $this->cash->refund($actor, $cashCents, $label, $locked);
            }

            foreach ($locked->items()->orderBy('product_variant_id')->with('variant')->get() as $item) {
                $this->stock->register($item->variant, MovementType::SaleCancellation, $item->quantity, $label, $actor, $item->unit_cost, $locked);
            }

            $this->receivables->cancelForSale($locked);
            $this->thanks->cancelForSale($locked);

            $locked->forceFill([
                'status' => Sale::CANCELLED,
                'cancelled_by' => $actor->id,
                'cancel_authorized_by' => $authorizedBy?->id,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ])->save();

            $this->audit->record('sale.cancelled', "Venda #{$locked->number()} cancelada: {$reason}.", $locked, [
                'total' => $locked->total,
                'autorizado_por' => $authorizedBy?->name,
            ]);
        });
    }
}
