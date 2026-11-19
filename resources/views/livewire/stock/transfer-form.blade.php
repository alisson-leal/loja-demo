<div>
    <x-flash />

    <div class="row g-3">
        <div class="col-12 col-xl-7">
            <div class="card">
                <div class="card-body">
                    <h2 class="h6">Peças que saem de {{ $from->name }}</h2>
                    <livewire:stock.variant-picker :serials="true" />
                </div>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <caption class="visually-hidden">Peças da transferência</caption>
                        <thead><tr><th scope="col">Produto</th><th scope="col">Qtd.</th><th scope="col">Disponível aqui</th><th scope="col"><span class="visually-hidden">Remover</span></th></tr></thead>
                        <tbody>
                            @forelse ($items as $id => $quantity)
                                @php $variant = $variants[$id] ?? null; @endphp
                                @if ($variant)
                                    @php $here = $variant->availableIn($from->id); @endphp
                                    <tr wire:key="item-{{ $id }}">
                                        <td>
                                            {{ $variant->fullName() }}<div class="small text-body-secondary">{{ $variant->sku }}</div>
                                            @if ($variant->product->tracks_serial)
                                                <label for="serials-{{ $id }}" class="form-label small mt-1 mb-0">Números de série (um por linha)</label>
                                                <textarea id="serials-{{ $id }}" rows="2" class="form-control form-control-sm serial-input" wire:model.live.debounce.500ms="serials.{{ $id }}" autocomplete="off" spellcheck="false"></textarea>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($variant->product->tracks_serial)
                                                <span class="fw-bold">{{ $quantity }}</span>
                                            @elseif ($variant->product->isFractional())
                                                <div class="input-group input-group-sm grid-medium">
                                                    <label for="qty-{{ $id }}" class="visually-hidden">Quantidade em {{ $variant->product->unit === 'm' ? 'metros' : 'quilos' }} de {{ $variant->fullName() }}</label>
                                                    <input id="qty-{{ $id }}" type="text" inputmode="decimal" autocomplete="off" placeholder="0,000" class="form-control text-end" wire:model.live.debounce.400ms="items.{{ $id }}">
                                                    <span class="input-group-text">{{ $variant->product->unit }}</span>
                                                </div>
                                            @else
                                                <label for="qty-{{ $id }}" class="visually-hidden">Quantidade de {{ $variant->fullName() }}</label>
                                                <input id="qty-{{ $id }}" type="number" min="1" class="form-control form-control-sm grid-small" wire:model.live.debounce.400ms="items.{{ $id }}">
                                            @endif
                                        </td>
                                        <td @class(['text-nowrap', 'text-danger' => $quantities[$id] > $here])>{{ $variant->formatQuantity($here) }}</td>
                                        <td class="text-end"><button type="button" class="btn btn-sm btn-light text-danger" wire:click="remove({{ $id }})" aria-label="Remover {{ $variant->fullName() }}"><i class="bi bi-trash" aria-hidden="true"></i></button></td>
                                    </tr>
                                @endif
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-4">Busque as peças ou use o leitor de código de barras.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-5">
            <form wire:submit="send" class="card" novalidate>
                <div class="card-body">
                    <label for="toStoreId" class="form-label">Enviar para</label>
                    <select id="toStoreId" class="form-select mb-3 @error('toStoreId') is-invalid @enderror" wire:model="toStoreId">
                        <option value="">Escolha a loja</option>
                        @foreach ($destinations as $store)
                            <option value="{{ $store->id }}">{{ $store->name }}</option>
                        @endforeach
                    </select>
                    @error('toStoreId') <div class="invalid-feedback">{{ $message }}</div> @enderror

                    <label for="notes" class="form-label">Observação</label>
                    <textarea id="notes" rows="2" maxlength="500" class="form-control mb-3" wire:model="notes"></textarea>

                    <p><strong>{{ $pieces }}</strong> peça(s)</p>
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:confirm="Enviar as peças? Elas saem do estoque desta loja agora.">Enviar transferência</button>
                </div>
            </form>
        </div>
    </div>
</div>
