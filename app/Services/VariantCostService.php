<?php

namespace App\Services;

use App\Models\ProductVariant;
use App\Models\User;
use App\Models\VariantCostHistory;
use App\Support\Money;

class VariantCostService
{
    /** Atualiza o custo da variação e registra o histórico se o valor mudou. */
    public function change(ProductVariant $variant, float|string $newCost, string $source, ?User $actor, ?int $supplierId = null): void
    {
        $previous = $variant->cost_price;

        if (Money::equals($previous, $newCost)) {
            return;
        }

        $variant->cost_price = $newCost;
        $variant->save();

        VariantCostHistory::create([
            'product_variant_id' => $variant->id,
            'previous_cost' => $previous ?? 0,
            'new_cost' => $newCost,
            'source' => $source,
            'supplier_id' => $supplierId,
            'user_id' => $actor?->id,
        ]);
    }
}
