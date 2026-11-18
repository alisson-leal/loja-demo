<div>
    <x-flash />

    <div class="mb-3"><a href="{{ route('stock.inventories') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Voltar aos inventários</a></div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h2 class="h6 mb-1">Inventário #{{ $inventory->id }} · {{ \App\Models\Inventory::STATUS_LABELS[$inventory->status] }}</h2>
                    <div class="small text-body-secondary">{{ $counted }} de {{ $total }} itens contados</div>
                </div>
                @if ($inventory->isOpen())
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-light" wire:click="cancel" wire:confirm="Cancelar este inventário? As contagens serão descartadas.">Cancelar</button>
                        <button type="button" class="btn btn-primary" wire:click="finish" wire:confirm="Concluir o inventário e ajustar os saldos dos itens contados? Itens sem contagem não serão alterados.">Concluir</button>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="row g-2 mb-3 align-items-end">
        @if ($inventory->isOpen())
            <div class="col-12 col-md-4">
                <form wire:submit="scan">
                    <label for="code" class="form-label">Leitor de código de barras</label>
                    <input id="code" type="text" autocomplete="off" class="form-control" placeholder="Cada leitura soma 1 unidade" wire:model="code">
                </form>
            </div>
        @endif
        <div class="col-8 col-md-5">
            <label for="search" class="form-label">Filtrar itens</label>
            <input id="search" type="search" class="form-control" placeholder="Produto ou SKU" wire:model.live.debounce.300ms="search">
        </div>
        <div class="col-4 col-md-3">
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" id="onlyPending" wire:model.live="onlyPending">
                <label class="form-check-label" for="onlyPending">Só pendentes</label>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <caption class="visually-hidden">Itens do inventário</caption>
                <thead>
                    <tr>
                        <th scope="col">Item</th>
                        <th scope="col">SKU</th>
                        <th scope="col">{{ $inventory->isOpen() ? 'Saldo atual' : 'Saldo no fechamento' }}</th>
                        <th scope="col">Contado</th>
                        <th scope="col">Diferença</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        @php
                            $expected = $inventory->isOpen() ? $item->variant->stockIn($inventory->store_id)['on_hand'] : $item->expected_quantity;
                            $difference = $item->counted_quantity !== null && $expected !== null ? $item->counted_quantity - $expected : null;
                        @endphp
                        <tr wire:key="item-{{ $item->id }}-{{ $item->counted_quantity ?? 'x' }}">
                            <td>{{ $item->variant->fullName() }}</td>
                            <td class="text-nowrap">{{ $item->variant->sku }}</td>
                            <td>{{ $expected ?? '-' }}</td>
                            <td>
                                @if ($inventory->isOpen())
                                    <input type="number" min="0" step="1" aria-label="Quantidade contada de {{ $item->variant->fullName() }}" value="{{ $item->counted_quantity }}"
                                           class="form-control form-control-sm grid-small" wire:change="saveCount({{ $item->id }}, $event.target.value)">
                                @else
                                    {{ $item->counted_quantity ?? 'Não contado' }}
                                @endif
                            </td>
                            <td @class(['fw-bold', 'text-success' => $difference > 0, 'text-danger' => $difference < 0])>
                                {{ $difference === null ? '-' : ($difference > 0 ? '+' : '').$difference }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-4">Nenhum item encontrado.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $items->links() }}</div>
</div>
