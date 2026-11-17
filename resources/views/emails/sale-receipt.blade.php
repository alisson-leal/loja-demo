<!doctype html>
<html lang="pt-BR">
<head><meta charset="utf-8"></head>
<body style="margin: 0; padding: 16px; background: #fff;">
    @include('sales.partials.receipt-body', ['sale' => $sale->loadMissing(['items', 'payments.method', 'customer', 'user'])])
</body>
</html>
