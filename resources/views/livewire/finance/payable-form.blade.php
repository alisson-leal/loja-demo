<div>
    <x-flash />

    <form wire:submit="save" class="card" novalidate>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-6"><x-text-field name="description" label="Descrição" required maxlength="150" hint="Ex.: Aluguel da loja, Nota 1234 Fornecedor X" /></div>
                <div class="col-12 col-md-3">
                    <label for="categoryId" class="form-label">Categoria</label>
                    <select id="categoryId" class="form-select @error('categoryId') is-invalid @enderror" wire:model="categoryId">
                        <option value="">Escolha</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('categoryId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-12 col-md-3">
                    <label for="supplierId" class="form-label">Fornecedor (opcional)</label>
                    <select id="supplierId" class="form-select @error('supplierId') is-invalid @enderror" wire:model="supplierId">
                        <option value="">Nenhum</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                    @error('supplierId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                @if ($stores->isNotEmpty())
                    <div class="col-12 col-md-3">
                        <label for="storeId" class="form-label">Loja</label>
                        <select id="storeId" class="form-select @error('storeId') is-invalid @enderror" wire:model="storeId">
                            <option value="">Geral (toda a rede)</option>
                            @foreach ($stores as $store)
                                <option value="{{ $store->id }}">{{ $store->name }}</option>
                            @endforeach
                        </select>
                        @error('storeId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                @endif
                <div class="col-6 col-md-3"><x-text-field name="dueDate" :label="$payableId ? 'Vencimento' : 'Primeiro vencimento'" type="date" required /></div>
                <div class="col-6 col-md-3">
                    <label for="amount" class="form-label">{{ $mode === 'parcelada' ? 'Valor total (R$)' : 'Valor (R$)' }}</label>
                    <input id="amount" type="number" min="0" step="0.01" class="form-control @error('amount') is-invalid @enderror" wire:model.live.debounce.400ms="amount">
                    @error('amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-12 col-md-6"><x-text-field name="document" label="Nº do documento, boleto ou nota (opcional)" maxlength="60" /></div>

                @unless ($payableId)
                    <div class="col-12 col-md-6">
                        <span class="form-label d-block">Repetição</span>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" id="mode-unica" value="unica" wire:model.live="mode">
                            <label class="form-check-label" for="mode-unica">Conta única</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" id="mode-parcelada" value="parcelada" wire:model.live="mode">
                            <label class="form-check-label" for="mode-parcelada">Compra parcelada (divide o valor em parcelas mensais)</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" id="mode-mensal" value="mensal" wire:model.live="mode">
                            <label class="form-check-label" for="mode-mensal">Conta fixa mensal (repete o mesmo valor, como aluguel)</label>
                        </div>
                    </div>
                    @if ($mode !== 'unica')
                        <div class="col-6 col-md-3">
                            <label for="times" class="form-label">{{ $mode === 'parcelada' ? 'Parcelas' : 'Meses' }}</label>
                            <input id="times" type="number" min="2" max="60" class="form-control @error('times') is-invalid @enderror" wire:model.live.debounce.400ms="times">
                            @error('times') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        @if ($preview)
                            <div class="col-12 col-md-3 align-self-end"><p class="small text-body-secondary mb-2" id="series-preview">{{ $preview }}</p></div>
                        @endif
                    @endif
                @endunless

                <div class="col-12">
                    <label for="notes" class="form-label">Observação</label>
                    <textarea id="notes" rows="2" maxlength="500" class="form-control @error('notes') is-invalid @enderror" wire:model="notes"></textarea>
                    @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Salvar</button>
                <a href="{{ route('finance.payables') }}" class="btn btn-light">Cancelar</a>
            </div>
        </div>
    </form>
</div>
