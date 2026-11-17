@extends('layouts.guest')
@section('title', 'Descadastro de e-mails')

@section('content')
@if ($done)
    <h1 class="h5">Pronto, {{ $customer->firstName() }}.</h1>
    <p>Você não receberá mais e-mails de ofertas e novidades da {{ $store['name'] }}.</p>
    <p class="small text-body-secondary mb-0">Se mudar de ideia, é só avisar na loja ou pelo WhatsApp {{ $store['whatsapp'] }}.</p>
@else
    <h1 class="h5">Deixar de receber e-mails?</h1>
    <p>Olá, {{ $customer->firstName() }}. Confirme abaixo para não receber mais e-mails de ofertas e novidades da {{ $store['name'] }}.</p>
    <form method="POST" action="{{ request()->fullUrl() }}">
        @csrf
        <button type="submit" class="btn btn-primary w-100">Não quero mais receber e-mails</button>
    </form>
@endif
@endsection
