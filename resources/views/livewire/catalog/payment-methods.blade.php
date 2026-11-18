<div>
    <x-flash />

    @can('cadastros.gerir')
        <div class="card mb-3">
            <div class="card-body">
                <h2 class="h6 mb-3">{{ $editingId ? 'Editar forma de pagamento' : 'Nova forma de pagamento' }}</h2>
                <form wire:submit="save" class="row g-3 align-items-start" novalidate>
                    <div class="col-12 col-md-5"><x-text-field name="name" label="Nome" required maxlength="60" /></div>
                    <div class="col-12 col-md-3">
                        <label for="type" class="form-label">Tipo</label>
                        <select id="type" wire:model.live="type" @disabled($typeLocked) class="form-select @error('type') is-invalid @enderror">
                            @if ($typeLocked)
                                <option value="{{ $type }}">{{ $typeLabels[$type] }}</option>
                            @else
                                @foreach ($selectableTypes as $option)
                                    <option value="{{ $option }}">{{ $typeLabels[$option] }}</option>
                                @endforeach
                            @endif
                        </select>
                        @error('type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-6 col-md-1 form-check-aligned">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="isActive" wire:model="isActive">
                            <label class="form-check-label" for="isActive">Ativa</label>
                        </div>
                    </div>
                    @if ($currentType === 'crediario')
                        <div class="col-6 col-md-3">
                            <x-text-field name="maxInstallments" label="Máximo de parcelas" type="number" />
                        </div>
                        <div class="col-6 col-md-3">
                            <x-text-field name="settlementDays" label="Dias até a 1ª parcela" type="number" hint="As demais vencem a cada 30 dias" />
                        </div>
                        <div class="col-6 col-md-3">
                            <x-text-field name="lateFeePercent" label="Multa por atraso (%)" type="number" step="0.01" min="0" hint="Até 2% (Código de Defesa do Consumidor)" />
                        </div>
                        <div class="col-6 col-md-3">
                            <x-text-field name="interestPercent" label="Juros ao mês (%)" type="number" step="0.01" min="0" hint="Proporcional aos dias de atraso" />
                        </div>
                    @endif
                    @unless (in_array($currentType, ['cash', 'store_credit', 'crediario'], true))
                        <div class="col-6 col-md-3">
                            <x-text-field name="feePercent" label="Taxa da maquininha (%)" type="number" step="0.01" min="0" hint="0 se não houver taxa" />
                        </div>
                        <div class="col-6 col-md-3">
                            <x-text-field name="settlementDays" label="Dias para o dinheiro cair" type="number" hint="0 = no mesmo dia" />
                        </div>
                        @if ($currentType === 'credit')
                            <div class="col-6 col-md-3">
                                <x-text-field name="maxInstallments" label="Máximo de parcelas" type="number" hint="1 = só à vista" />
                            </div>
                            <div class="col-6 col-md-3">
                                <x-text-field name="installmentFeePercent" label="Taxa no parcelado (%)" type="number" step="0.01" min="0" hint="Vazio = mesma taxa" />
                            </div>
                        @endif
                    @endunless
                    <div class="col-12 col-md-3 d-flex gap-2 form-actions-aligned">
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Salvar</button>
                        @if ($editingId)
                            <button type="button" class="btn btn-light" wire:click="cancel">Cancelar</button>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    @endcan

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle">
                <caption class="visually-hidden">Formas de pagamento</caption>
                <thead>
                    <tr>
                        <th scope="col">Nome</th>
                        <th scope="col">Tipo</th>
                        <th scope="col">Condições</th>
                        <th scope="col">Situação</th>
                        @can('cadastros.gerir') <th scope="col" class="text-end">Ações</th> @endcan
                    </tr>
                </thead>
                <tbody>
                    @foreach ($methods as $method)
                        <tr wire:key="method-{{ $method->id }}">
                            <td>{{ $method->name }}</td>
                            <td>{{ $typeLabels[$method->type] }}</td>
                            <td class="small">
                                @if (in_array($method->type, ['cash', 'store_credit'], true))
                                    -
                                @elseif ($method->type === 'crediario')
                                    Até {{ $method->max_installments }}x, 1ª parcela em {{ $method->settlement_days }} dias
                                    <br>Multa {{ number_format((float) $method->late_fee_percent, 2, ',', '.') }}% e juros {{ number_format((float) $method->interest_percent, 2, ',', '.') }}% ao mês
                                @else
                                    Taxa {{ number_format((float) $method->fee_percent, 2, ',', '.') }}%,
                                    {{ $method->settlement_days === 0 ? 'cai no mesmo dia' : "cai em {$method->settlement_days} dia(s)" }}
                                    @if ($method->max_installments > 1)
                                        <br>Até {{ $method->max_installments }}x{{ $method->installment_fee_percent !== null ? ', parcelado '.number_format((float) $method->installment_fee_percent, 2, ',', '.').'%' : '' }}
                                    @endif
                                @endif
                            </td>
                            <td><span @class(['badge', 'text-bg-success' => $method->is_active, 'text-bg-secondary' => ! $method->is_active])>{{ $method->is_active ? 'Ativa' : 'Inativa' }}</span></td>
                            @can('cadastros.gerir')
                                <td class="text-end"><button type="button" class="btn btn-sm btn-outline-primary" wire:click="edit({{ $method->id }})">Editar</button></td>
                            @endcan
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
