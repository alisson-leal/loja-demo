<div>
    <x-flash />

    @if ($blocking)
        <div class="card border-danger mb-3">
            <div class="card-body">
                <h2 class="h6">Bloquear {{ $blocking->name }}</h2>
                <form wire:submit="confirmBlock" novalidate>
                    <label for="blockReason" class="form-label">Motivo do bloqueio</label>
                    <input id="blockReason" type="text" maxlength="255" required wire:model="blockReason"
                           class="form-control @error('blockReason') is-invalid @enderror">
                    @error('blockReason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    <div class="mt-3 d-flex gap-2">
                        <button type="submit" class="btn btn-danger" wire:loading.attr="disabled">Confirmar bloqueio</button>
                        <button type="button" class="btn btn-light" wire:click="cancelBlock">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div class="row g-2 mb-3 align-items-end">
        <div class="col-12 col-md-5">
            <label for="search" class="form-label">Buscar</label>
            <input id="search" type="search" class="form-control" placeholder="Nome ou e-mail" wire:model.live.debounce.300ms="search">
        </div>
        <div class="col-8 col-md-3">
            <label for="status" class="form-label">Situação</label>
            <select id="status" class="form-select" wire:model.live="status">
                <option value="">Todos</option>
                <option value="ativos">Ativos</option>
                <option value="bloqueados">Bloqueados</option>
            </select>
        </div>
        @can('usuarios.criar')
            <div class="col-4 col-md-4 text-end">
                <a href="{{ route('users.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> Novo</a>
            </div>
        @endcan
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <caption class="visually-hidden">Usuários do sistema</caption>
                <thead>
                    <tr>
                        <th scope="col">Nome</th>
                        <th scope="col">E-mail</th>
                        <th scope="col">Perfis</th>
                        <th scope="col">Situação</th>
                        <th scope="col">Último acesso</th>
                        <th scope="col" class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr wire:key="user-{{ $user->id }}">
                            <td>{{ $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            <td>{{ $user->roles->pluck('name')->implode(', ') ?: '-' }}</td>
                            <td>
                                @if ($user->is_blocked)
                                    <span class="badge text-bg-danger" title="{{ $user->blocked_reason }}">Bloqueado</span>
                                @else
                                    <span class="badge text-bg-success">Ativo</span>
                                @endif
                            </td>
                            <td>{{ $user->last_login_at?->format('d/m/Y H:i') ?? 'Nunca' }}</td>
                            <td class="text-end text-nowrap">
                                @if ($user->canBeManagedBy(auth()->user()))
                                    @can('usuarios.editar')
                                        <a href="{{ route('users.edit', $user->id) }}" class="btn btn-sm btn-outline-primary">Editar</a>
                                    @endcan
                                    @can('usuarios.bloquear')
                                        @if ($user->is_blocked)
                                            <button type="button" class="btn btn-sm btn-outline-success" wire:click="unblock({{ $user->id }})" wire:confirm="Desbloquear este usuário?">Desbloquear</button>
                                        @elseif ($user->id !== auth()->id())
                                            <button type="button" class="btn btn-sm btn-outline-danger" wire:click="askBlock({{ $user->id }})">Bloquear</button>
                                        @endif
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-body-secondary py-4">Nenhum usuário encontrado.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $users->links() }}</div>
</div>
