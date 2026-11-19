<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Exceptions\BusinessRuleException;
use App\Models\ProductVariant;
use App\Models\StockEntry;
use App\Models\User;
use App\Models\VariantCostHistory;
use App\Support\CurrentStore;
use Illuminate\Support\Facades\DB;

class StockEntryService
{
    public function __construct(
        private readonly StockService $stock,
        private readonly VariantCostService $costs,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  list<array{variant_id: int, quantity: int, unit_cost: float|string|null, serials?: list<string>}>  $items
     */
    public function register(User $actor, ?int $supplierId, ?string $documentNumber, ?string $notes, array $items, ?int $storeId = null): StockEntry
    {
        if ($items === []) {
            throw new BusinessRuleException('Inclua ao menos um item na entrada.');
        }

        // Ordem fixa de bloqueio evita deadlock entre entradas simultâneas.
        usort($items, fn (array $a, array $b) => $a['variant_id'] <=> $b['variant_id']);

        $storeId ??= CurrentStore::id();

        return DB::transaction(function () use ($actor, $supplierId, $documentNumber, $notes, $items, $storeId) {
            $entry = StockEntry::create([
                'store_id' => $storeId,
                'supplier_id' => $supplierId,
                'document_number' => $documentNumber,
                'notes' => $notes,
                'user_id' => $actor->id,
            ]);

            $reason = $documentNumber ? "Entrada - documento {$documentNumber}" : "Entrada #{$entry->id}";

            foreach ($items as $item) {
                $variant = ProductVariant::findOrFail($item['variant_id']);

                $this->stock->register($variant, MovementType::Entry, $item['quantity'], $reason, $actor, $item['unit_cost'], $entry, storeId: $storeId, serials: $item['serials'] ?? []);

                if ($item['unit_cost'] !== null) {
                    $this->costs->change($variant->fresh(), $item['unit_cost'], VariantCostHistory::SOURCE_ENTRY, $actor, $supplierId);
                }
            }

            $this->audit->record('stock.entry', "{$reason} registrada com ".count($items).' item(ns).', $entry);

            return $entry;
        });
    }
}
