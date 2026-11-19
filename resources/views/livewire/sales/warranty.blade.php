<div>
    <div class="card mb-3">
        <div class="card-body">
            <label for="search" class="form-label">Número de série ou número da venda</label>
            <input id="search" type="search" class="form-control" autocomplete="off" spellcheck="false" placeholder="Ex.: SN12345 ou 000123" wire:model.live.debounce.400ms="search" aria-describedby="searchHelp">
            <div id="searchHelp" class="form-text">Leia o número da etiqueta do produto com o leitor de código de barras ou digite ao menos 3 caracteres.</div>
        </div>
    </div>

    @if ($sale)
        <div class="card mb-3" id="warranty-sale">
            <div class="card-body">
                <h2 class="h6 mb-1">Venda #{{ $sale->number() }}</h2>
                <p class="small text-body-secondary">
                    {{ $sale->created_at->format('d/m/Y') }}@if ($sale->customer) · {{ $sale->customer->name }}@endif
                    @unless ($sale->isCompleted()) · <span class="text-danger">cancelada</span>@endunless
                    @if ($sale->isVisibleTo(auth()->user())) · <a href="{{ route('sales.show', $sale->id) }}">ver venda</a>@endif
                </p>
                <ul class="list-unstyled mb-0">
                    @foreach ($sale->items as $item)
                        <li class="py-2 border-top" wire:key="sale-item-{{ $item->id }}">
                            <strong>{{ $item->description }}</strong>
                            @if ($item->serials)<div class="small text-break">Nº de série: {{ implode(', ', $item->serials) }}</div>@endif
                            @include('livewire.sales.partials.warranty-badge', ['item' => $item])
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    @foreach ($units as $unit)
        <div class="card mb-3" wire:key="unit-{{ $unit->id }}">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between gap-2">
                    <div>
                        <h2 class="h6 mb-1 text-break">{{ $unit->serial }}</h2>
                        <div>{{ $unit->variant->fullName() }}</div>
                        <div class="small text-body-secondary">SKU {{ $unit->variant->sku }}</div>
                    </div>
                    <div class="text-end">
                        <span class="badge {{ $unit->status === \App\Models\SerialNumber::IN_STOCK ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $unit->statusLabel() }}</span>
                        @if ($unit->store && $unit->status !== \App\Models\SerialNumber::SOLD)<div class="small text-body-secondary">{{ $unit->store->name }}</div>@endif
                    </div>
                </div>

                @if ($unit->saleItem && $unit->status === \App\Models\SerialNumber::SOLD)
                    @php $soldIn = $unit->saleItem->sale; @endphp
                    <div class="mt-2">
                        Vendido na venda
                        @if ($soldIn->isVisibleTo(auth()->user()))<a href="{{ route('sales.show', $soldIn->id) }}">#{{ $soldIn->number() }}</a>@else #{{ $soldIn->number() }}@endif
                        em {{ $soldIn->created_at->format('d/m/Y') }}@if ($soldIn->customer) para {{ $soldIn->customer->name }}@endif.
                        @include('livewire.sales.partials.warranty-badge', ['item' => $unit->saleItem])
                    </div>
                @endif

                @if ($unit->events->isNotEmpty())
                    <h3 class="h6 small text-uppercase text-body-secondary mt-3 mb-1">Histórico</h3>
                    <ul class="list-unstyled small mb-0">
                        @foreach ($unit->events as $event)
                            <li class="py-1 border-top" wire:key="event-{{ $event->id }}">
                                {{ $event->created_at->format('d/m/Y H:i') }} · {{ $event->description }}@if ($event->store) · {{ $event->store->name }}@endif @if ($event->user) · {{ $event->user->name }}@endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    @endforeach

    @if ($searching && $units->isEmpty() && ! $sale)
        <div class="card"><div class="card-body text-center text-body-secondary py-4">Nada encontrado para "{{ $search }}".</div></div>
    @endif
</div>
