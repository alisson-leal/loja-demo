<div>
    <x-flash />

    <form wire:submit="save" novalidate>
        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-6"><x-text-field name="name" label="Nome ou razão social" required maxlength="150" /></div>
                    <div class="col-12 col-md-3"><x-text-field name="document" label="CPF ou CNPJ (opcional)" maxlength="18" /></div>
                    <div class="col-12 col-md-3"><x-text-field name="contact_name" label="Pessoa de contato" maxlength="120" /></div>
                    <div class="col-12 col-md-4"><x-text-field name="phone" label="Telefone" type="tel" maxlength="20" /></div>
                    <div class="col-12 col-md-4"><x-text-field name="whatsapp" label="WhatsApp" type="tel" maxlength="20" /></div>
                    <div class="col-12 col-md-4"><x-text-field name="email" label="E-mail" type="email" maxlength="190" /></div>
                    <div class="col-12 col-md-6"><x-text-field name="address" label="Endereço" maxlength="190" /></div>
                    <div class="col-12 col-md-3"><x-text-field name="city" label="Cidade" maxlength="100" /></div>
                    <div class="col-4 col-md-1"><x-text-field name="state" label="UF" maxlength="2" /></div>
                    <div class="col-8 col-md-2"><x-text-field name="zip_code" label="CEP" maxlength="9" /></div>
                    <div class="col-12">
                        <label for="notes" class="form-label">Observações</label>
                        <textarea id="notes" rows="2" maxlength="2000" wire:model="notes" class="form-control @error('notes') is-invalid @enderror"></textarea>
                        @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_active" wire:model="is_active">
                            <label class="form-check-label" for="is_active">Ativo</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Salvar</button>
            <a href="{{ route('suppliers.index') }}" class="btn btn-light">Cancelar</a>
        </div>
    </form>
</div>
