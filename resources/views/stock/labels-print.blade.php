@php
    $roll = $format['per_page'] === 1;
    $rows = intdiv($format['per_page'], $format['columns']);
    $marginX = $roll ? 0 : (210 - $format['columns'] * $format['width']) / 2;
    $marginY = $roll ? 0 : (297 - $rows * $format['height']) / 2;
    $pages = array_chunk($labels, $format['per_page']);
    $mm = fn ($v) => number_format($v, 2, '.', '').'mm';
@endphp
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Etiquetas · {{ config('app.name') }}</title>
    <style>
        @page { size: {{ $roll ? $mm($format['width']).' '.$mm($format['height']) : 'A4' }}; margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; background: #fff; color: #000; font-family: Arial, Helvetica, sans-serif; }
        .actions { padding: 12px; text-align: center; background: #eef3fb; font-size: 14px; }
        .actions button, .actions a { margin: 0 6px; padding: 8px 16px; font-size: 14px; }
        .page {
            width: {{ $roll ? $mm($format['width']) : '210mm' }};
            height: {{ $roll ? $mm($format['height']) : '297mm' }};
            padding: {{ $mm($marginY) }} {{ $mm($marginX) }};
            display: grid;
            grid-template-columns: repeat({{ $format['columns'] }}, {{ $mm($format['width']) }});
            grid-auto-rows: {{ $mm($format['height']) }};
            page-break-after: always;
            overflow: hidden;
        }
        .label {
            display: flex; flex-direction: column; justify-content: space-between;
            padding: 1.5mm 2.5mm; overflow: hidden; text-align: center;
        }
        .name { font-size: {{ $format['height'] > 30 ? '10pt' : '7pt' }}; font-weight: bold; line-height: 1.1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .variant { font-size: {{ $format['height'] > 30 ? '9pt' : '6.5pt' }}; line-height: 1.1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .price { font-size: {{ $format['height'] > 30 ? '14pt' : '9pt' }}; font-weight: bold; line-height: 1.1; }
        .barcode svg { width: {{ $format['width'] > 50 ? '42mm' : '34mm' }}; max-width: 100%; height: {{ $format['height'] > 30 ? '13mm' : '7mm' }}; display: block; margin: 0 auto; }
        .code { font-size: 6pt; letter-spacing: .5px; }
        .store { font-size: 5.5pt; color: #444; }
        @media screen {
            body { background: #9aa5b8; }
            .page { margin: 12px auto; background: #fff; box-shadow: 0 2px 10px rgba(0,0,0,.3); }
            .label { outline: 1px dashed #ccc; }
        }
        @media print { .actions { display: none; } }
    </style>
</head>
<body>
    <div class="actions">
        {{ count($labels) }} etiqueta(s) - {{ $format['label'] }}
        <button type="button" onclick="window.print()">Imprimir</button>
        <a href="{{ route('stock.labels') }}">Voltar</a>
    </div>

    @foreach ($pages as $page)
        <div class="page">
            @foreach ($page as $label)
                <div class="label">
                    <div class="store">{{ $store }}</div>
                    <div class="name">{{ $label['name'] }}</div>
                    @if ($label['variant'])
                        <div class="variant">{{ $label['variant'] }}</div>
                    @endif
                    @if ($showPrice)
                        <div class="price">{{ \App\Support\Money::format($label['price']) }}</div>
                    @endif
                    <div class="barcode">{!! $label['svg'] !!}</div>
                    <div class="code">{{ $label['code'] }}</div>
                </div>
            @endforeach
        </div>
    @endforeach
</body>
</html>
