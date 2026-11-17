@extends('layouts.guest')
@section('title', 'Acessar o sistema')

@section('content')
<form method="POST" action="{{ route('login') }}" novalidate>
    @csrf
    <div class="mb-3">
        <label for="email" class="form-label">E-mail</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
               class="form-control @error('email') is-invalid @enderror">
        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="mb-3">
        <label for="password" class="form-label">Senha</label>
        <input id="password" name="password" type="password" required autocomplete="current-password"
               class="form-control @error('password') is-invalid @enderror">
        @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <button type="submit" class="btn btn-primary w-100">Entrar</button>
    @unless (config('loja.demo.enabled'))
        <p class="text-center mt-3 mb-0"><a href="{{ route('password.request') }}">Esqueci minha senha</a></p>
    @endunless
</form>

@if (config('loja.demo.enabled'))
    <div class="demo-access mt-4">
        <p class="fw-semibold mb-2">Acesse a demonstração com um perfil:</p>
        @foreach (\Database\Seeders\DemoDataSeeder::ACCOUNTS as $account)
            <button type="button" class="btn btn-sm btn-light w-100 text-start mb-2 demo-login"
                    data-email="{{ $account['email'] }}" data-password="{{ config('loja.demo.password') }}">
                <strong>{{ $account['role'] }}</strong>
                <span class="d-block small text-body-secondary">{{ $account['hint'] }}</span>
            </button>
        @endforeach
        <p class="small text-body-secondary mb-0">Senha de todos: <code>{{ config('loja.demo.password') }}</code>. Os dados são fictícios e voltam ao original todos os dias.</p>
    </div>
    <script>
        document.querySelectorAll('.demo-login').forEach((button) => button.addEventListener('click', () => {
            document.getElementById('email').value = button.dataset.email;
            document.getElementById('password').value = button.dataset.password;
            button.closest('.card-body').querySelector('form').submit();
        }));
    </script>
@endif
@endsection
