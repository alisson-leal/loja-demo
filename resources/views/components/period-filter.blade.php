@props(['error' => null, 'future' => false])
<div class="row g-2 align-items-end mb-3 no-print">
    <div class="col-6 col-md-3 col-xl-2">
        <label for="from" class="form-label">De</label>
        <input id="from" type="date" class="form-control" wire:model.live="from">
    </div>
    <div class="col-6 col-md-3 col-xl-2">
        <label for="to" class="form-label">Até</label>
        <input id="to" type="date" class="form-control" wire:model.live="to">
    </div>
    <div class="col-12 col-md-6 col-xl-8 d-flex flex-wrap gap-2">
        <button type="button" class="btn btn-light btn-sm" wire:click="preset('hoje')">Hoje</button>
        <button type="button" class="btn btn-light btn-sm" wire:click="preset('7')">7 dias</button>
        <button type="button" class="btn btn-light btn-sm" wire:click="preset('mes')">Este mês</button>
        <button type="button" class="btn btn-light btn-sm" wire:click="preset('mes_anterior')">Mês anterior</button>
        @if ($future)
            <button type="button" class="btn btn-light btn-sm" wire:click="preset('proximos_30')">Próximos 30 dias</button>
        @endif
        {{ $slot }}
        <button type="button" class="btn btn-outline-primary btn-sm ms-auto" onclick="window.print()"><i class="bi bi-printer" aria-hidden="true"></i> Imprimir</button>
    </div>
    @if ($error)
        <div class="col-12"><div class="alert alert-warning mb-0 py-2">{{ $error }}</div></div>
    @endif
</div>
