<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-xl-5">
        @if ($forced)
            <div class="alert alert-warning" role="alert">Sua senha é provisória. Defina uma nova senha para continuar.</div>
        @endif

        <div class="card">
            <div class="card-body">
                <form wire:submit="save" novalidate>
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Senha atual</label>
                        <input id="current_password" type="password" required autocomplete="current-password" wire:model="current_password"
                               class="form-control @error('current_password') is-invalid @enderror">
                        @error('current_password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Nova senha</label>
                        <input id="password" type="password" required autocomplete="new-password" wire:model="password"
                               class="form-control @error('password') is-invalid @enderror">
                        <div class="form-text">Mínimo de 8 caracteres, com letras e números.</div>
                        @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-3">
                        <label for="password_confirmation" class="form-label">Confirmar nova senha</label>
                        <input id="password_confirmation" type="password" required autocomplete="new-password" wire:model="password_confirmation" class="form-control">
                    </div>
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Salvar nova senha</button>
                </form>
            </div>
        </div>
    </div>
</div>
