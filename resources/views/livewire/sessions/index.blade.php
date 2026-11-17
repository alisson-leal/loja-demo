<div>
    <x-flash />

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <caption class="visually-hidden">Sessões ativas</caption>
                <thead>
                    <tr>
                        @if ($seesAll) <th scope="col">Usuário</th> @endif
                        <th scope="col">Dispositivo</th>
                        <th scope="col">IP</th>
                        <th scope="col">Última atividade</th>
                        <th scope="col" class="text-end">Ação</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr wire:key="session-{{ $loop->index }}">
                            @if ($seesAll) <td>{{ $item->user_name }}</td> @endif
                            <td>{{ \App\Support\UserAgent::describe($item->user_agent) }}</td>
                            <td>{{ $item->ip_address }}</td>
                            <td>{{ \Illuminate\Support\Carbon::createFromTimestamp($item->last_activity, config('app.timezone'))->format('d/m/Y H:i') }}</td>
                            <td class="text-end">
                                @if ($item->id === $currentId)
                                    <span class="badge text-bg-primary">Esta sessão</span>
                                @elseif ($item->user_id === auth()->id() || $canRevokeOthers)
                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                            wire:click="revoke('{{ $item->id }}')" wire:confirm="Encerrar esta sessão?">Encerrar</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-4">Nenhuma sessão ativa.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
