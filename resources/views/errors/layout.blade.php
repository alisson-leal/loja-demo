<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title')</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #150c25; color: #f6eefb; font-family: system-ui, sans-serif; text-align: center; padding: 16px; }
        h1 { font-size: 1.5rem; margin-bottom: .5rem; }
        a { color: #ff8cc4; }
    </style>
</head>
<body>
<main>
    <h1>@yield('title')</h1>
    <p>@yield('message')</p>
    <p><a href="{{ url('/') }}">Voltar ao início</a></p>
</main>
</body>
</html>
