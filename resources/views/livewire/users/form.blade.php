<div>
    <x-flash />

    <form wire:submit="save" novalidate>
        <div class="card mb-3">
            <div class="card-body">
                <h2 class="h6 mb-3">Dados do usuário</h2>
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label for="name" class="form-label">Nome</label>
                        <input id="name" type="text" required maxlength="120" wire:model="name" class="form-control @error('name') is-invalid @enderror">
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="email" class="form-label">E-mail</label>
                        <input id="email" type="email" required maxlength="190" wire:model="email" class="form-control @error('email') is-invalid @enderror">
                        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-12 col-md-4">
                        <label for="phone" class="form-label">Telefone <span class="text-body-secondary">(opcional)</span></label>
                        <input id="phone" type="tel" maxlength="20" wire:model="phone" class="form-control @error('phone') is-invalid @enderror">
                        @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-12 col-md-4">
                        <label for="password" class="form-label">{{ $userId ? 'Nova senha provisória' : 'Senha provisória' }}</label>
                        <input id="password" type="password" autocomplete="new-password" @required(! $userId) wire:model="password" class="form-control @error('password') is-invalid @enderror">
                        <div class="form-text">{{ $userId ? 'Deixe em branco para manter a atual.' : 'Mínimo de 8 caracteres, com letras e números.' }}</div>
                        @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-12 col-md-4">
                        <label for="password_confirmation" class="form-label">Confirmar senha</label>
                        <input id="password_confirmation" type="password" autocomplete="new-password" wire:model="password_confirmation" class="form-control">
                    </div>
                </div>
            </div>
        </div>

        @if ($isSelf)
            <div class="alert alert-info" role="status">Você não pode alterar os próprios perfis e permissões.</div>
        @endif

        <fieldset @disabled($isSelf)>
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="h6 mb-3">Perfis</h2>
                    @foreach ($availableRoles as $role)
                        <div class="form-check form-check-inline" wire:key="role-{{ $loop->index }}">
                            <input class="form-check-input" type="checkbox" id="role-{{ $loop->index }}" value="{{ $role }}" wire:model="roles">
                            <label class="form-check-label" for="role-{{ $loop->index }}">{{ $role }}</label>
                        </div>
                    @endforeach
                    @error('roles') <div class="text-danger small mt-2">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="h6">Permissões adicionais</h2>
                    <p class="small text-body-secondary">Somam-se às permissões dos perfis acima.</p>
                    <x-permission-matrix :catalog="$catalog" :grantable="$grantable" />
                </div>
            </div>
        </fieldset>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Salvar</button>
            <a href="{{ route('users.index') }}" class="btn btn-light">Cancelar</a>
        </div>
    </form>
</div>
