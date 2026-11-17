@extends('layouts.guest')
@section('title', 'Definir nova senha')

@section('content')
<form method="POST" action="{{ route('password.update') }}" novalidate>
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <div class="mb-3">
        <label for="email" class="form-label">E-mail</label>
        <input id="email" name="email" type="email" value="{{ old('email', $email) }}" required autocomplete="username"
               class="form-control @error('email') is-invalid @enderror">
        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="mb-3">
        <label for="password" class="form-label">Nova senha</label>
        <input id="password" name="password" type="password" required autofocus autocomplete="new-password"
               class="form-control @error('password') is-invalid @enderror">
        <div class="form-text">Mínimo de 8 caracteres, com letras e números.</div>
        @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="mb-3">
        <label for="password_confirmation" class="form-label">Confirmar nova senha</label>
        <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="form-control">
    </div>
    <button type="submit" class="btn btn-primary w-100">Salvar nova senha</button>
</form>
@endsection
