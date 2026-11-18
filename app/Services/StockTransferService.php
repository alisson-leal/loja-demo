<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Exceptions\BusinessRuleException;
use App\Models\ProductVariant;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Store;
use App\Models\User;
use App\Support\CurrentStore;
use Illuminate\Support\Facades\DB;

/**
 * Transferência entre lojas: no envio a mercadoria sai da origem; fica "a
 * caminho" (fora das duas lojas) até a loja de destino confirmar o recebimento.
 */
class StockTransferService
{
    public function __construct(
        private readonly StockService $stock,
        private readonly AuditLogger $audit,
    ) {}

    /** @param list<array{variant_id: int, quantity: int}> $items */
    public function send(User $actor, int $fromStoreId, int $toStoreId, array $items, ?string $notes): StockTransfer
    {
        $items = array_values(array_filter($items, fn (array $item) => $item['quantity'] > 0));

        if ($items === []) {
            throw new BusinessRuleException('Inclua ao menos um item.');
        }

        if ($fromStoreId === $toStoreId) {
            throw new BusinessRuleException('Escolha uma loja de destino diferente da origem.');
        }

        $this->assertCanUse($actor, $fromStoreId, 'enviar desta loja');

        if (! Store::active()->whereKey($toStoreId)->exists()) {
            throw new BusinessRuleException('Loja de destino inválida.');
        }

        usort($items, fn (array $a, array $b) => $a['variant_id'] <=> $b['variant_id']);

        return DB::transaction(function () use ($actor, $fromStoreId, $toStoreId, $items, $notes) {
            $transfer = StockTransfer::create(['from_store_id' => $fromStoreId, 'to_store_id' => $toStoreId, 'notes' => $notes, 'user_id' => $actor->id]);
            $reason = "Transferência #{$transfer->number()}";

            foreach ($items as $item) {
                $variant = ProductVariant::findOrFail($item['variant_id']);
                StockTransferItem::create(['stock_transfer_id' => $transfer->id, 'product_variant_id' => $variant->id, 'quantity' => $item['quantity']]);
                $this->stock->register($variant, MovementType::TransferOut, $item['quantity'], $reason, $actor, reference: $transfer, storeId: $fromStoreId);
            }

            $this->audit->record('transfer.sent', "{$reason} enviada para {$transfer->to->name}.", $transfer);

            return $transfer;
        });
    }

    public function receive(User $actor, StockTransfer $transfer): void
    {
        DB::transaction(function () use ($actor, $transfer) {
            $locked = $this->lockInTransit($transfer);
            $this->assertCanUse($actor, $locked->to_store_id, 'receber nesta loja');

            foreach ($locked->items()->orderBy('product_variant_id')->with('variant')->get() as $item) {
                $this->stock->register($item->variant, MovementType::TransferIn, $item->quantity, "Transferência #{$locked->number()}", $actor, reference: $locked, storeId: $locked->to_store_id);
            }

            $locked->forceFill(['status' => StockTransfer::RECEIVED, 'received_by' => $actor->id, 'received_at' => now()])->save();
            $this->audit->record('transfer.received', "Transferência #{$locked->number()} recebida em {$locked->to->name}.", $locked);
        });
    }

    /** Cancela antes de chegar: a mercadoria volta para a loja de origem. */
    public function cancel(User $actor, StockTransfer $transfer): void
    {
        DB::transaction(function () use ($actor, $transfer) {
            $locked = $this->lockInTransit($transfer);
            $this->assertCanUse($actor, $locked->from_store_id, 'cancelar transferências desta loja');

            foreach ($locked->items()->orderBy('product_variant_id')->with('variant')->get() as $item) {
                $this->stock->register($item->variant, MovementType::TransferIn, $item->quantity, "Cancelamento da transferência #{$locked->number()}", $actor, reference: $locked, storeId: $locked->from_store_id);
            }

            $locked->forceFill(['status' => StockTransfer::CANCELLED])->save();
            $this->audit->record('transfer.cancelled', "Transferência #{$locked->number()} cancelada.", $locked);
        });
    }

    private function lockInTransit(StockTransfer $transfer): StockTransfer
    {
        $locked = StockTransfer::with('to', 'from')->whereKey($transfer->id)->lockForUpdate()->firstOrFail();

        if ($locked->status !== StockTransfer::IN_TRANSIT) {
            throw new BusinessRuleException('Esta transferência já foi recebida ou cancelada.');
        }

        return $locked;
    }

    private function assertCanUse(User $actor, int $storeId, string $action): void
    {
        if (! $actor->can('estoque.transferir')) {
            throw new BusinessRuleException('Você não tem permissão para transferir mercadoria.');
        }

        if (! in_array($storeId, CurrentStore::allowedIds($actor), true)) {
            throw new BusinessRuleException("Você não trabalha na loja para {$action}.");
        }
    }
}
