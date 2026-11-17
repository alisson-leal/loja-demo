<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Comprovante #{{ $sale->number() }}</title>
    <style>
        body { margin: 0; padding: 16px; background: #fff; }
        .actions { text-align: center; margin: 0 0 16px; }
        .actions button { padding: 10px 20px; font-size: 15px; cursor: pointer; }
        @media print { .actions { display: none; } body { padding: 0; } }
    </style>
</head>
<body>
    <div class="actions"><button type="button" onclick="window.print()">Imprimir</button></div>
    @include('sales.partials.receipt-body')
</body>
</html>
