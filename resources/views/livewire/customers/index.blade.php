@php use App\Support\Money; @endphp
<div>
    <x-flash />

    <div class="row g-2 mb-3 align-items-end">
        <div class="col-12 col-md-6">
            <label for="search" class="form-label">Buscar</label>
            <input id="search" type="search" class="form-control" placeholder="Nome, telefone, CPF ou e-mail" wire:model.live.debounce.300ms="filters.search">
        </div>
        <div class="col-6 col-md-3">
            <label for="status" class="form-label">Situação</label>
            <select id="status" class="form-select" wire:model.live="filters.status">
                <option value="">Todos</option>
                <option value="ativos">Ativos (comprou em {{ $inactiveDays }} dias)</option>
                <option value="inativos">Inativos</option>
                <option value="sem_compras">Sem compras</option>
            </select>
        </div>
        <div class="col-6 col-md-3 d-flex gap-2 justify-content-end flex-wrap">
            @can('clientes.exportar')
                <a id="export-link" href="{{ route('customers.export', $exportQuery) }}" class="btn btn-light">Exportar</a>
            @endcan
            @can('clientes.criar')
                <a href="{{ route('customers.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> Novo</a>
            @endcan
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <h2 class="h6 mb-3">Segmentação</h2>
            <div class="row g-2">
                <div class="col-6 col-md-3 col-xl-2">
                    <label for="min_spent" class="form-label">Comprou a partir de (R$)</label>
                    <input id="min_spent" type="number" min="0" step="0.01" class="form-control" wire:model.live.debounce.400ms="filters.min_spent">
                </div>
                <div class="col-6 col-md-3 col-xl-2">
                    <label for="min_purchases" class="form-label">Mínimo de compras</label>
                    <input id="min_purchases" type="number" min="0" step="1" class="form-control" wire:model.live.debounce.400ms="filters.min_purchases">
                </div>
                <div class="col-6 col-md-3 col-xl-2">
                    <label for="idle_days" class="form-label">Sem comprar há (dias)</label>
                    <input id="idle_days" type="number" min="0" step="1" class="form-control" wire:model.live.debounce.400ms="filters.idle_days">
                </div>
                <div class="col-6 col-md-3 col-xl-2">
                    <label for="birthday_month" class="form-label">Aniversário em</label>
                    <select id="birthday_month" class="form-select" wire:model.live="filters.birthday_month">
                        <option value="">Qualquer mês</option>
                        @foreach (\App\Livewire\Customers\Birthdays::MONTHS as $number => $label)
                            <option value="{{ $number }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-6 col-xl-4">
                    <label for="category_id" class="form-label">Comprou na categoria</label>
                    <select id="category_id" class="form-select" wire:model.live="filters.category_id">
                        <option value="">Qualquer categoria</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->parent_id ? '— ' : '' }}{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3 col-xl-2">
                    <label for="has_children" class="form-label">Filhos</label>
                    <select id="has_children" class="form-select" wire:model.live="filters.has_children">
                        <option value="">Indiferente</option>
                        <option value="1">Com filhos</option>
                        <option value="0">Sem filhos</option>
                    </select>
                </div>
                <div class="col-6 col-md-3 col-xl-2">
                    <label for="child_size" class="form-label">Tamanho do filho</label>
                    <input id="child_size" type="text" maxlength="20" class="form-control" wire:model.live.debounce.400ms="filters.child_size">
                </div>
                <div class="col-6 col-md-3 col-xl-2">
                    <label for="child_age_min" class="form-label">Idade do filho, de</label>
                    <input id="child_age_min" type="number" min="0" step="1" class="form-control" wire:model.live.debounce.400ms="filters.child_age_min">
                </div>
                <div class="col-6 col-md-3 col-xl-2">
                    <label for="child_age_max" class="form-label">até</label>
                    <input id="child_age_max" type="number" min="0" step="1" class="form-control" wire:model.live.debounce.400ms="filters.child_age_max">
                </div>
                <div class="col-6 col-xl-2">
                    <label for="city" class="form-label">Cidade</label>
                    <input id="city" type="text" class="form-control" wire:model.live.debounce.400ms="filters.city">
                </div>
                <div class="col-6 col-xl-2">
                    <label for="neighborhood" class="form-label">Bairro</label>
                    <input id="neighborhood" type="text" class="form-control" wire:model.live.debounce.400ms="filters.neighborhood">
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 align-items-start mt-3">
                <button type="button" class="btn btn-light" wire:click="clearFilters">Limpar filtros</button>
                @can('clientes.editar')
                    <form wire:submit="saveSegment" class="input-group has-validation segment-form" novalidate>
                        <label for="segmentName" class="visually-hidden">Nome do segmento</label>
                        <input id="segmentName" type="text" maxlength="80" placeholder="Salvar filtros como segmento" wire:model="segmentName" class="form-control @error('segmentName') is-invalid @enderror">
                        <button type="submit" class="btn btn-outline-primary">Salvar</button>
                        @error('segmentName') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </form>
                @endcan
            </div>

            @if ($segments->isNotEmpty())
                <div class="d-flex flex-wrap gap-2 mt-3">
                    @foreach ($segments as $segment)
                        <span class="value-chip" wire:key="segment-{{ $segment->id }}">
                            <button type="button" class="segment-apply" wire:click="applySegment({{ $segment->id }})">{{ $segment->name }}</button>
                            @can('clientes.editar')
                                <button type="button" aria-label="Excluir segmento {{ $segment->name }}" wire:click="deleteSegment({{ $segment->id }})" wire:confirm="Excluir este segmento?"><i class="bi bi-x" aria-hidden="true"></i></button>
                            @endcan
                        </span>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <p class="small text-body-secondary" id="customer-count">{{ $customers->total() }} {{ $customers->total() === 1 ? 'cliente encontrado' : 'clientes encontrados' }}</p>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle">
                <caption class="visually-hidden">Clientes</caption>
                <thead>
                    <tr>
                        <th scope="col">Nome</th>
                        <th scope="col">Contato</th>
                        <th scope="col">Compras</th>
                        <th scope="col">Total</th>
                        <th scope="col">Última compra</th>
                        <th scope="col">Situação</th>
                        <th scope="col" class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($customers as $customer)
                        <tr wire:key="customer-{{ $customer->id }}">
                            <td>{{ $customer->name }}@if ($customer->children->isNotEmpty())<div class="small text-body-secondary">{{ $customer->children->count() }} {{ $customer->children->count() === 1 ? 'filho(a)' : 'filhos' }}</div>@endif</td>
                            <td>{{ $customer->whatsapp ?: $customer->phone ?: '-' }}<div class="small text-body-secondary">{{ $customer->email }}</div></td>
                            <td>{{ $customer->purchases_count }}</td>
                            <td class="text-nowrap">{{ Money::format($customer->total_spent) }}</td>
                            <td class="text-nowrap">{{ $customer->last_purchase_at?->format('d/m/Y') ?? '-' }}</td>
                            <td><span @class(['badge', 'text-bg-success' => $customer->statusLabel() === 'Ativo', 'text-bg-secondary' => $customer->statusLabel() !== 'Ativo'])>{{ $customer->statusLabel() }}</span></td>
                            <td class="text-end"><a href="{{ route('customers.show', $customer->id) }}" class="btn btn-sm btn-outline-primary">Abrir</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-body-secondary py-4">Nenhum cliente encontrado.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $customers->links() }}</div>
</div>
