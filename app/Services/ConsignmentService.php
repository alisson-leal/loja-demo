<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Consignment;
use App\Models\ConsignmentItem;
use App\Models\Customer;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\StockReservation;
use App\Models\User;
use App\Support\CurrentStore;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Venda condicional ("malinha"). As peças saem reservadas: continuam no
 * estoque da loja, mas não podem ser vendidas a outra pessoa. No acerto, o
 * que volta é liberado; o que o cliente ficou segue reservado até o caixa.
 */
class ConsignmentService
{
    public function __construct(
        private readonly StockReservationService $reservations,
        private readonly AuditLogger $audit,
    ) {}

    /** @param list<array{variant_id: int, quantity: int}> $items */
    public function open(User $actor, int $customerId, array $items, CarbonInterface $dueDate, ?string $notes): Consignment
    {
        $items = array_values(array_filter($items, fn (array $item) => $item['quantity'] > 0));

        if ($items === []) {
            throw new BusinessRuleException('Inclua ao menos uma peça.');
        }

        if ($dueDate->lt(today())) {
            throw new BusinessRuleException('O prazo de devolução não pode estar no passado.');
        }

        $customer = Customer::find($customerId) ?? throw new BusinessRuleException('Escolha o cliente.');
        usort($items, fn (array $a, array $b) => $a['variant_id'] <=> $b['variant_id']);

        return DB::transaction(function () use ($actor, $customer, $items, $dueDate, $notes) {
            $consignment = Consignment::create([
                'store_id' => CurrentStore::id(),
                'customer_id' => $customer->id,
                'user_id' => $actor->id,
                'due_date' => $dueDate->toDateString(),
                'notes' => $notes,
            ]);

            foreach ($items as $item) {
                $variant = ProductVariant::with(['product', 'values.attribute'])->findOrFail($item['variant_id']);
                $line = ConsignmentItem::create([
                    'consignment_id' => $consignment->id,
                    'product_variant_id' => $variant->id,
                    'description' => $variant->fullName(),
                    'unit_price' => $variant->sale_price,
                    'quantity' => $item['quantity'],
                ]);

                $this->reservations->reserve($variant, $item['quantity'], $this->key($line), storeId: $consignment->store_id);
            }

            $this->audit->record('consignment.opened', "Condicional #{$consignment->number()} aberta para {$customer->name}.", $consignment);

            return $consignment;
        });
    }

    /**
     * Acerto: informa quantas peças de cada item voltaram. O resto fica com o
     * cliente e deve ser pago no caixa.
     *
     * @param  array<int, int>  $returned  id do item => quantidade devolvida
     * @return int peças que o cliente ficou
     */
    public function settle(Consignment $consignment, array $returned, User $actor): int
    {
        return DB::transaction(function () use ($consignment, $returned, $actor) {
            $locked = Consignment::with('items')->whereKey($consignment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== Consignment::OPEN) {
                throw new BusinessRuleException('O acerto desta condicional já foi feito.');
            }

            $kept = 0;

            foreach ($locked->items as $item) {
                $back = (int) ($returned[$item->id] ?? 0);

                if ($back < 0 || $back > $item->quantity) {
                    throw new BusinessRuleException("Quantidade devolvida inválida em {$item->description}.");
                }

                $this->releaseReservation($this->key($item));

                $keep = $item->quantity - $back;
                $item->forceFill(['returned_quantity' => $back, 'kept_quantity' => $keep])->save();

                if ($keep > 0) {
                    $this->reservations->reserve($item->variant, $keep, $this->key($item, 'venda'), storeId: $locked->store_id);
                    $kept += $keep;
                }
            }

            $locked->forceFill($kept > 0
                ? ['status' => Consignment::AWAITING_SALE]
                : ['status' => Consignment::CLOSED, 'closed_at' => now(), 'closed_by' => $actor->id])->save();

            $this->audit->record('consignment.settled', "Acerto da condicional #{$locked->number()}: {$kept} peça(s) ficaram com o cliente.", $locked);

            return $kept;
        });
    }

    /** O cliente desistiu das peças que ia levar: tudo volta para o estoque disponível. */
    public function closeWithoutSale(Consignment $consignment, User $actor): void
    {
        DB::transaction(function () use ($consignment, $actor) {
            $locked = Consignment::with('items')->whereKey($consignment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== Consignment::AWAITING_SALE) {
                throw new BusinessRuleException('Esta condicional não está aguardando pagamento.');
            }

            foreach ($locked->items as $item) {
                $this->releaseReservation($this->key($item, 'venda'));
                $item->forceFill(['returned_quantity' => $item->quantity, 'kept_quantity' => 0])->save();
            }

            $locked->forceFill(['status' => Consignment::CLOSED, 'closed_at' => now(), 'closed_by' => $actor->id])->save();
            $this->audit->record('consignment.closed', "Condicional #{$locked->number()} encerrada sem venda.", $locked);
        });
    }

    /** Chamado pela venda, na mesma transação: tira a reserva das peças para a baixa da venda. */
    public function releaseForSale(int $consignmentId, ?int $customerId, ?int $storeId = null): Consignment
    {
        $locked = Consignment::with('items')->whereKey($consignmentId)->lockForUpdate()->firstOrFail();

        if ($locked->status !== Consignment::AWAITING_SALE) {
            throw new BusinessRuleException('Esta condicional não está aguardando pagamento.');
        }

        if ($storeId !== null && $locked->store_id !== null && $storeId !== $locked->store_id) {
            throw new BusinessRuleException('A condicional é de outra loja: cobre no caixa da loja onde ela foi aberta.');
        }

        if ($customerId !== $locked->customer_id) {
            throw new BusinessRuleException('A venda da condicional precisa ser para o mesmo cliente.');
        }

        foreach ($locked->items as $item) {
            $this->releaseReservation($this->key($item, 'venda'), StockReservation::CONSUMED);
        }

        return $locked;
    }

    public function attachSale(Consignment $consignment, Sale $sale): void
    {
        $consignment->forceFill([
            'status' => Consignment::CLOSED,
            'sale_id' => $sale->id,
            'closed_at' => now(),
            'closed_by' => $sale->user_id,
        ])->save();

        $this->audit->record('consignment.sold', "Condicional #{$consignment->number()} paga na venda #{$sale->number()}.", $consignment);
    }

    /** Itens que o cliente ficou, no formato do carrinho do PDV. */
    public function cartFor(Consignment $consignment): array
    {
        return $consignment->items->where('kept_quantity', '>', 0)->map(fn (ConsignmentItem $item) => [
            'variant_id' => $item->product_variant_id,
            'quantity' => (string) $item->kept_quantity,
            'discount' => '',
        ])->values()->all();
    }

    private function key(ConsignmentItem $item, string $suffix = ''): string
    {
        return "condicional:{$item->consignment_id}:{$item->id}".($suffix !== '' ? ":{$suffix}" : '');
    }

    private function releaseReservation(string $key, string $status = StockReservation::RELEASED): void
    {
        foreach (StockReservation::where('reference_key', $key)->where('status', StockReservation::ACTIVE)->get() as $reservation) {
            $this->reservations->release($reservation, $status);
        }
    }
}
