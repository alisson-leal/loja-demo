<!doctype html>
<html lang="pt-BR" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.png') }}" type="image/png">
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>
<body class="auth-page">
<main class="auth-card card">
    <div class="card-body">
        <div class="auth-brand">
            <img src="{{ asset('images/logo.png') }}" alt="DevZero1 - Software & Technology" width="900" height="211">
        </div>
        <h1 class="h5 text-center mb-4">@yield('title')</h1>

        @if (session('success'))
            <div class="alert alert-success" role="status">{{ session('success') }}</div>
        @endif

        @yield('content')
    </div>
</main>
</body>
</html>
