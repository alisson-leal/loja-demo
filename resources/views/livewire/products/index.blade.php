<div>
    <x-flash />

    <div class="row g-2 mb-3 align-items-end">
        <div class="col-12 col-lg-3">
            <label for="search" class="form-label">Buscar</label>
            <input id="search" type="search" class="form-control" placeholder="Nome, código, SKU ou código de barras" wire:model.live.debounce.300ms="search">
        </div>
        <div class="col-6 col-lg-3">
            <label for="categoryId" class="form-label">Categoria</label>
            <select id="categoryId" class="form-select" wire:model.live="categoryId">
                <option value="">Todas</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->parent_id ? '— ' : '' }}{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-lg-2">
            <label for="brandId" class="form-label">Marca</label>
            <select id="brandId" class="form-select" wire:model.live="brandId">
                <option value="">Todas</option>
                @foreach ($brands as $brand)
                    <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-lg-2">
            <label for="status" class="form-label">Situação</label>
            <select id="status" class="form-select" wire:model.live="status">
                <option value="ativos">Ativos</option>
                <option value="inativos">Inativos</option>
                <option value="todos">Todos</option>
            </select>
        </div>
        @can('produtos.criar')
            <div class="col-12 col-lg-2 d-flex gap-2 justify-content-end">
                <a href="{{ route('products.import') }}" class="btn btn-light" title="Importar planilha"><i class="bi bi-file-earmark-spreadsheet" aria-hidden="true"></i> Importar</a>
                <a href="{{ route('products.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> Novo</a>
            </div>
        @endcan
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <caption class="visually-hidden">Produtos</caption>
                <thead>
                    <tr>
                        <th scope="col"><span class="visually-hidden">Imagem</span></th>
                        <th scope="col">Código</th>
                        <th scope="col">Produto</th>
                        <th scope="col">Categoria</th>
                        <th scope="col">Preço</th>
                        <th scope="col">Estoque</th>
                        <th scope="col">Situação</th>
                        @can('produtos.editar') <th scope="col" class="text-end">Ações</th> @endcan
                    </tr>
                </thead>
                <tbody>
                    @forelse ($products as $product)
                        <tr wire:key="product-{{ $product->id }}">
                            <td>
                                @if ($image = $product->images->first())
                                    <img src="{{ $image->url() }}" alt="" class="product-thumb" loading="lazy">
                                @else
                                    <span class="product-thumb product-thumb-empty"><i class="bi bi-image" aria-hidden="true"></i></span>
                                @endif
                            </td>
                            <td class="text-nowrap">{{ $product->internal_code }}</td>
                            <td>
                                {{ $product->name }}
                                <div class="small text-body-secondary">{{ $product->brand?->name }}{{ $product->brand ? ' · ' : '' }}{{ $product->variants_count }} {{ $product->variants_count === 1 ? 'SKU' : 'SKUs' }}</div>
                            </td>
                            <td>{{ $product->category->fullName() }}</td>
                            <td class="text-nowrap">
                                {{ \App\Support\Money::format($product->price_min) }}
                                @unless (\App\Support\Money::equals($product->price_min, $product->price_max))
                                    a {{ \App\Support\Money::format($product->price_max) }}
                                @endunless
                                {{ \App\Support\Quantity::PER[$product->unit] ?? '' }}
                            </td>
                            <td class="text-nowrap">{{ $product->formatQuantity($product->stock_total) }}</td>
                            <td><span @class(['badge', 'text-bg-success' => $product->is_active, 'text-bg-secondary' => ! $product->is_active])>{{ $product->is_active ? 'Ativo' : 'Inativo' }}</span></td>
                            @can('produtos.editar')
                                <td class="text-end"><a href="{{ route('products.edit', $product->id) }}" class="btn btn-sm btn-outline-primary">Editar</a></td>
                            @endcan
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-body-secondary py-4">Nenhum produto encontrado.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $products->links() }}</div>
</div>
