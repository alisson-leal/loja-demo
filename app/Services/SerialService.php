<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Exceptions\BusinessRuleException;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SerialNumber;
use App\Models\SerialNumberEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Números de série das unidades em estoque. Chamado pelo StockService na mesma
 * transação da movimentação: o saldo da loja e a quantidade de unidades "em
 * estoque" andam sempre juntos.
 */
class SerialService
{
    private const PATTERN = '/^[A-Z0-9][A-Z0-9._\/-]{0,59}$/';

    /**
     * Separa os números digitados (um por linha, ou por vírgula, ponto e vírgula
     * ou espaço). Maiúsculas, sem repetição.
     *
     * @param  string|list<string>|null  $input
     * @return list<string>
     */
    public static function parse(string|array|null $input): array
    {
        $parts = is_array($input) ? $input : preg_split('/[\s,;]+/', (string) $input);
        $serials = [];

        foreach ($parts as $part) {
            $serial = Str::upper(trim((string) $part));

            if ($serial === '') {
                continue;
            }

            if (! preg_match(self::PATTERN, $serial)) {
                throw new BusinessRuleException("Número de série inválido: {$serial}. Use letras, números, ponto, hífen ou barra (até 60 caracteres).");
            }

            if (in_array($serial, $serials, true)) {
                throw new BusinessRuleException("O número de série {$serial} foi informado mais de uma vez.");
            }

            $serials[] = $serial;
        }

        return $serials;
    }

    /** Conta os números digitados sem lançar erro (para a tela calcular a quantidade). */
    public static function count(string|array|null $input): int
    {
        try {
            return count(self::parse($input));
        } catch (BusinessRuleException) {
            return count(array_filter(is_array($input) ? $input : preg_split('/[\s,;]+/', (string) $input), fn ($part) => trim((string) $part) !== ''));
        }
    }

    /**
     * Aplica ao número de série o efeito da movimentação. Chame só dentro da
     * transação do StockService, com a variação já bloqueada.
     *
     * @param  list<string>  $serials
     */
    public function move(ProductVariant $variant, MovementType $type, array $serials, int $storeId, string $reason, ?User $actor, ?Model $reference): void
    {
        foreach ($serials as $serial) {
            $unit = SerialNumber::where('product_variant_id', $variant->id)->where('serial', $serial)->lockForUpdate()->first();

            $unit = match ($type) {
                MovementType::Sale => $this->leaveStock($unit, $serial, $storeId, SerialNumber::SOLD),
                MovementType::TransferOut => $this->leaveStock($unit, $serial, $storeId, SerialNumber::IN_TRANSIT),
                MovementType::Exit, MovementType::Loss, MovementType::Damage, MovementType::AdjustmentOut => $this->leaveStock($unit, $serial, $storeId, SerialNumber::REMOVED),
                MovementType::TransferIn => $this->arrive($unit, $serial, $storeId),
                MovementType::SaleCancellation => $this->backFromSale($unit, $serial, $storeId, $reference instanceof Sale ? $reference->id : null),
                MovementType::Return => $reference instanceof SaleReturn
                    ? $this->backFromSale($unit, $serial, $storeId, $reference->sale_id)
                    : $this->enter($unit, $variant, $serial, $storeId),
                MovementType::Entry, MovementType::AdjustmentIn => $this->enter($unit, $variant, $serial, $storeId),
                MovementType::Inventory => throw new BusinessRuleException('Produtos com número de série não são ajustados pelo inventário.'),
            };

            $this->log($unit, $type->value, $reason, $storeId, $actor, $reference);
        }
    }

    /** Liga as unidades vendidas ao item da venda (a garantia é consultada por ele). */
    public function linkToSaleItem(SaleItem $item, array $serials): void
    {
        SerialNumber::where('product_variant_id', $item->product_variant_id)->whereIn('serial', $serials)->update(['sale_item_id' => $item->id]);
    }

    /** Devolução que não volta ao estoque (defeito): a unidade sai de circulação. */
    public function retireReturned(SaleItem $item, array $serials, SaleReturn $return, string $reason, ?User $actor): void
    {
        foreach ($serials as $serial) {
            $unit = SerialNumber::where('product_variant_id', $item->product_variant_id)->where('serial', $serial)->lockForUpdate()->first();
            $unit = $this->ensureSoldIn($unit, $serial, $return->sale_id);
            $unit->update(['status' => SerialNumber::REMOVED, 'store_id' => $return->sale->store_id]);

            $this->log($unit, 'return_retired', "{$reason} (não voltou ao estoque)", $return->sale->store_id, $actor, $return);
        }
    }

    /**
     * Unidades da venda que ainda estão com o cliente (podem ser devolvidas).
     *
     * @return list<string>
     */
    public function stillSold(SaleItem $item): array
    {
        return SerialNumber::where('sale_item_id', $item->id)->where('status', SerialNumber::SOLD)
            ->orderBy('serial')->pluck('serial')->all();
    }

    private function leaveStock(?SerialNumber $unit, string $serial, int $storeId, string $status): SerialNumber
    {
        if (! $unit || $unit->status !== SerialNumber::IN_STOCK || $unit->store_id !== $storeId) {
            throw new BusinessRuleException("O número de série {$serial} não está no estoque desta loja.");
        }

        $unit->update(['status' => $status, 'store_id' => $status === SerialNumber::IN_TRANSIT ? $unit->store_id : $storeId]);

        return $unit;
    }

    private function arrive(?SerialNumber $unit, string $serial, int $storeId): SerialNumber
    {
        if (! $unit || $unit->status !== SerialNumber::IN_TRANSIT) {
            throw new BusinessRuleException("O número de série {$serial} não está em transferência.");
        }

        $unit->update(['status' => SerialNumber::IN_STOCK, 'store_id' => $storeId]);

        return $unit;
    }

    private function backFromSale(?SerialNumber $unit, string $serial, int $storeId, ?int $saleId): SerialNumber
    {
        $unit = $this->ensureSoldIn($unit, $serial, $saleId);
        $unit->update(['status' => SerialNumber::IN_STOCK, 'store_id' => $storeId]);

        return $unit;
    }

    private function ensureSoldIn(?SerialNumber $unit, string $serial, ?int $saleId): SerialNumber
    {
        if (! $unit || $unit->status !== SerialNumber::SOLD || $saleId === null || $unit->saleItem?->sale_id !== $saleId) {
            throw new BusinessRuleException("O número de série {$serial} não consta como vendido nesta venda.");
        }

        return $unit;
    }

    /** Entrada: número novo, ou uma unidade que tinha saído do estoque (perda encontrada, conserto). */
    private function enter(?SerialNumber $unit, ProductVariant $variant, string $serial, int $storeId): SerialNumber
    {
        if (! $unit) {
            return SerialNumber::create([
                'product_variant_id' => $variant->id,
                'serial' => $serial,
                'status' => SerialNumber::IN_STOCK,
                'store_id' => $storeId,
            ]);
        }

        if ($unit->status === SerialNumber::SOLD) {
            $number = $unit->saleItem?->sale?->number();

            throw new BusinessRuleException("O número de série {$serial} foi vendido".($number ? " na venda #{$number}" : '').': registre a devolução pela venda.');
        }

        if ($unit->status !== SerialNumber::REMOVED) {
            throw new BusinessRuleException("O número de série {$serial} já está no estoque.");
        }

        $unit->update(['status' => SerialNumber::IN_STOCK, 'store_id' => $storeId]);

        return $unit;
    }

    private function log(SerialNumber $unit, string $type, string $description, ?int $storeId, ?User $actor, ?Model $reference): void
    {
        SerialNumberEvent::create([
            'serial_number_id' => $unit->id,
            'type' => $type,
            'description' => Str::limit($description, 250),
            'store_id' => $storeId,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'user_id' => $actor?->id,
        ]);
    }
}
