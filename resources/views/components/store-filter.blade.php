@props(['options', 'class' => 'col-12 col-md-3'])
@if ($options->isNotEmpty())
    <div class="{{ $class }} no-print">
        <label for="storeFilter" class="form-label">Loja</label>
        <select id="storeFilter" class="form-select" wire:model.live="storeFilter">
            <option value="">Todas as lojas</option>
            @foreach ($options as $option)
                <option value="{{ $option->id }}">{{ $option->name }}</option>
            @endforeach
        </select>
    </div>
@elseif (\App\Support\CurrentStore::multiple())
    <div class="{{ $class }} no-print align-self-end">
        <p class="small text-body-secondary mb-2"><i class="bi bi-shop" aria-hidden="true"></i> {{ \App\Support\CurrentStore::store()->name }}</p>
    </div>
@endif
