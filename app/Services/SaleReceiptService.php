<?php

namespace App\Services;

use App\Models\Sale;
use App\Support\Money;
use App\Support\Quantity;
use App\Support\WhatsApp;

class SaleReceiptService
{
    public function text(Sale $sale): string
    {
        $store = config('loja.store');
        $lines = ["*{$store['name']}*", "Comprovante da venda #{$sale->number()}", $sale->created_at->format('d/m/Y H:i'), ''];

        foreach ($sale->items as $item) {
            $lines[] = $item->isFractional()
                ? "{$item->formatQuantity()} {$item->description} (".Money::format($item->unit_price).Quantity::PER[$item->unit].') - '.Money::format($item->total)
                : "{$item->quantity}x {$item->description} - ".Money::format($item->total);
        }

        $lines[] = '';

        if ((float) $sale->discount_total > 0) {
            $lines[] = 'Desconto: '.Money::format($sale->discount_total);
        }

        $lines[] = '*Total: '.Money::format($sale->total).'*';
        $lines[] = '';
        $lines[] = 'Agradecemos a preferência!';

        return implode("\n", $lines);
    }

    /** Link que abre a conversa no WhatsApp com a mensagem pronta para o atendente enviar. */
    public function whatsappUrl(Sale $sale, ?string $phone): ?string
    {
        return WhatsApp::url($phone, $this->text($sale));
    }
}
