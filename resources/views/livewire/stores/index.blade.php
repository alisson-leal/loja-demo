<div>
    <x-flash />

    <div class="card mb-3">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h2 class="h6 mb-1">Plano {{ $plan['name'] }}</h2>
                <p class="mb-0" id="plan-usage">
                    <strong>{{ $plan['active'] }}</strong> de <strong>{{ $plan['max'] }}</strong> {{ $plan['max'] === 1 ? 'loja ativa' : 'lojas ativas' }}.
                    @if ($plan['available'] === 0)
                        <span class="text-body-secondary">
                            {{ $plan['max'] === 1 ? 'Para cadastrar filiais, contrate o plano Multi Lojas com a DevZero1.' : 'Para abrir mais lojas, fale com a DevZero1 sobre ampliar o plano.' }}
                        </span>
                    @endif
                </p>
                @if ($userPlan['max'] !== null)
                    <p class="mb-0 small text-body-secondary" id="plan-users">
                        Usuários: <strong>{{ $userPlan['used'] }}</strong> de <strong>{{ $userPlan['max'] }}</strong> ({{ $userPlan['per_store'] }} por loja ativa; administradores não contam).
                    </p>
                @endif
            </div>
            @if ($plan['available'] > 0)
                <button type="button" class="btn btn-primary" wire:click="create"><i class="bi bi-plus-lg" aria-hidden="true"></i> Nova loja</button>
            @else
                <button type="button" class="btn btn-light" disabled title="Limite do plano atingido"><i class="bi bi-lock" aria-hidden="true"></i> Nova loja</button>
            @endif
        </div>
    </div>

    @if ($showForm)
        <form wire:submit="save" class="card mb-3" novalidate>
            <div class="card-body">
                <h2 class="h6 mb-3">{{ $editingId ? 'Editar loja' : 'Nova loja' }}</h2>
                <div class="row g-3">
                    <div class="col-12 col-md-5"><x-text-field name="name" label="Nome" required maxlength="80" /></div>
                    <div class="col-6 col-md-2"><x-text-field name="code" label="Código" required maxlength="10" hint="Ex.: CENTRO" /></div>
                    <div class="col-6 col-md-3"><x-text-field name="phone" label="Telefone" maxlength="20" /></div>
                    <div class="col-6 col-md-2 form-check-aligned">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="isActive" wire:model="isActive">
                            <label class="form-check-label" for="isActive">Ativa</label>
                        </div>
                    </div>
                    <div class="col-12 col-md-7"><x-text-field name="address" label="Endereço" maxlength="190" /></div>
                    <div class="col-12 col-md-5"><x-text-field name="city" label="Cidade" maxlength="100" /></div>
                    <div class="col-12">
                        <span class="form-label d-block">Quem trabalha nesta loja</span>
                        <p class="small text-body-secondary">Administradores e quem tem a permissão "ver todas as lojas" acessam todas. Usuário sem loja marcada trabalha na loja principal.</p>
                        <div class="row g-1">
                            @foreach ($users as $user)
                                <div class="col-12 col-md-6 col-xl-4" wire:key="team-{{ $user->id }}">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="team-{{ $user->id }}" value="{{ $user->id }}" wire:model="team">
                                        <label class="form-check-label" for="team-{{ $user->id }}">{{ $user->name }}</label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="d-flex gap-2 mt-3">
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Salvar</button>
                    <button type="button" class="btn btn-light" wire:click="$set('showForm', false)">Cancelar</button>
                </div>
            </div>
        </form>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle">
                <caption class="visually-hidden">Lojas</caption>
                <thead><tr><th scope="col">Loja</th><th scope="col">Código</th><th scope="col">Cidade</th><th scope="col">Equipe</th><th scope="col">Situação</th><th scope="col" class="text-end">Ações</th></tr></thead>
                <tbody>
                    @foreach ($stores as $store)
                        <tr wire:key="store-{{ $store->id }}">
                            <td>{{ $store->name }} @if ($store->is_main)<span class="badge text-bg-primary">Principal</span>@endif</td>
                            <td>{{ $store->code }}</td>
                            <td>{{ $store->city ?: '-' }}</td>
                            <td>{{ $store->users_count }}</td>
                            <td><span @class(['badge', 'text-bg-success' => $store->is_active, 'text-bg-secondary' => ! $store->is_active])>{{ $store->is_active ? 'Ativa' : 'Inativa' }}</span></td>
                            <td class="text-end"><button type="button" class="btn btn-sm btn-outline-primary" wire:click="edit({{ $store->id }})">Editar</button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
