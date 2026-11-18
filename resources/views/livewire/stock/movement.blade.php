<div class="row justify-content-center">
    <div class="col-12 col-xl-8">
        <x-flash />

        <div class="card mb-3">
            <div class="card-body">
                @if ($variant)
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div>
                            <h2 class="h6 mb-1">{{ $variant->fullName() }}</h2>
                            <div class="small text-body-secondary">SKU {{ $variant->sku }} · saldo nesta loja {{ $variant->stockIn()['on_hand'] }} · disponível <strong id="available">{{ $variant->availableIn() }}</strong></div>
                        </div>
                        <button type="button" class="btn btn-sm btn-light" wire:click="clearVariant">Trocar</button>
                    </div>
                @else
                    <livewire:stock.variant-picker />
                    @error('variantId') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <form wire:submit="save" novalidate>
                    <div class="row g-3">
                        <div class="col-12 col-md-7">
                            <label for="type" class="form-label">Tipo</label>
                            <select id="type" required wire:model="type" class="form-select @error('type') is-invalid @enderror">
                                <option value="">Selecione</option>
                                @foreach ($types as $option)
                                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                                @endforeach
                            </select>
                            @error('type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12 col-md-5">
                            <x-text-field name="quantity" label="Quantidade" type="number" required min="1" step="1" />
                        </div>
                        <div class="col-12">
                            <x-text-field name="reason" label="Motivo" required maxlength="255" />
                        </div>
                        @if ($canGoNegative)
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="allowNegative" wire:model="allowNegative">
                                    <label class="form-check-label" for="allowNegative">Autorizar saldo negativo nesta movimentação</label>
                                </div>
                            </div>
                        @endif
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Registrar</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
