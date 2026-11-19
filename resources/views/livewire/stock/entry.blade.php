<div>
    <x-flash />

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-5">
                    <label for="supplierId" class="form-label">Fornecedor</label>
                    <select id="supplierId" wire:model="supplierId" class="form-select @error('supplierId') is-invalid @enderror">
                        <option value="">Não informado</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                    @error('supplierId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-12 col-md-3"><x-text-field name="documentNumber" label="Nota ou documento" maxlength="60" /></div>
                <div class="col-12 col-md-4"><x-text-field name="notes" label="Observação" maxlength="255" /></div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <livewire:stock.variant-picker />
        </div>
    </div>

    <div class="card mb-3">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <caption class="visually-hidden">Itens da entrada</caption>
                <thead>
                    <tr>
                        <th scope="col">Item</th>
                        <th scope="col">SKU</th>
                        <th scope="col">Quantidade</th>
                        @if ($canSeeCost) <th scope="col">Custo unitário (R$)</th> @endif
                        <th scope="col"><span class="visually-hidden">Remover</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $i => $item)
                        <tr wire:key="item-{{ $item['variant_id'] }}">
                            <td>
                                {{ $item['label'] }}
                                @if ($item['tracks'])
                                    <label for="serials-{{ $i }}" class="form-label small mt-1 mb-0">Números de série (um por linha)</label>
                                    <textarea id="serials-{{ $i }}" rows="2" class="form-control form-control-sm serial-input" wire:model.live.debounce.500ms="items.{{ $i }}.serials" autocomplete="off" spellcheck="false"></textarea>
                                @endif
                            </td>
                            <td class="text-nowrap">{{ $item['sku'] }}</td>
                            <td>
                                @if ($item['tracks'])
                                    <span class="fw-bold" id="qty-{{ $i }}">{{ $item['quantity'] }}</span>
                                @else
                                    <input type="number" min="1" step="1" aria-label="Quantidade de {{ $item['label'] }}" wire:model="items.{{ $i }}.quantity" class="form-control form-control-sm grid-small @error('items.'.$i.'.quantity') is-invalid @enderror">
                                @endif
                                @error('items.'.$i.'.quantity') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            </td>
                            @if ($canSeeCost)
                                <td>
                                    <input type="number" min="0" step="0.01" aria-label="Custo de {{ $item['label'] }}" wire:model="items.{{ $i }}.unit_cost" class="form-control form-control-sm grid-medium @error('items.'.$i.'.unit_cost') is-invalid @enderror">
                                    @error('items.'.$i.'.unit_cost') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </td>
                            @endif
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-light text-danger" wire:click="removeItem({{ $i }})" aria-label="Remover {{ $item['label'] }}"><i class="bi bi-trash" aria-hidden="true"></i></button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-4">Busque um produto acima para incluir na entrada.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @error('items') <div class="alert alert-danger">{{ $message }}</div> @enderror

    <button type="button" class="btn btn-primary" wire:click="save" wire:loading.attr="disabled">Registrar entrada</button>
</div>
