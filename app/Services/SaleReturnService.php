<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Exceptions\BusinessRuleException;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\StoreCredit;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SaleReturnService
{
    private const CREDIT_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(
        private readonly StockService $stock,
        private readonly CashService $cash,
        private readonly AuditLogger $audit,
        private readonly PayableService $payables,
    ) {}

    /**
     * Devolução parcial ou total. Sem forma de pagamento, gera vale-troca, que o
     * cliente usa em uma nova venda; é assim que as trocas são registradas.
     *
     * @param  list<array{sale_item_id: int, quantity: int, restock: bool}>  $items
     */
    public function register(User $actor, Sale $sale, array $items, string $reason, ?int $refundMethodId, ?User $authorizedBy = null): SaleReturn
    {
        $items = array_values(array_filter($items, fn (array $item) => $item['quantity'] > 0));

        if ($items === []) {
            throw new BusinessRuleException('Informe a quantidade devolvida em ao menos um item.');
        }

        usort($items, fn (array $a, array $b) => $a['sale_item_id'] <=> $b['sale_item_id']);

        return DB::transaction(function () use ($actor, $sale, $items, $reason, $refundMethodId, $authorizedBy) {
            $locked = Sale::whereKey($sale->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isCompleted()) {
                throw new BusinessRuleException('Não é possível devolver itens de uma venda cancelada.');
            }

            $return = SaleReturn::create([
                'sale_id' => $locked->id,
                'user_id' => $actor->id,
                'authorized_by' => $authorizedBy?->id,
                'reason' => $reason,
                'total' => 0,
            ]);

            $label = "Devolução da venda #{$locked->number()}";
            $totalCents = 0;

            foreach ($items as $item) {
                $totalCents += $this->returnItem($locked, $return, $item, $label, $actor);
            }

            $this->settle($return, $locked, $totalCents, $refundMethodId, $label, $actor);

            $this->audit->record('sale.returned', "{$label}: ".Money::format($totalCents / 100)." - {$reason}.", $return, [
                'autorizado_por' => $authorizedBy?->name,
            ]);

            return $return->refresh();
        });
    }

    /** @param array{sale_item_id: int, quantity: int, restock: bool} $item */
    private function returnItem(Sale $sale, SaleReturn $return, array $item, string $label, User $actor): int
    {
        $saleItem = SaleItem::where('sale_id', $sale->id)->whereKey($item['sale_item_id'])->lockForUpdate()->with('variant')->firstOrFail();

        if ($item['quantity'] > $saleItem->returnableQuantity()) {
            throw new BusinessRuleException("Quantidade maior que a disponível para devolução em {$saleItem->description}.");
        }

        // Calculado sobre o acumulado: a soma das devoluções parciais é exatamente o valor pago.
        $paid = Money::toCents($saleItem->total);
        $before = (int) round($paid * $saleItem->returned_quantity / $saleItem->quantity);
        $after = (int) round($paid * ($saleItem->returned_quantity + $item['quantity']) / $saleItem->quantity);
        $amount = $after - $before;

        $saleItem->update(['returned_quantity' => $saleItem->returned_quantity + $item['quantity']]);

        $return->items()->create([
            'sale_item_id' => $saleItem->id,
            'quantity' => $item['quantity'],
            'amount' => Money::fromCents($amount),
            'restocked' => $item['restock'],
        ]);

        if ($item['restock']) {
            $this->stock->register($saleItem->variant, MovementType::Return, $item['quantity'], $label, $actor, $saleItem->unit_cost, $return, storeId: $sale->store_id);
        }

        return $amount;
    }

    private function settle(SaleReturn $return, Sale $sale, int $totalCents, ?int $refundMethodId, string $label, User $actor): void
    {
        $attributes = ['total' => Money::fromCents($totalCents)];

        if ($refundMethodId === null) {
            $attributes['store_credit_id'] = StoreCredit::create([
                'code' => $this->newCreditCode(),
                'initial_amount' => Money::fromCents($totalCents),
                'balance' => Money::fromCents($totalCents),
                'customer_id' => $sale->customer_id,
            ])->id;
        } else {
            $method = PaymentMethod::where('is_active', true)
                ->whereNotIn('type', [PaymentMethod::STORE_CREDIT, PaymentMethod::CREDIARIO])
                ->find($refundMethodId);

            if (! $method) {
                throw new BusinessRuleException('Forma de reembolso indisponível.');
            }

            $attributes['payment_method_id'] = $method->id;

            if ($method->isCash() && $totalCents > 0) {
                $this->cash->refund($actor, $totalCents, $label, $return);
            }

            // O reembolso sai do caixa da loja em qualquer meio: entra como saída paga no financeiro.
            $this->payables->recordRefund($return, "{$label} ({$method->name})", $totalCents, $actor);
        }

        $return->update($attributes);
    }

    private function newCreditCode(): string
    {
        do {
            $code = 'VT'.collect(range(1, 8))
                ->map(fn () => self::CREDIT_ALPHABET[random_int(0, strlen(self::CREDIT_ALPHABET) - 1)])
                ->implode('');
        } while (StoreCredit::where('code', $code)->exists());

        return Str::upper($code);
    }
}
