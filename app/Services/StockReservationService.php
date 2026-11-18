<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Exceptions\BusinessRuleException;
use App\Models\ProductVariant;
use App\Models\StockReservation;
use App\Models\User;
use App\Support\CurrentStore;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Reserva de estoque de uma loja para vendas que ainda não foram concluídas,
 * como a condicional e os pedidos da futura loja virtual. Repetir uma
 * operação não duplica o efeito, o que protege contra notificações repetidas.
 */
class StockReservationService
{
    public function __construct(private readonly StockService $stock) {}

    public function reserve(ProductVariant $variant, int $quantity, string $referenceKey, ?CarbonInterface $expiresAt = null, ?int $storeId = null): StockReservation
    {
        if ($quantity <= 0) {
            throw new BusinessRuleException('A quantidade reservada deve ser maior que zero.');
        }

        $storeId ??= CurrentStore::id();

        return DB::transaction(function () use ($variant, $quantity, $referenceKey, $expiresAt, $storeId) {
            $locked = ProductVariant::whereKey($variant->id)->lockForUpdate()->firstOrFail();

            $existing = StockReservation::where('product_variant_id', $locked->id)
                ->where('reference_key', $referenceKey)
                ->whereIn('status', [StockReservation::ACTIVE, StockReservation::CONSUMED])
                ->first();

            if ($existing) {
                return $existing;
            }

            $row = $this->stock->lockRow($locked, $storeId);

            if ($row->available() < $quantity) {
                throw new BusinessRuleException("Estoque indisponível nesta loja para o SKU {$locked->sku}: disponível {$row->available()}.");
            }

            $row->reserved += $quantity;
            $row->save();
            $locked->stock_reserved += $quantity;
            $locked->save();

            return StockReservation::create([
                'store_id' => $storeId,
                'product_variant_id' => $locked->id,
                'quantity' => $quantity,
                'status' => StockReservation::ACTIVE,
                'reference_key' => $referenceKey,
                'expires_at' => $expiresAt,
            ]);
        });
    }

    public function release(StockReservation $reservation, string $finalStatus = StockReservation::RELEASED): void
    {
        DB::transaction(function () use ($reservation, $finalStatus) {
            $this->closeIfActive($reservation, $finalStatus);
        });
    }

    /** Transforma a reserva em baixa definitiva do estoque. */
    public function consume(StockReservation $reservation, string $reason, ?User $actor = null): void
    {
        DB::transaction(function () use ($reservation, $reason, $actor) {
            $variant = $this->closeIfActive($reservation, StockReservation::CONSUMED);

            if ($variant) {
                $this->stock->register($variant, MovementType::Sale, $reservation->quantity, $reason, $actor, reference: $reservation, storeId: $reservation->store_id);
            }
        });
    }

    public function releaseExpired(): int
    {
        $due = StockReservation::where('status', StockReservation::ACTIVE)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($due as $reservation) {
            $this->release($reservation, StockReservation::EXPIRED);
        }

        return $due->count();
    }

    /** Retorna a variação bloqueada, ou null se a reserva já não estava ativa. */
    private function closeIfActive(StockReservation $reservation, string $finalStatus): ?ProductVariant
    {
        $variant = ProductVariant::whereKey($reservation->product_variant_id)->lockForUpdate()->firstOrFail();
        $current = StockReservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();

        if ($current->status !== StockReservation::ACTIVE) {
            return null;
        }

        $row = $this->stock->lockRow($variant, $current->store_id ?? CurrentStore::mainId());
        $row->reserved -= $current->quantity;
        $row->save();

        $variant->stock_reserved -= $current->quantity;
        $variant->save();

        $current->status = $finalStatus;
        $current->save();

        return $variant;
    }
}
