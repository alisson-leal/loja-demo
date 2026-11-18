@php use App\Support\Money; $store = config('loja.store'); @endphp
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Carnê venda #{{ $sale->number() }} · {{ config('app.name') }}</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #000; max-width: 760px; margin: 16px auto; padding: 0 12px; font-size: 13px; }
        .slip { display: grid; grid-template-columns: 1fr 2fr; border: 1px dashed #555; margin-bottom: 10px; break-inside: avoid; }
        .slip > div { padding: 8px 10px; } .slip > div:first-child { border-right: 1px dashed #555; }
        .big { font-size: 16px; font-weight: bold; } .muted { color: #555; font-size: 11px; }
        .actions { text-align: center; margin-bottom: 12px; } @media print { .actions { display: none; } }
    </style>
</head>
<body>
    <div class="actions"><button type="button" onclick="window.print()">Imprimir</button></div>
    @foreach ($installments as $installment)
        <div class="slip">
            <div>
                <div class="muted">Canhoto da loja</div>
                <div>Venda #{{ $sale->number() }}</div>
                <div>Parcela {{ $installment->installment }}/{{ $installment->installments }}</div>
                <div>Venc. {{ $installment->due_date->format('d/m/Y') }}</div>
                <div class="big">{{ Money::format($installment->net_amount) }}</div>
            </div>
            <div>
                <div class="big">{{ $store['name'] }} - Carnê de pagamento</div>
                <div>Cliente: <strong>{{ $sale->customer?->name }}</strong></div>
                <div>Compra em {{ $sale->created_at->format('d/m/Y') }} · venda #{{ $sale->number() }} · parcela <strong>{{ $installment->installment }}/{{ $installment->installments }}</strong></div>
                <div>Vencimento: <strong>{{ $installment->due_date->format('d/m/Y') }}</strong> · Valor: <strong>{{ Money::format($installment->net_amount) }}</strong></div>
                <div class="muted">
                    Após o vencimento: multa de {{ number_format((float) $installment->method->late_fee_percent, 2, ',', '.') }}% e juros de {{ number_format((float) $installment->method->interest_percent, 2, ',', '.') }}% ao mês.
                    Pague na loja em dinheiro ou Pix. WhatsApp {{ $store['whatsapp'] }}.
                </div>
                @if ($installment->status === 'received')
                    <div><strong>PAGA em {{ $installment->received_at->format('d/m/Y') }}</strong></div>
                @endif
            </div>
        </div>
    @endforeach
</body>
</html>
