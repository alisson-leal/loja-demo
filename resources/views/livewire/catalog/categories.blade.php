<div>
    <x-flash />

    @can('cadastros.gerir')
        <div class="card mb-3">
            <div class="card-body">
                <h2 class="h6 mb-3">{{ $editingId ? 'Editar categoria' : 'Nova categoria' }}</h2>
                <form wire:submit="save" class="row g-3 align-items-start" novalidate>
                    <div class="col-12 col-md-5">
                        <label for="name" class="form-label">Nome</label>
                        <input id="name" type="text" required maxlength="80" wire:model="name" class="form-control @error('name') is-invalid @enderror">
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-12 col-md-4">
                        <label for="parentId" class="form-label">Categoria principal</label>
                        <select id="parentId" wire:model="parentId" class="form-select @error('parentId') is-invalid @enderror">
                            <option value="">Nenhuma (é uma categoria principal)</option>
                            @foreach ($roots as $root)
                                @if ($root->id !== $editingId)
                                    <option value="{{ $root->id }}">{{ $root->name }}</option>
                                @endif
                            @endforeach
                        </select>
                        @error('parentId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-12 col-md-3 d-flex gap-2 form-actions-aligned">
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Salvar</button>
                        @if ($editingId)
                            <button type="button" class="btn btn-light" wire:click="cancel">Cancelar</button>
                        @endif
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="isActive" wire:model="isActive">
                            <label class="form-check-label" for="isActive">Ativa</label>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endcan

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <caption class="visually-hidden">Categorias</caption>
                <thead>
                    <tr>
                        <th scope="col">Categoria</th>
                        <th scope="col">Produtos</th>
                        <th scope="col">Situação</th>
                        @can('cadastros.gerir') <th scope="col" class="text-end">Ações</th> @endcan
                    </tr>
                </thead>
                <tbody>
                    @forelse ($categories as $category)
                        <tr wire:key="category-{{ $category->id }}">
                            <td @class(['ps-4' => $category->parent_id, 'fw-bold' => ! $category->parent_id])>
                                @if ($category->parent_id) <span aria-hidden="true">— </span> @endif{{ $category->name }}
                            </td>
                            <td>{{ $category->products_count }}</td>
                            <td><span @class(['badge', 'text-bg-success' => $category->is_active, 'text-bg-secondary' => ! $category->is_active])>{{ $category->is_active ? 'Ativa' : 'Inativa' }}</span></td>
                            @can('cadastros.gerir')
                                <td class="text-end text-nowrap">
                                    <button type="button" class="btn btn-sm btn-outline-primary" wire:click="edit({{ $category->id }})">Editar</button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" wire:click="delete({{ $category->id }})" wire:confirm="Excluir esta categoria?">Excluir</button>
                                </td>
                            @endcan
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-body-secondary py-4">Nenhuma categoria cadastrada.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
