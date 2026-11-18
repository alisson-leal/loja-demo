<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Inventory;
use App\Models\InventoryItem;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\CurrentStore;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public function __construct(
        private readonly StockService $stock,
        private readonly AuditLogger $audit,
    ) {}

    public function open(User $actor, ?int $categoryId, ?string $notes): Inventory
    {
        return DB::transaction(function () use ($actor, $categoryId, $notes) {
            // Um inventário em contagem por loja.
            if (Inventory::where('status', Inventory::OPEN)->where('store_id', CurrentStore::id())->lockForUpdate()->exists()) {
                throw new BusinessRuleException('Já existe um inventário em contagem nesta loja. Conclua ou cancele antes de abrir outro.');
            }

            $variantIds = ProductVariant::operational()
                ->when($categoryId, fn ($query) => $query->inCategory($categoryId))
                ->pluck('id');

            if ($variantIds->isEmpty()) {
                throw new BusinessRuleException('Não há produtos ativos para este inventário.');
            }

            $inventory = Inventory::create([
                'store_id' => CurrentStore::id(),
                'status' => Inventory::OPEN,
                'category_id' => $categoryId,
                'notes' => $notes,
                'opened_by' => $actor->id,
            ]);

            $now = now();
            InventoryItem::insert($variantIds->map(fn (int $id) => [
                'inventory_id' => $inventory->id,
                'product_variant_id' => $id,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());

            $this->audit->record('inventory.opened', "Inventário #{$inventory->id} aberto com {$variantIds->count()} item(ns).", $inventory);

            return $inventory;
        });
    }

    public function setCount(InventoryItem $item, ?int $quantity): void
    {
        $this->assertOpen($item->inventory);

        if ($quantity !== null && $quantity < 0) {
            throw new BusinessRuleException('A quantidade contada não pode ser negativa.');
        }

        $item->update(['counted_quantity' => $quantity]);
    }

    /** Soma uma unidade ao item lido pelo leitor de código de barras. */
    public function scan(Inventory $inventory, string $code): InventoryItem
    {
        $this->assertOpen($inventory);

        $item = $inventory->items()
            ->whereHas('variant', fn ($query) => $query->where('barcode', $code)->orWhere('sku', $code))
            ->first();

        if (! $item) {
            throw new BusinessRuleException("O código {$code} não faz parte deste inventário.");
        }

        $item->update(['counted_quantity' => ($item->counted_quantity ?? 0) + 1]);

        return $item;
    }

    /** Aplica as diferenças dos itens contados. Itens sem contagem não são alterados. */
    public function finish(User $actor, Inventory $inventory): int
    {
        return DB::transaction(function () use ($actor, $inventory) {
            $locked = Inventory::whereKey($inventory->id)->lockForUpdate()->firstOrFail();
            $this->assertOpen($locked);

            $items = $locked->items()->whereNotNull('counted_quantity')->orderBy('product_variant_id')->with('variant')->get();

            if ($items->isEmpty()) {
                throw new BusinessRuleException('Conte ao menos um item antes de concluir.');
            }

            $adjusted = 0;

            foreach ($items as $item) {
                $result = $this->stock->setCounted($item->variant, $item->counted_quantity, "Inventário #{$locked->id}", $actor, $locked, $locked->store_id);
                $item->update(['expected_quantity' => $result['expected']]);
                $adjusted += $result['movement'] ? 1 : 0;
            }

            $locked->update(['status' => Inventory::FINISHED, 'closed_by' => $actor->id, 'closed_at' => now()]);

            $this->audit->record('inventory.finished', "Inventário #{$locked->id} concluído: {$items->count()} contado(s), {$adjusted} ajustado(s).", $locked);

            return $adjusted;
        });
    }

    public function cancel(User $actor, Inventory $inventory): void
    {
        DB::transaction(function () use ($actor, $inventory) {
            $locked = Inventory::whereKey($inventory->id)->lockForUpdate()->firstOrFail();
            $this->assertOpen($locked);

            $locked->update(['status' => Inventory::CANCELLED, 'closed_by' => $actor->id, 'closed_at' => now()]);

            $this->audit->record('inventory.cancelled', "Inventário #{$locked->id} cancelado.", $locked);
        });
    }

    private function assertOpen(Inventory $inventory): void
    {
        if (! $inventory->isOpen()) {
            throw new BusinessRuleException('Este inventário já foi encerrado.');
        }
    }
}
