<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Exceptions\BusinessRuleException;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\StoreStock;
use App\Models\User;
use App\Support\CurrentStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Único ponto do sistema que altera o saldo físico. Cada alteração bloqueia a
 * variação e o saldo da loja e grava a movimentação na mesma transação. O
 * saldo de cada loja fica em store_stocks; a variação guarda o total da rede.
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
        ?int $storeId = null,
    ): StockMovement {
        if ($quantity <= 0) {
            throw new BusinessRuleException('A quantidade deve ser maior que zero.');
        }

        $storeId ??= CurrentStore::id();

        return DB::transaction(function () use ($variant, $type, $quantity, $reason, $actor, $unitCost, $reference, $allowNegative, $storeId) {
            $locked = ProductVariant::whereKey($variant->id)->lockForUpdate()->firstOrFail();
            $row = $this->lockRow($locked, $storeId);
            $delta = $type->isInbound() ? $quantity : -$quantity;

            if ($delta < 0 && $row->available() + $delta < 0
                && ! ($allowNegative && $actor?->can('estoque.negativo'))) {
                throw new BusinessRuleException(
                    "Estoque insuficiente para o SKU {$locked->sku} nesta loja: disponível {$row->available()}, solicitado {$quantity}."
                );
            }

            return $this->apply($locked, $row, $type, $delta, $reason, $actor, $unitCost, $reference);
        });
    }

    /**
     * Ajusta o saldo da loja para a quantidade contada. Reflete a realidade
     * física, por isso não passa pela trava de disponibilidade.
     *
     * @return array{expected: int, movement: ?StockMovement}
     */
    public function setCounted(ProductVariant $variant, int $counted, string $reason, ?User $actor, ?Model $reference = null, ?int $storeId = null): array
    {
        $storeId ??= CurrentStore::id();

        return DB::transaction(function () use ($variant, $counted, $reason, $actor, $reference, $storeId) {
            $locked = ProductVariant::whereKey($variant->id)->lockForUpdate()->firstOrFail();
            $row = $this->lockRow($locked, $storeId);
            $expected = $row->on_hand;
            $delta = $counted - $expected;

            return [
                'expected' => $expected,
                'movement' => $delta === 0
                    ? null
                    : $this->apply($locked, $row, MovementType::Inventory, $delta, $reason, $actor, null, $reference),
            ];
        });
    }

    /**
     * Saldo da loja, bloqueado. Na primeira vez em que a loja principal mexe na
     * variação, ela recebe o que não está em outras lojas (saldos de antes das filiais).
     * Chame só com a variação já bloqueada.
     */
    public function lockRow(ProductVariant $lockedVariant, int $storeId): StoreStock
    {
        $row = StoreStock::where('store_id', $storeId)->where('product_variant_id', $lockedVariant->id)->lockForUpdate()->first();

        if ($row) {
            return $row;
        }

        [$onHand, $reserved] = $storeId === CurrentStore::mainId()
            ? $this->remainder($lockedVariant, $storeId)
            : [0, 0];

        StoreStock::create(['store_id' => $storeId, 'product_variant_id' => $lockedVariant->id, 'on_hand' => $onHand, 'reserved' => $reserved]);

        return StoreStock::where('store_id', $storeId)->where('product_variant_id', $lockedVariant->id)->lockForUpdate()->firstOrFail();
    }

    /** @return array{0: int, 1: int} o que do total da variação não está nas outras lojas */
    public function remainder(ProductVariant $variant, int $storeId): array
    {
        $others = StoreStock::where('product_variant_id', $variant->id)->where('store_id', '!=', $storeId)
            ->selectRaw('COALESCE(SUM(on_hand), 0) as on_hand, COALESCE(SUM(reserved), 0) as reserved')->first();

        return [(int) $variant->stock_on_hand - (int) $others->on_hand, (int) $variant->stock_reserved - (int) $others->reserved];
    }

    private function apply(
        ProductVariant $locked,
        StoreStock $row,
        MovementType $type,
        int $delta,
        string $reason,
        ?User $actor,
        float|string|null $unitCost,
        ?Model $reference,
    ): StockMovement {
        $row->on_hand += $delta;
        $row->save();

        $locked->stock_on_hand += $delta;
        $locked->save();

        return StockMovement::create([
            'store_id' => $row->store_id,
            'product_variant_id' => $locked->id,
            'type' => $type,
            'quantity' => $delta,
            'balance_after' => $row->on_hand,
            'unit_cost' => $unitCost ?? $locked->cost_price,
            'reason' => $reason,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'user_id' => $actor?->id,
        ]);
    }
}
