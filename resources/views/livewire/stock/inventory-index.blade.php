<div>
    <x-flash />

    <div class="card mb-3">
        <div class="card-body">
            <h2 class="h6 mb-3">Abrir inventário</h2>
            <form wire:submit="open" class="row g-3 align-items-start" novalidate>
                <div class="col-12 col-md-4">
                    <label for="categoryId" class="form-label">Abrangência</label>
                    <select id="categoryId" wire:model="categoryId" class="form-select @error('categoryId') is-invalid @enderror">
                        <option value="">Toda a loja</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->parent_id ? '— ' : '' }}{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('categoryId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-12 col-md-5"><x-text-field name="notes" label="Observação" maxlength="255" /></div>
                <div class="col-12 col-md-3 form-actions-aligned">
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Iniciar contagem</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <caption class="visually-hidden">Inventários</caption>
                <thead>
                    <tr>
                        <th scope="col">Número</th>
                        <th scope="col">Abertura</th>
                        <th scope="col">Abrangência</th>
                        <th scope="col">Itens</th>
                        <th scope="col">Responsável</th>
                        <th scope="col">Situação</th>
                        <th scope="col" class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($inventories as $inventory)
                        <tr wire:key="inventory-{{ $inventory->id }}">
                            <td>#{{ $inventory->id }}</td>
                            <td class="text-nowrap">{{ $inventory->created_at->format('d/m/Y H:i') }}</td>
                            <td>{{ $inventory->category?->name ?? 'Toda a loja' }}</td>
                            <td>{{ $inventory->items_count }}</td>
                            <td>{{ $inventory->opener->name }}</td>
                            <td>{{ \App\Models\Inventory::STATUS_LABELS[$inventory->status] }}</td>
                            <td class="text-end"><a href="{{ route('stock.inventory', $inventory->id) }}" class="btn btn-sm btn-outline-primary">{{ $inventory->isOpen() ? 'Contar' : 'Ver' }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-body-secondary py-4">Nenhum inventário realizado.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $inventories->links() }}</div>
</div>
