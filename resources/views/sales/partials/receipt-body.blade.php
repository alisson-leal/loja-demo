@php use App\Support\Money; $store = config('loja.store'); @endphp
<div style="font-family: Arial, Helvetica, sans-serif; color: #222; font-size: 14px; line-height: 1.4; max-width: 420px; margin: 0 auto;">
    <div style="text-align: center; margin-bottom: 12px;">
        <strong style="font-size: 20px;">{{ $store['name'] }}</strong><br>
        {{ $store['tagline'] }}<br>
        {{ $store['instagram'] }} · {{ $store['whatsapp'] }}
        @if (\App\Support\CurrentStore::multiple() && $sale->store)
            <br>{{ $sale->store->name }}@if ($sale->store->address) · {{ $sale->store->address }}@endif @if ($sale->store->phone) · {{ $sale->store->phone }}@endif
        @endif
    </div>
    <div style="text-align: center; border-top: 1px dashed #999; border-bottom: 1px dashed #999; padding: 6px 0; margin-bottom: 10px;">
        <strong>Comprovante de venda - não fiscal</strong><br>
        Venda #{{ $sale->number() }} · {{ $sale->created_at->format('d/m/Y H:i') }}<br>
        Atendimento: {{ $sale->user->name }}
        @if ($sale->customer)<br>Cliente: {{ $sale->customer->name }}@endif
    </div>
    @unless ($sale->isCompleted())
        <p style="text-align: center; font-weight: bold; color: #b00020;">VENDA CANCELADA</p>
    @endunless
    <table style="width: 100%; border-collapse: collapse;">
        @foreach ($sale->items as $item)
            <tr>
                <td style="padding: 3px 0;">
                    {{ $item->quantity }}x {{ $item->description }}
                    @if ($item->serials)<br><span style="font-size: 12px; word-break: break-all;">Nº de série: {{ implode(', ', $item->serials) }}</span>@endif
                    @if ($item->warranty_until)<br><span style="font-size: 12px;">Garantia até {{ $item->warranty_until->format('d/m/Y') }}</span>@endif
                </td>
                <td style="padding: 3px 0; text-align: right; white-space: nowrap; vertical-align: top;">{{ Money::format($item->total) }}</td>
            </tr>
        @endforeach
    </table>
    <div style="border-top: 1px dashed #999; margin-top: 8px; padding-top: 8px;">
        <table style="width: 100%; border-collapse: collapse;">
            <tr><td>Subtotal</td><td style="text-align: right;">{{ Money::format($sale->subtotal) }}</td></tr>
            @if ((float) $sale->discount_total > 0)
                <tr><td>Descontos</td><td style="text-align: right;">{{ Money::format($sale->discount_total) }}</td></tr>
            @endif
            <tr><td style="font-size: 18px; font-weight: bold;">Total</td><td style="font-size: 18px; font-weight: bold; text-align: right;">{{ Money::format($sale->total) }}</td></tr>
            @foreach ($sale->payments as $payment)
                <tr><td>{{ $payment->method->name }}@if ($payment->installments > 1) ({{ $payment->installments }}x)@endif</td><td style="text-align: right;">{{ Money::format($payment->received_amount ?? $payment->amount) }}</td></tr>
            @endforeach
            @if ((float) $sale->change_amount > 0)
                <tr><td>Troco</td><td style="text-align: right;">{{ Money::format($sale->change_amount) }}</td></tr>
            @endif
        </table>
    </div>
    <p style="text-align: center; margin-top: 16px;">Agradecemos a preferência!</p>
</div>
