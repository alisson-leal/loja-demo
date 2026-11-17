<div>
    <div class="row g-2 mb-3 align-items-end">
        <div class="col-12 col-md-3">
            <label for="event" class="form-label">Evento</label>
            <select id="event" class="form-select" wire:model.live="event">
                <option value="">Todos</option>
                @foreach ($events as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-3">
            <label for="filter-user" class="form-label">Usuário</label>
            <select id="filter-user" class="form-select" wire:model.live="userId">
                <option value="">Todos</option>
                @foreach ($users as $option)
                    <option value="{{ $option->id }}">{{ $option->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label for="from" class="form-label">De</label>
            <input id="from" type="date" class="form-control" wire:model.live="from">
        </div>
        <div class="col-6 col-md-2">
            <label for="to" class="form-label">Até</label>
            <input id="to" type="date" class="form-control" wire:model.live="to">
        </div>
        <div class="col-12 col-md-2">
            <button type="button" class="btn btn-light w-100" wire:click="clearFilters">Limpar filtros</button>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <caption class="visually-hidden">Histórico de auditoria</caption>
                <thead>
                    <tr>
                        <th scope="col">Data e hora</th>
                        <th scope="col">Usuário</th>
                        <th scope="col">Evento</th>
                        <th scope="col">Descrição</th>
                        <th scope="col">IP</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr wire:key="log-{{ $log->id }}">
                            <td class="text-nowrap">{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
                            <td>{{ $log->user?->name ?? '-' }}</td>
                            <td class="text-nowrap">{{ $log->eventLabel() }}</td>
                            <td>{{ $log->description }}</td>
                            <td>{{ $log->ip_address }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-4">Nenhum registro encontrado.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $logs->links() }}</div>
</div>
