<div>
    <x-flash />

    <form wire:submit="save" novalidate>
        <div class="card mb-3">
            <div class="card-body">
                <label for="name" class="form-label">Nome do perfil</label>
                <input id="name" type="text" required maxlength="60" wire:model="name" @readonly($nameLocked)
                       class="form-control @error('name') is-invalid @enderror">
                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <h2 class="h6 mb-3">Permissões por módulo</h2>
                <x-permission-matrix :catalog="$catalog" :grantable="$grantable" />
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Salvar</button>
            <a href="{{ route('roles.index') }}" class="btn btn-light">Cancelar</a>
        </div>
    </form>
</div>
