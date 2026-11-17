@php use App\Support\Money; @endphp
<div>
    <x-flash />

    @if (! $session)
        <div class="row justify-content-center">
            <div class="col-12 col-md-7 col-xl-5">
                <div class="card">
                    <div class="card-body">
                        <h2 class="h5">Abrir caixa</h2>
                        <p class="small text-body-secondary">Informe quanto há em dinheiro na gaveta para o troco.</p>
                        <form wire:submit="open" novalidate>
                            <div class="mb-3"><x-text-field name="openingAmount" label="Saldo inicial (R$)" type="number" required min="0" step="0.01" /></div>
                            <button type="submit" class="btn btn-primary w-100" wire:loading.attr="disabled">Abrir caixa</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="row g-3 mb-3">
            <div class="col-12 col-md-4"><x-money-card label="Dinheiro esperado na gaveta" :cents="$expected" icon="cash-stack" /></div>
            <div class="col-12 col-md-8">
                <div class="card h-100">
                    <div class="card-body">
                        <h2 class="h6">Vendas neste caixa por forma de pagamento</h2>
                        @forelse ($methodTotals as $row)
                            <div class="d-flex justify-content-between"><span>{{ $row->name }}</span><strong>{{ Money::format($row->total) }}</strong></div>
                        @empty
                            <p class="small text-body-secondary mb-0">Nenhuma venda ainda. Caixa aberto em {{ $session->opened_at->format('d/m/Y H:i') }}.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-12 col-xl-7">
                <div class="card h-100">
                    <div class="card-body">
                        <h2 class="h6 mb-3">Sangria, suprimento, entrada ou retirada</h2>
                        <form wire:submit="register" class="row g-3" novalidate>
                            <div class="col-12 col-md-6">
                                <label for="type" class="form-label">Tipo</label>
                                <select id="type" required wire:model="type" class="form-select @error('type') is-invalid @enderror">
                                    <option value="">Selecione</option>
                                    @foreach ($types as $option)
                                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                                    @endforeach
                                </select>
                                @error('type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-12 col-md-6"><x-text-field name="amount" label="Valor (R$)" type="number" required min="0.01" step="0.01" /></div>
                            <div class="col-12"><x-text-field name="reason" label="Motivo" required maxlength="255" /></div>
                            <div class="col-12"><button type="submit" class="btn btn-outline-primary" wire:loading.attr="disabled">Registrar</button></div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-12 col-xl-5">
                <div class="card h-100">
                    <div class="card-body">
                        <h2 class="h6 mb-3">Fechar caixa</h2>
                        <form wire:submit="close" novalidate>
                            <div class="mb-3"><x-text-field name="countedAmount" label="Dinheiro contado na gaveta (R$)" type="number" required min="0" step="0.01" /></div>
                            <div class="mb-3"><x-text-field name="closingNotes" label="Observação" maxlength="255" hint="Obrigatória se houver diferença." /></div>
                            <button type="submit" class="btn btn-primary w-100" wire:confirm="Fechar o caixa agora?" wire:loading.attr="disabled">Fechar caixa</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body pb-0"><h2 class="h6">Movimentações em dinheiro</h2></div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead><tr><th scope="col">Hora</th><th scope="col">Tipo</th><th scope="col">Motivo</th><th scope="col" class="text-end">Valor</th></tr></thead>
                    <tbody>
                        @foreach ($movements as $movement)
                            <tr wire:key="movement-{{ $movement->id }}">
                                <td class="text-nowrap">{{ $movement->created_at->format('H:i') }}</td>
                                <td class="text-nowrap">{{ $movement->type->label() }}</td>
                                <td>{{ $movement->reason }}</td>
                                <td @class(['text-end', 'text-nowrap', 'fw-bold', 'text-success' => $movement->amount > 0, 'text-danger' => $movement->amount < 0])>{{ Money::format($movement->amount) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
