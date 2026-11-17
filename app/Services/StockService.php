<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Exceptions\BusinessRuleException;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Único ponto do sistema que altera o saldo físico. Cada alteração bloqueia a
 * linha da variação e grava a movimentação na mesma transação.
 */
class StockService
{
    public function register(
        ProductVariant $variant,
        MovementType $type,
        int $quantity,
        string $reason,
        ?User $actor,
        float|string|null $unitCost = null,
        ?Model $reference = null,
        bool $allowNegative = false,
    ): StockMovement {
        if ($quantity <= 0) {
            throw new BusinessRuleException('A quantidade deve ser maior que zero.');
        }

        return DB::transaction(function () use ($variant, $type, $quantity, $reason, $actor, $unitCost, $reference, $allowNegative) {
            $locked = ProductVariant::whereKey($variant->id)->lockForUpdate()->firstOrFail();
            $delta = $type->isInbound() ? $quantity : -$quantity;

            if ($delta < 0 && $locked->available() + $delta < 0
                && ! ($allowNegative && $actor?->can('estoque.negativo'))) {
                throw new BusinessRuleException(
                    "Estoque insuficiente para o SKU {$locked->sku}: disponível {$locked->available()}, solicitado {$quantity}."
                );
            }

            return $this->apply($locked, $type, $delta, $reason, $actor, $unitCost, $reference);
        });
    }

    /**
     * Ajusta o saldo para a quantidade contada. Reflete a realidade física, por
     * isso não passa pela trava de disponibilidade. Retorna null se não houver diferença.
     *
     * @return array{expected: int, movement: ?StockMovement}
     */
    public function setCounted(ProductVariant $variant, int $counted, string $reason, ?User $actor, ?Model $reference = null): array
    {
        return DB::transaction(function () use ($variant, $counted, $reason, $actor, $reference) {
            $locked = ProductVariant::whereKey($variant->id)->lockForUpdate()->firstOrFail();
            $expected = $locked->stock_on_hand;
            $delta = $counted - $expected;

            return [
                'expected' => $expected,
                'movement' => $delta === 0
                    ? null
                    : $this->apply($locked, MovementType::Inventory, $delta, $reason, $actor, null, $reference),
            ];
        });
    }

    private function apply(
        ProductVariant $locked,
        MovementType $type,
        int $delta,
        string $reason,
        ?User $actor,
        float|string|null $unitCost,
        ?Model $reference,
    ): StockMovement {
        $locked->stock_on_hand += $delta;
        $locked->save();

        return StockMovement::create([
            'product_variant_id' => $locked->id,
            'type' => $type,
            'quantity' => $delta,
            'balance_after' => $locked->stock_on_hand,
            'unit_cost' => $unitCost ?? $locked->cost_price,
            'reason' => $reason,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'user_id' => $actor?->id,
        ]);
    }
}
