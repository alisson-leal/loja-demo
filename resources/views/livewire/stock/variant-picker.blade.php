<div class="variant-picker" x-data x-on:focus-picker.window="$el.querySelector('input').focus()">
    <label for="picker-term" class="form-label">Produto</label>
    <input id="picker-term" type="search" class="form-control" autocomplete="off" placeholder="Digite o nome ou o SKU, ou use o leitor de código de barras"
           wire:model.live.debounce.250ms="term" wire:keydown.enter.prevent="pickExact">

    @if ($notFound)
        <div class="small text-danger mt-1">{{ $notFound }}</div>
    @endif

    @if ($results->isNotEmpty())
        <div class="list-group mt-1">
            @foreach ($results as $result)
                <button type="button" class="list-group-item list-group-item-action" wire:key="pick-{{ $result->id }}" wire:click="pick({{ $result->id }})">
                    {{ $result->fullName() }}
                    <span class="small text-body-secondary">· {{ $result->sku }} · disponível {{ $result->available() }}</span>
                </button>
            @endforeach
        </div>
    @elseif (mb_strlen(trim($term)) >= 2 && ! $notFound)
        <div class="small text-body-secondary mt-1">Nenhum item encontrado.</div>
    @endif
</div>
