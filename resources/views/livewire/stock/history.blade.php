<div>
    <x-flash />

    <div class="row g-2 mb-3 align-items-end">
        <div class="col-12 col-lg-4">
            <label for="search" class="form-label">Buscar</label>
            <input id="search" type="search" class="form-control" placeholder="Produto, SKU ou código de barras" wire:model.live.debounce.300ms="search">
        </div>
        <div class="col-6 col-lg-2">
            <label for="type" class="form-label">Tipo</label>
            <select id="type" class="form-select" wire:model.live="type">
                <option value="">Todos</option>
                @foreach ($types as $option)
                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-lg-2">
            <label for="filter-user" class="form-label">Usuário</label>
            <select id="filter-user" class="form-select" wire:model.live="userId">
                <option value="">Todos</option>
                @foreach ($users as $option)
                    <option value="{{ $option->id }}">{{ $option->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-lg-2">
            <label for="from" class="form-label">De</label>
            <input id="from" type="date" class="form-control" wire:model.live="from">
        </div>
        <div class="col-6 col-lg-2">
            <label for="to" class="form-label">Até</label>
            <input id="to" type="date" class="form-control" wire:model.live="to">
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <caption class="visually-hidden">Histórico de movimentações de estoque</caption>
                <thead>
                    <tr>
                        <th scope="col">Data e hora</th>
                        <th scope="col">Item</th>
                        <th scope="col">Tipo</th>
                        <th scope="col">Quantidade</th>
                        <th scope="col">Saldo após</th>
                        <th scope="col">Motivo</th>
                        <th scope="col">Usuário</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($movements as $movement)
                        <tr wire:key="movement-{{ $movement->id }}">
                            <td class="text-nowrap">{{ $movement->created_at->format('d/m/Y H:i') }}</td>
                            <td>
                                <a href="{{ route('stock.variant', $movement->product_variant_id) }}">{{ $movement->variant->fullName() }}</a>
                                <div class="small text-body-secondary">{{ $movement->variant->sku }}</div>
                            </td>
                            <td class="text-nowrap">{{ $movement->type->label() }}</td>
                            <td @class(['fw-bold', 'text-nowrap', 'text-success' => $movement->quantity > 0, 'text-danger' => $movement->quantity < 0])>{{ $movement->quantity > 0 ? '+' : '' }}{{ $movement->variant->formatQuantity($movement->quantity) }}</td>
                            <td class="text-nowrap">{{ $movement->variant->formatQuantity($movement->balance_after) }}</td>
                            <td>{{ $movement->reason }}</td>
                            <td>{{ $movement->user?->name ?? 'Sistema' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-body-secondary py-4">Nenhuma movimentação encontrada.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $movements->links() }}</div>
</div>
