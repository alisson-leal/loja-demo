<div>
    <x-flash />

    <div class="row g-2 mb-3 align-items-end">
        <div class="col-6 col-md-3">
            <label for="status" class="form-label">Situação</label>
            <select id="status" class="form-select" wire:model.live="status">
                <option value="">Todas</option>
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-9 text-end">
            @can('marketing.campanhas')
                <a href="{{ route('marketing.campaigns.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> Nova campanha</a>
            @endcan
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle">
                <caption class="visually-hidden">Campanhas</caption>
                <thead>
                    <tr>
                        <th scope="col">Campanha</th>
                        <th scope="col">Canal</th>
                        <th scope="col">Situação</th>
                        <th scope="col">Enviadas</th>
                        <th scope="col">Criada em</th>
                        <th scope="col" class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($campaigns as $campaign)
                        <tr wire:key="campaign-{{ $campaign->id }}">
                            <td>{{ $campaign->name }}<div class="small text-body-secondary">{{ $campaign->segment_name ?? 'Todos os clientes' }}</div></td>
                            <td>{{ $campaign->channelLabel() }}</td>
                            <td><span class="badge text-bg-{{ $campaign->statusTone() }}">{{ $campaign->statusLabel() }}</span></td>
                            <td class="text-nowrap">{{ $campaign->isDraft() ? '-' : "{$campaign->sent_count} de {$campaign->messages_count}" }}</td>
                            <td class="text-nowrap">{{ $campaign->created_at->format('d/m/Y') }}</td>
                            <td class="text-end"><a href="{{ route('marketing.campaigns.show', $campaign->id) }}" class="btn btn-sm btn-outline-primary">Abrir</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-body-secondary py-4">Nenhuma campanha cadastrada.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $campaigns->links() }}</div>
</div>
