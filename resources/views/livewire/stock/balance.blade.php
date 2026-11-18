<div>
    <x-flash />

    <div class="row g-2 mb-3 align-items-end">
        <div class="col-12 col-md-4">
            <label for="search" class="form-label">Buscar</label>
            <input id="search" type="search" class="form-control" placeholder="Nome, código, SKU ou código de barras" wire:model.live.debounce.300ms="search">
        </div>
        @if ($multiple)
            <div class="col-12 col-md-3">
                <label for="store" class="form-label">Loja</label>
                <select id="store" class="form-select" wire:model.live="store">
                    <option value="">Loja atual</option>
                    @foreach ($stores as $option)
                        <option value="{{ $option->id }}">{{ $option->name }}</option>
                    @endforeach
                    @if ($canSeeNetwork) <option value="rede">Todas as lojas (rede)</option> @endif
                </select>
            </div>
        @endif
        <div class="col-6 col-md-3">
            <label for="categoryId" class="form-label">Categoria</label>
            <select id="categoryId" class="form-select" wire:model.live="categoryId">
                <option value="">Todas</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->parent_id ? '— ' : '' }}{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label for="situation" class="form-label">Situação</label>
            <select id="situation" class="form-select" wire:model.live="situation">
                <option value="">Todas</option>
                <option value="baixo">Estoque baixo</option>
                <option value="sem">Sem estoque</option>
            </select>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <caption class="visually-hidden">Saldos por SKU</caption>
                <thead>
                    <tr>
                        <th scope="col">Produto</th>
                        <th scope="col">Variação</th>
                        <th scope="col">SKU</th>
                        <th scope="col">Saldo</th>
                        <th scope="col">Reservado</th>
                        <th scope="col">Disponível</th>
                        <th scope="col">Mínimo</th>
                        <th scope="col">Situação</th>
                        <th scope="col" class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($variants as $variant)
                        @php
                            $onHand = $network ? $variant->stock_on_hand : (int) $variant->store_on_hand;
                            $reserved = $network ? $variant->stock_reserved : (int) $variant->store_reserved;
                            $free = $onHand - $reserved;
                        @endphp
                        <tr wire:key="variant-{{ $variant->id }}">
                            <td>{{ $variant->product->name }}</td>
                            <td>{{ $variant->label() }}</td>
                            <td class="text-nowrap">{{ $variant->sku }}</td>
                            <td>{{ $onHand }}</td>
                            <td>{{ $reserved }}</td>
                            <td class="fw-bold">{{ $free }}</td>
                            <td>{{ $variant->min_stock }}</td>
                            <td>
                                @if ($free <= 0)
                                    <span class="badge text-bg-danger">Sem estoque</span>
                                @elseif ($variant->min_stock > 0 && $free <= $variant->min_stock)
                                    <span class="badge text-bg-warning">Baixo</span>
                                @else
                                    <span class="badge text-bg-success">Normal</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('stock.variant', $variant->id) }}" class="btn btn-sm btn-outline-primary">Detalhes</a>
                                @canany(['estoque.movimentar', 'estoque.ajustar'])
                                    <a href="{{ route('stock.movement', ['variante' => $variant->id]) }}" class="btn btn-sm btn-light">Movimentar</a>
                                @endcanany
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-body-secondary py-4">Nenhum item encontrado.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $variants->links() }}</div>
</div>
