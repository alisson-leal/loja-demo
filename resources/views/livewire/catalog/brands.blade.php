<div>
    <x-flash />

    @can('cadastros.gerir')
        <div class="card mb-3">
            <div class="card-body">
                <h2 class="h6 mb-3">{{ $editingId ? 'Editar marca' : 'Nova marca' }}</h2>
                <form wire:submit="save" class="row g-3 align-items-start" novalidate>
                    <div class="col-12 col-md-6">
                        <label for="name" class="form-label">Nome</label>
                        <input id="name" type="text" required maxlength="80" wire:model="name" class="form-control @error('name') is-invalid @enderror">
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-12 col-md-2 form-check-aligned">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="isActive" wire:model="isActive">
                            <label class="form-check-label" for="isActive">Ativa</label>
                        </div>
                    </div>
                    <div class="col-12 col-md-4 d-flex gap-2 form-actions-aligned">
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Salvar</button>
                        @if ($editingId)
                            <button type="button" class="btn btn-light" wire:click="cancel">Cancelar</button>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    @endcan

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <caption class="visually-hidden">Marcas</caption>
                <thead>
                    <tr>
                        <th scope="col">Marca</th>
                        <th scope="col">Produtos</th>
                        <th scope="col">Situação</th>
                        @can('cadastros.gerir') <th scope="col" class="text-end">Ações</th> @endcan
                    </tr>
                </thead>
                <tbody>
                    @forelse ($brands as $brand)
                        <tr wire:key="brand-{{ $brand->id }}">
                            <td>{{ $brand->name }}</td>
                            <td>{{ $brand->products_count }}</td>
                            <td><span @class(['badge', 'text-bg-success' => $brand->is_active, 'text-bg-secondary' => ! $brand->is_active])>{{ $brand->is_active ? 'Ativa' : 'Inativa' }}</span></td>
                            @can('cadastros.gerir')
                                <td class="text-end text-nowrap">
                                    <button type="button" class="btn btn-sm btn-outline-primary" wire:click="edit({{ $brand->id }})">Editar</button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" wire:click="delete({{ $brand->id }})" wire:confirm="Excluir esta marca?">Excluir</button>
                                </td>
                            @endcan
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-body-secondary py-4">Nenhuma marca cadastrada.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
