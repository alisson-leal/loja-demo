<div>
    <x-flash />

    <p class="text-body-secondary small">Atributos e valores criados aqui ficam disponíveis no cadastro de produtos.</p>

    @can('cadastros.gerir')
        <div class="card mb-3">
            <div class="card-body">
                <form wire:submit="addAttribute" class="row g-2 align-items-start" novalidate>
                    <div class="col-12 col-md-6">
                        <label for="newAttribute" class="form-label">Novo atributo</label>
                        <input id="newAttribute" type="text" required maxlength="60" placeholder="Ex.: Tecido" wire:model="newAttribute" class="form-control @error('newAttribute') is-invalid @enderror">
                        @error('newAttribute') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-12 col-md-3 form-actions-aligned">
                        <button type="submit" class="btn btn-primary">Adicionar atributo</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan

    <div class="row g-3">
        @foreach ($variationAttributes as $attribute)
            <div class="col-12 col-xl-6" wire:key="attribute-{{ $attribute->id }}">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h2 class="h6 mb-0">{{ $attribute->name }}</h2>
                            @can('cadastros.gerir')
                                <button type="button" class="btn btn-sm btn-outline-danger" wire:click="deleteAttribute({{ $attribute->id }})" wire:confirm="Excluir o atributo {{ $attribute->name }} e seus valores?">Excluir</button>
                            @endcan
                        </div>

                        <div class="d-flex flex-wrap gap-1 mb-3">
                            @forelse ($attribute->values as $value)
                                <span class="value-chip" wire:key="value-{{ $value->id }}">
                                    {{ $value->value }}
                                    @can('cadastros.gerir')
                                        <button type="button" aria-label="Excluir {{ $value->value }}" wire:click="deleteValue({{ $value->id }})" wire:confirm="Excluir o valor {{ $value->value }}?"><i class="bi bi-x" aria-hidden="true"></i></button>
                                    @endcan
                                </span>
                            @empty
                                <span class="small text-body-secondary">Nenhum valor cadastrado.</span>
                            @endforelse
                        </div>

                        @can('cadastros.gerir')
                            <form wire:submit="addValue({{ $attribute->id }})" class="input-group has-validation" novalidate>
                                <label for="value-{{ $attribute->id }}" class="visually-hidden">Novo valor para {{ $attribute->name }}</label>
                                <input id="value-{{ $attribute->id }}" type="text" maxlength="60" placeholder="Novo valor" wire:model="newValues.{{ $attribute->id }}" class="form-control @error('newValues.'.$attribute->id) is-invalid @enderror">
                                <button type="submit" class="btn btn-outline-primary">Adicionar</button>
                                @error('newValues.'.$attribute->id) <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </form>
                        @endcan
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
