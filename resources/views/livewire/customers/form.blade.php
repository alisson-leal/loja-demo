<div>
    <x-flash />

    <form wire:submit="save" novalidate>
        <div class="card mb-3">
            <div class="card-body">
                <h2 class="h6 mb-3">Dados do cliente</h2>
                <div class="row g-3">
                    <div class="col-12 col-md-6"><x-text-field name="name" label="Nome completo" required maxlength="150" /></div>
                    <div class="col-12 col-md-3"><x-text-field name="cpf" label="CPF (opcional)" maxlength="14" /></div>
                    <div class="col-6 col-md-3"><x-text-field name="birth_date" label="Nascimento" type="date" /></div>
                    <div class="col-6 col-md-3">
                        <label for="gender" class="form-label">Sexo (opcional)</label>
                        <select id="gender" wire:model="gender" class="form-select @error('gender') is-invalid @enderror">
                            <option value="">Não informado</option>
                            @foreach ($genders as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('gender') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-12 col-md-3"><x-text-field name="whatsapp" label="WhatsApp com DDD" type="tel" maxlength="20" /></div>
                    <div class="col-12 col-md-3"><x-text-field name="phone" label="Telefone" type="tel" maxlength="20" /></div>
                    <div class="col-12 col-md-3"><x-text-field name="email" label="E-mail" type="email" maxlength="190" /></div>
                    <div class="col-12 col-md-5"><x-text-field name="address" label="Endereço" maxlength="190" /></div>
                    <div class="col-12 col-md-3"><x-text-field name="neighborhood" label="Bairro" maxlength="100" /></div>
                    <div class="col-8 col-md-2"><x-text-field name="city" label="Cidade" maxlength="100" /></div>
                    <div class="col-4 col-md-2"><x-text-field name="zip_code" label="CEP" maxlength="9" /></div>
                    <div @class(['col-12', 'col-md-9' => auth()->user()->can('crediario.gerir')])>
                        <label for="notes" class="form-label">Observações</label>
                        <textarea id="notes" rows="2" maxlength="2000" wire:model="notes" class="form-control @error('notes') is-invalid @enderror"></textarea>
                        @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    @can('crediario.gerir')
                        <div class="col-12 col-md-3">
                            <x-text-field name="credit_limit" label="Limite de crediário (R$)" type="number" step="0.01" min="0" hint="Vazio = sem crediário" />
                        </div>
                    @endcan
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <h2 class="h6 mb-1">Comunicação de ofertas e novidades</h2>
                <p class="small text-body-secondary">Marque só o que o cliente autorizou expressamente (LGPD). Cada mudança fica registrada no histórico do cliente.</p>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="whatsapp_opt_in" wire:model="whatsapp_opt_in">
                    <label class="form-check-label" for="whatsapp_opt_in">Aceita receber mensagens pelo WhatsApp</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="email_opt_in" wire:model="email_opt_in">
                    <label class="form-check-label" for="email_opt_in">Aceita receber e-mails</label>
                </div>
            </div>
        </div>

        @if (\App\Support\StoreSegment::childrenEnabled())
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h2 class="h6 mb-0">Filhos (opcional)</h2>
                    <button type="button" class="btn btn-sm btn-outline-primary" wire:click="addChild">Adicionar filho</button>
                </div>
                <p class="small text-body-secondary">Cadastre somente com a concordância do responsável.</p>

                @foreach ($children as $i => $child)
                    <div class="row g-2 align-items-start mb-2" wire:key="child-{{ $child['id'] ?? 'new-'.$i }}">
                        <div class="col-12 col-md-5">
                            <label for="child-name-{{ $i }}" class="form-label">Nome</label>
                            <input id="child-name-{{ $i }}" type="text" maxlength="100" wire:model="children.{{ $i }}.name" class="form-control @error('children.'.$i.'.name') is-invalid @enderror">
                            @error('children.'.$i.'.name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-6 col-md-3">
                            <label for="child-birth-{{ $i }}" class="form-label">Nascimento</label>
                            <input id="child-birth-{{ $i }}" type="date" wire:model="children.{{ $i }}.birth_date" class="form-control @error('children.'.$i.'.birth_date') is-invalid @enderror">
                            @error('children.'.$i.'.birth_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-4 col-md-3">
                            <label for="child-size-{{ $i }}" class="form-label">Tamanho</label>
                            <input id="child-size-{{ $i }}" type="text" maxlength="20" wire:model="children.{{ $i }}.clothing_size" class="form-control @error('children.'.$i.'.clothing_size') is-invalid @enderror">
                        </div>
                        <div class="col-2 col-md-1 form-actions-aligned text-end">
                            <button type="button" class="btn btn-light text-danger" wire:click="removeChild({{ $i }})" aria-label="Remover filho {{ $i + 1 }}"><i class="bi bi-trash" aria-hidden="true"></i></button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Salvar</button>
            <a href="{{ $customerId ? route('customers.show', $customerId) : route('customers.index') }}" class="btn btn-light">Cancelar</a>
        </div>
    </form>
</div>
