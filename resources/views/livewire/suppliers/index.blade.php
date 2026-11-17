<div>
    <x-flash />

    <div class="row g-2 mb-3 align-items-end">
        <div class="col-8 col-md-5">
            <label for="search" class="form-label">Buscar</label>
            <input id="search" type="search" class="form-control" placeholder="Nome, contato ou documento" wire:model.live.debounce.300ms="search">
        </div>
        @can('fornecedores.criar')
            <div class="col-4 col-md-7 text-end">
                <a href="{{ route('suppliers.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> Novo</a>
            </div>
        @endcan
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <caption class="visually-hidden">Fornecedores</caption>
                <thead>
                    <tr>
                        <th scope="col">Nome</th>
                        <th scope="col">Contato</th>
                        <th scope="col">Telefone</th>
                        <th scope="col">Produtos</th>
                        <th scope="col">Entradas</th>
                        <th scope="col">Situação</th>
                        @can('fornecedores.editar') <th scope="col" class="text-end">Ações</th> @endcan
                    </tr>
                </thead>
                <tbody>
                    @forelse ($suppliers as $supplier)
                        <tr wire:key="supplier-{{ $supplier->id }}">
                            <td>{{ $supplier->name }}</td>
                            <td>{{ $supplier->contact_name ?: '-' }}</td>
                            <td>{{ $supplier->phone ?: ($supplier->whatsapp ?: '-') }}</td>
                            <td>{{ $supplier->products_count }}</td>
                            <td>{{ $supplier->entries_count }}</td>
                            <td><span @class(['badge', 'text-bg-success' => $supplier->is_active, 'text-bg-secondary' => ! $supplier->is_active])>{{ $supplier->is_active ? 'Ativo' : 'Inativo' }}</span></td>
                            @can('fornecedores.editar')
                                <td class="text-end"><a href="{{ route('suppliers.edit', $supplier->id) }}" class="btn btn-sm btn-outline-primary">Editar</a></td>
                            @endcan
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-body-secondary py-4">Nenhum fornecedor encontrado.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $suppliers->links() }}</div>
</div>
