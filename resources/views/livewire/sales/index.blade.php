@php use App\Support\Money; @endphp
<div>
    <x-flash />

    <div class="row g-2 mb-3 align-items-end">
        <div class="col-12 col-lg-3">
            <label for="search" class="form-label">Buscar</label>
            <input id="search" type="search" class="form-control" placeholder="Número ou cliente" wire:model.live.debounce.300ms="search">
        </div>
        <div class="col-6 col-lg-2">
            <label for="status" class="form-label">Situação</label>
            <select id="status" class="form-select" wire:model.live="status">
                <option value="">Todas</option>
                <option value="completed">Concluídas</option>
                <option value="cancelled">Canceladas</option>
            </select>
        </div>
        <div class="col-6 col-lg-2">
            <label for="methodId" class="form-label">Pagamento</label>
            <select id="methodId" class="form-select" wire:model.live="methodId">
                <option value="">Todos</option>
                @foreach ($methods as $method)
                    <option value="{{ $method->id }}">{{ $method->name }}</option>
                @endforeach
            </select>
        </div>
        @if ($seesAll)
            <div class="col-12 col-lg-2">
                <label for="filter-user" class="form-label">Operador</label>
                <select id="filter-user" class="form-select" wire:model.live="userId">
                    <option value="">Todos</option>
                    @foreach ($users as $option)
                        <option value="{{ $option->id }}">{{ $option->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="col-6 col-lg">
            <label for="from" class="form-label">De</label>
            <input id="from" type="date" class="form-control" wire:model.live="from">
        </div>
        <div class="col-6 col-lg">
            <label for="to" class="form-label">Até</label>
            <input id="to" type="date" class="form-control" wire:model.live="to">
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle">
                <caption class="visually-hidden">Vendas realizadas</caption>
                <thead>
                    <tr>
                        <th scope="col">Venda</th>
                        <th scope="col">Data</th>
                        <th scope="col">Cliente</th>
                        <th scope="col">Operador</th>
                        <th scope="col">Pagamento</th>
                        <th scope="col">Total</th>
                        <th scope="col">Situação</th>
                        <th scope="col" class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sales as $sale)
                        <tr wire:key="sale-{{ $sale->id }}">
                            <td>#{{ $sale->number() }}</td>
                            <td class="text-nowrap">{{ $sale->created_at->format('d/m/Y H:i') }}</td>
                            <td>{{ $sale->customer?->name ?? '-' }}</td>
                            <td>{{ $sale->user->name }}</td>
                            <td>{{ $sale->payments->pluck('method.name')->unique()->implode(', ') }}</td>
                            <td class="text-nowrap fw-bold">{{ Money::format($sale->total) }}</td>
                            <td><span @class(['badge', 'text-bg-success' => $sale->isCompleted(), 'text-bg-danger' => ! $sale->isCompleted()])>{{ $sale->isCompleted() ? 'Concluída' : 'Cancelada' }}</span></td>
                            <td class="text-end"><a href="{{ route('sales.show', $sale->id) }}" class="btn btn-sm btn-outline-primary">Abrir</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-body-secondary py-4">Nenhuma venda encontrada.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $sales->links() }}</div>
</div>
