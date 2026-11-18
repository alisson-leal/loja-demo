@php use App\Support\Money; @endphp
<div>
    <x-flash />

    @if (! $session)
        <div class="card">
            <div class="card-body text-center py-5">
                <h2 class="h5">Seu caixa está fechado</h2>
                <p class="text-body-secondary">Abra o caixa para começar a vender.</p>
                @can('caixa.operar')
                    <a href="{{ route('cash.current') }}" class="btn btn-primary">Abrir caixa</a>
                @else
                    <p class="mb-0">Seu perfil não inclui abertura de caixa. Procure um gerente.</p>
                @endcan
            </div>
        </div>
    @else
        @if ($consignment)
            <div class="alert alert-info d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span><i class="bi bi-bag-check me-1" aria-hidden="true"></i>Pagamento da <strong>condicional #{{ $consignment->number() }}</strong>: peças que o cliente ficou. Você pode incluir outros produtos.</span>
                <button type="button" class="btn btn-sm btn-light" wire:click="detachConsignment" wire:confirm="Desvincular a condicional desta venda? As peças continuam reservadas para o cliente.">Desvincular</button>
            </div>
        @endif
        <div class="row g-3">
            <div class="col-12 col-xl-7">
                <div class="card mb-3">
                    <div class="card-body">
                        <livewire:stock.variant-picker />
                    </div>
                </div>

                <div class="card">
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <caption class="visually-hidden">Itens da venda</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Item</th>
                                    <th scope="col">Preço</th>
                                    <th scope="col">Quantidade</th>
                                    @if ($canDiscount) <th scope="col">Desc. (R$)</th> @endif
                                    <th scope="col" class="text-end">Total</th>
                                    <th scope="col"><span class="visually-hidden">Remover</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($cart as $i => $line)
                                    @php($variant = $computation['variants'][$line['variant_id']] ?? null)
                                    @if ($variant)
                                        <tr wire:key="line-{{ $line['variant_id'] }}">
                                            <td>
                                                {{ $variant->fullName() }}
                                                <div class="small text-body-secondary">{{ $variant->sku }}</div>
                                                @if (! $consignment && (int) $line['quantity'] > $variant->available())
                                                    <div class="small text-danger">Disponível: {{ $variant->available() }}</div>
                                                @endif
                                            </td>
                                            <td class="text-nowrap">{{ Money::format($variant->sale_price) }}</td>
                                            <td>
                                                <div class="input-group input-group-sm pos-qty">
                                                    <button type="button" class="btn btn-light" wire:click="changeQuantity({{ $i }}, -1)" aria-label="Diminuir quantidade de {{ $variant->fullName() }}">−</button>
                                                    <input type="number" min="1" step="1" class="form-control text-center" aria-label="Quantidade de {{ $variant->fullName() }}" wire:model.live.debounce.400ms="cart.{{ $i }}.quantity">
                                                    <button type="button" class="btn btn-light" wire:click="changeQuantity({{ $i }}, 1)" aria-label="Aumentar quantidade de {{ $variant->fullName() }}">+</button>
                                                </div>
                                            </td>
                                            @if ($canDiscount)
                                                <td><input type="number" min="0" step="0.01" class="form-control form-control-sm grid-small" aria-label="Desconto de {{ $variant->fullName() }}" wire:model.live.debounce.400ms="cart.{{ $i }}.discount"></td>
                                            @endif
                                            <td class="text-end text-nowrap fw-bold">{{ Money::format($computation['lines'][$i]['total'] / 100) }}</td>
                                            <td class="text-end"><button type="button" class="btn btn-sm btn-light text-danger" wire:click="removeLine({{ $i }})" aria-label="Remover {{ $variant->fullName() }}"><i class="bi bi-trash" aria-hidden="true"></i></button></td>
                                        </tr>
                                    @endif
                                @empty
                                    <tr><td colspan="6" class="text-center text-body-secondary py-5">Busque um produto ou use o leitor de código de barras.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-5">
                <div class="card mb-3">
                    <div class="card-body">
                        <h2 class="h6">Cliente</h2>
                        @if ($customer)
                            <div class="d-flex justify-content-between align-items-center gap-2">
                                <div>
                                    <strong>{{ $customer->name }}</strong>
                                    <div class="small text-body-secondary">{{ $customer->phone ?: $customer->email }}</div>
                                    @if ($crediarioPosition && ($crediarioPosition['limit'] !== null || $crediarioPosition['open'] > 0))
                                        <div class="small" id="pos-crediario">
                                            Crediário: {{ Money::format($crediarioPosition['open'] / 100) }} em aberto
                                            @if ($crediarioPosition['available'] !== null) · disponível {{ Money::format($crediarioPosition['available'] / 100) }} @endif
                                            @if ($crediarioPosition['overdue'] > 0) <span class="text-danger">· {{ Money::format($crediarioPosition['overdue'] / 100) }} vencido</span> @endif
                                        </div>
                                    @endif
                                </div>
                                <button type="button" class="btn btn-sm btn-light" wire:click="clearCustomer">Trocar</button>
                            </div>
                        @else
                            <label for="customerTerm" class="visually-hidden">Buscar cliente</label>
                            <input id="customerTerm" type="search" class="form-control" autocomplete="off" placeholder="Nome, telefone, CPF ou e-mail (opcional)" wire:model.live.debounce.300ms="customerTerm">
                            @if ($customerResults->isNotEmpty())
                                <div class="list-group mt-1">
                                    @foreach ($customerResults as $result)
                                        <button type="button" class="list-group-item list-group-item-action" wire:key="customer-{{ $result->id }}" wire:click="selectCustomer({{ $result->id }})">{{ $result->name }} <span class="small text-body-secondary">{{ $result->phone }}</span></button>
                                    @endforeach
                                </div>
                            @endif

                            @if ($canCreateCustomer)
                                @if ($showNewCustomer)
                                    <form wire:submit="createCustomer" class="mt-2" novalidate>
                                        <div class="mb-2"><x-text-field name="newCustomerName" label="Nome" required maxlength="150" /></div>
                                        <div class="mb-2"><x-text-field name="newCustomerPhone" label="WhatsApp com DDD" type="tel" maxlength="20" /></div>
                                        <div class="mb-2"><x-text-field name="newCustomerEmail" label="E-mail" type="email" maxlength="190" /></div>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" id="newCustomerConsent" wire:model="newCustomerConsent">
                                            <label class="form-check-label small" for="newCustomerConsent">O cliente aceita receber ofertas e novidades nos contatos informados</label>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <button type="submit" class="btn btn-sm btn-primary">Cadastrar</button>
                                            <button type="button" class="btn btn-sm btn-light" wire:click="$set('showNewCustomer', false)">Cancelar</button>
                                        </div>
                                    </form>
                                @else
                                    <button type="button" class="btn btn-sm btn-link px-0 mt-1" wire:click="$set('showNewCustomer', true)">Cadastrar novo cliente</button>
                                @endif
                            @endif
                        @endif
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-1"><span>Subtotal</span><span id="pos-subtotal">{{ Money::format($computation['subtotal'] / 100) }}</span></div>

                        @if ($canDiscount)
                            <div class="row g-2 my-1">
                                <div class="col-7">
                                    <label for="orderDiscount" class="visually-hidden">Desconto geral</label>
                                    <input id="orderDiscount" type="number" min="0" step="0.01" class="form-control form-control-sm" placeholder="Desconto geral" wire:model.live.debounce.400ms="orderDiscount">
                                </div>
                                <div class="col-5">
                                    <label for="orderDiscountType" class="visually-hidden">Tipo do desconto</label>
                                    <select id="orderDiscountType" class="form-select form-select-sm" wire:model.live="orderDiscountType">
                                        <option value="value">R$</option>
                                        <option value="percent">%</option>
                                    </select>
                                </div>
                            </div>
                        @endif

                        <div class="d-flex justify-content-between mb-1"><span>Descontos</span><span id="pos-discount">{{ Money::format($computation['discount_total'] / 100) }}</span></div>
                        <div class="pos-total"><span>Total</span><strong id="pos-total">{{ Money::format($computation['total'] / 100) }}</strong></div>

                        <h2 class="h6 mt-3">Pagamento</h2>
                        <div class="d-flex flex-wrap gap-2 mb-2">
                            @foreach ($methods as $method)
                                <button type="button" class="btn btn-sm btn-outline-primary" wire:key="method-{{ $method->id }}" wire:click="addPayment({{ $method->id }})">{{ $method->name }}</button>
                            @endforeach
                        </div>

                        <form wire:submit="applyCredit" class="input-group input-group-sm has-validation mb-2" novalidate>
                            <label for="creditCode" class="visually-hidden">Código do vale-troca</label>
                            <input id="creditCode" type="text" class="form-control text-uppercase @error('creditCode') is-invalid @enderror" placeholder="Código do vale-troca" autocomplete="off" wire:model="creditCode">
                            <button type="submit" class="btn btn-light">Aplicar</button>
                            @error('creditCode') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </form>

                        @foreach ($payments as $p => $payment)
                            <div class="d-flex align-items-center gap-2 mb-2" wire:key="payment-{{ $p }}-{{ $payment['method_id'] }}">
                                <span class="flex-grow-1 small">{{ $payment['is_cash'] ? 'Dinheiro recebido' : $payment['label'] }}</span>
                                @if (($payment['max_installments'] ?? 1) > 1)
                                    <select class="form-select form-select-sm w-auto" aria-label="Parcelas em {{ $payment['label'] }}" wire:model.live="payments.{{ $p }}.installments">
                                        @for ($n = 1; $n <= $payment['max_installments']; $n++)
                                            <option value="{{ $n }}">{{ $n }}x</option>
                                        @endfor
                                    </select>
                                @endif
                                <input type="number" min="0" step="0.01" class="form-control form-control-sm grid-medium" aria-label="Valor em {{ $payment['label'] }}" wire:model.live.debounce.400ms="payments.{{ $p }}.amount">
                                <button type="button" class="btn btn-sm btn-light text-danger" wire:click="removePayment({{ $p }})" aria-label="Remover {{ $payment['label'] }}"><i class="bi bi-trash" aria-hidden="true"></i></button>
                            </div>
                        @endforeach

                        <div class="d-flex justify-content-between mt-2"><span>Falta receber</span><strong id="pos-remaining">{{ Money::format($computation['remaining'] / 100) }}</strong></div>
                        <div class="d-flex justify-content-between"><span>Troco</span><strong id="pos-change" class="text-success">{{ Money::format($computation['change'] / 100) }}</strong></div>

                        <label for="notes" class="visually-hidden">Observação da venda</label>
                        <input id="notes" type="text" maxlength="255" class="form-control form-control-sm mt-3" placeholder="Observação (opcional)" wire:model="notes">

                        <div class="d-flex gap-2 mt-3">
                            <button type="button" class="btn btn-light" wire:click="clearSale" wire:confirm="Limpar a venda em andamento?">Limpar</button>
                            <button type="button" id="pos-finalize" class="btn btn-primary flex-grow-1" wire:click="finalize" wire:loading.attr="disabled" wire:target="finalize" @disabled($cart === [] || $computation['remaining'] > 0)>Finalizar venda</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
