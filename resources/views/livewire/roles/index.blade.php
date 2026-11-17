<div>
    <x-flash />

    @can('perfis.criar')
        <div class="text-end mb-3">
            <a href="{{ route('roles.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> Novo perfil</a>
        </div>
    @endcan

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <caption class="visually-hidden">Perfis de acesso</caption>
                <thead>
                    <tr>
                        <th scope="col">Perfil</th>
                        <th scope="col">Permissões</th>
                        <th scope="col">Usuários</th>
                        <th scope="col" class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($roles as $role)
                        <tr wire:key="role-{{ $role->id }}">
                            <td>{{ $role->name }}</td>
                            <td>{{ $role->name === $adminRole ? 'Acesso total' : $role->permissions_count }}</td>
                            <td>{{ $role->users_count }}</td>
                            <td class="text-end text-nowrap">
                                @if ($role->name !== $adminRole)
                                    @can('perfis.editar')
                                        <a href="{{ route('roles.edit', $role->id) }}" class="btn btn-sm btn-outline-primary">Editar</a>
                                    @endcan
                                    @can('perfis.excluir')
                                        @unless (\App\Support\PermissionCatalog::isDefaultRole($role->name))
                                            <button type="button" class="btn btn-sm btn-outline-danger" wire:click="delete({{ $role->id }})" wire:confirm="Excluir este perfil?">Excluir</button>
                                        @endunless
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
