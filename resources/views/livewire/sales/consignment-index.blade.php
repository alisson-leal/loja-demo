<div>
    <x-flash />

    @if ($overdue > 0)
        <div class="alert alert-warning">{{ $overdue }} condicional(is) com o prazo de devolução vencido. Entre em contato com o cliente.</div>
    @endif

    <div class="row g-2 mb-3 align-items-end">
        <div class="col-6 col-md-3">
            <label for="view" class="form-label">Mostrar</label>
            <select id="view" class="form-select" wire:model.live="view">
                <option value="abertas">Em aberto</option>
                <option value="vencidas">Prazo vencido</option>
                <option value="encerradas">Encerradas</option>
                <option value="todas">Todas</option>
            </select>
        </div>
        <div class="col-6 col-md-5">
            <label for="search" class="form-label">Cliente</label>
            <input id="search" type="search" class="form-control" placeholder="Nome ou telefone" wire:model.live.debounce.300ms="search">
        </div>
        @can('condicional.criar')
            <div class="col-12 col-md-4 text-end">
                <a href="{{ route('consignments.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> Nova condicional</a>
            </div>
        @endcan
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle">
                <caption class="visually-hidden">Condicionais</caption>
                <thead><tr><th scope="col">Nº</th><th scope="col">Cliente</th><th scope="col">Peças</th><th scope="col">Devolver até</th><th scope="col">Situação</th><th scope="col" class="text-end">Ações</th></tr></thead>
                <tbody>
                    @forelse ($consignments as $consignment)
                        <tr wire:key="consignment-{{ $consignment->id }}">
                            <td>#{{ $consignment->number() }}</td>
                            <td>{{ $consignment->customer->name }}<div class="small text-body-secondary">por {{ $consignment->user->name }} em {{ $consignment->created_at->format('d/m/Y') }}</div></td>
                            <td>{{ (int) $consignment->pieces }}</td>
                            <td class="text-nowrap">{{ $consignment->due_date->format('d/m/Y') }}</td>
                            <td><span class="badge text-bg-{{ $consignment->statusTone() }}">{{ $consignment->statusLabel() }}</span></td>
                            <td class="text-end"><a href="{{ route('consignments.show', $consignment->id) }}" class="btn btn-sm btn-outline-primary">Abrir</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-body-secondary py-4">Nenhuma condicional encontrada.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $consignments->links() }}</div>
</div>
