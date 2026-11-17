@extends('layouts.guest')
@section('title', 'Recuperar senha')

@section('content')
<p class="text-body-secondary small">Informe seu e-mail para receber o link de redefinição.</p>
<form method="POST" action="{{ route('password.email') }}" novalidate>
    @csrf
    <div class="mb-3">
        <label for="email" class="form-label">E-mail</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
               class="form-control @error('email') is-invalid @enderror">
        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <button type="submit" class="btn btn-primary w-100">Enviar link</button>
    <p class="text-center mt-3 mb-0"><a href="{{ route('login') }}">Voltar para o login</a></p>
</form>
@endsection
