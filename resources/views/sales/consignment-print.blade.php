@php use App\Support\Money; $store = config('loja.store'); @endphp
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Condicional #{{ $consignment->number() }} · {{ config('app.name') }}</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #000; max-width: 720px; margin: 24px auto; padding: 0 16px; font-size: 14px; }
        h1 { font-size: 18px; margin: 0; } table { width: 100%; border-collapse: collapse; margin: 16px 0; }
        th, td { border-bottom: 1px solid #ccc; padding: 6px 4px; text-align: left; } td.r, th.r { text-align: right; }
        .muted { color: #555; } .sign { margin-top: 56px; border-top: 1px solid #000; width: 60%; padding-top: 4px; }
        .actions { text-align: center; margin-bottom: 16px; } @media print { .actions { display: none; } }
    </style>
</head>
<body>
    <div class="actions"><button type="button" onclick="window.print()">Imprimir</button></div>
    <h1>{{ $store['name'] }} - Venda condicional #{{ $consignment->number() }}</h1>
    <p class="muted">{{ $store['tagline'] }} · WhatsApp {{ $store['whatsapp'] }}</p>
    <p>
        <strong>Cliente:</strong> {{ $consignment->customer->name }} {{ $consignment->customer->whatsappNumber() ? '· '.\App\Support\WhatsApp::format($consignment->customer->whatsappNumber()) : '' }}<br>
        <strong>Retirada:</strong> {{ $consignment->created_at->format('d/m/Y H:i') }} · <strong>Devolver até:</strong> {{ $consignment->due_date->format('d/m/Y') }}
    </p>
    <table>
        <thead><tr><th>Peça</th><th class="r">Qtd.</th><th class="r">Preço</th><th class="r">Total</th></tr></thead>
        <tbody>
            @foreach ($consignment->items as $item)
                <tr><td>{{ $item->description }}</td><td class="r">{{ $item->quantity }}</td><td class="r">{{ Money::format($item->unit_price) }}</td><td class="r">{{ Money::format($item->unit_price * $item->quantity) }}</td></tr>
            @endforeach
        </tbody>
        <tfoot><tr><th colspan="3" class="r">Valor total das peças</th><th class="r">{{ Money::format($consignment->items->sum(fn ($i) => $i->unit_price * $i->quantity)) }}</th></tr></tfoot>
    </table>
    <p>Declaro que recebi as peças acima para provar e me comprometo a devolvê-las em perfeito estado ou pagá-las até a data indicada.</p>
    <div class="sign">{{ $consignment->customer->name }}</div>
</body>
</html>
