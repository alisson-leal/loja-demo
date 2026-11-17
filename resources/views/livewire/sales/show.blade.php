@php use App\Support\Money; @endphp
<div>
    <x-flash />

    <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
        <a href="{{ route('sales.index') }}" class="align-self-center"><i class="bi bi-arrow-left" aria-hidden="true"></i> Voltar às vendas</a>
        @can('pdv.vender')
            <a href="{{ route('pos') }}" class="btn btn-primary"><i class="bi bi-cart-plus" aria-hidden="true"></i> Nova venda</a>
        @endcan
    </div>

    @unless ($sale->isCompleted())
        <div class="alert alert-danger" role="status">
            Venda cancelada em {{ $sale->cancelled_at->format('d/m/Y H:i') }} por {{ $sale->canceller->name }}@if ($sale->cancelAuthorizer), autorizada por {{ $sale->cancelAuthorizer->name }}@endif. Motivo: {{ $sale->cancel_reason }}
        </div>
    @endunless

    <div class="row g-3">
        <div class="col-12 col-xl-8">
            <div class="card mb-3">
                <div class="card-body pb-0">
                    <h2 class="h6 mb-1">Itens</h2>
                    <p class="small text-body-secondary">
                        {{ $sale->created_at->format('d/m/Y H:i') }} · Operador: {{ $sale->user->name }}
                        @if ($sale->customer) · Cliente: {{ $sale->customer->name }} @endif
                    </p>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead><tr><th scope="col">Item</th><th scope="col">Qtde.</th><th scope="col">Unitário</th><th scope="col">Desconto</th><th scope="col" class="text-end">Total</th></tr></thead>
                        <tbody>
                            @foreach ($sale->items as $item)
                                <tr wire:key="item-{{ $item->id }}">
                                    <td>
                                        {{ $item->description }}
                                        <div class="small text-body-secondary">{{ $item->sku }}@if ($item->returned_quantity) · {{ $item->returned_quantity }} devolvido(s)@endif</div>
                                    </td>
                                    <td>{{ $item->quantity }}</td>
                                    <td class="text-nowrap">{{ Money::format($item->unit_price) }}</td>
                                    <td class="text-nowrap">{{ Money::format($item->discount) }}</td>
                                    <td class="text-end text-nowrap fw-bold">{{ Money::format($item->total) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between"><span>Subtotal</span><span>{{ Money::format($sale->subtotal) }}</span></div>
                    <div class="d-flex justify-content-between"><span>Descontos</span><span>{{ Money::format($sale->discount_total) }}</span></div>
                    <div class="pos-total"><span>Total</span><strong>{{ Money::format($sale->total) }}</strong></div>
                    <hr>
                    @foreach ($sale->payments as $payment)
                        <div class="d-flex justify-content-between" wire:key="payment-{{ $payment->id }}">
                            <span>{{ $payment->method->name }}@if ($payment->installments > 1) ({{ $payment->installments }}x)@endif @if ($payment->storeCredit) {{ $payment->storeCredit->code }}@endif</span>
                            <span>{{ Money::format($payment->received_amount ?? $payment->amount) }}</span>
                        </div>
                    @endforeach
                    @if ((float) $sale->change_amount > 0)
                        <div class="d-flex justify-content-between"><span>Troco</span><span>{{ Money::format($sale->change_amount) }}</span></div>
                    @endif
                    @if ($sale->notes)
                        <p class="small text-body-secondary mt-2 mb-0">Observação: {{ $sale->notes }}</p>
                    @endif
                </div>
            </div>

            @if ($sale->returns->isNotEmpty())
                <div class="card">
                    <div class="card-body">
                        <h2 class="h6">Devoluções e trocas</h2>
                        @foreach ($sale->returns as $return)
                            <div class="py-2 border-bottom" wire:key="return-{{ $return->id }}">
                                <div class="d-flex justify-content-between"><strong>{{ $return->created_at->format('d/m/Y H:i') }} · {{ $return->user->name }}</strong><strong>{{ Money::format($return->total) }}</strong></div>
                                <div class="small">
                                    @foreach ($return->items as $returned)
                                        {{ $returned->quantity }}x {{ $returned->saleItem->description }}{{ $returned->restocked ? '' : ' (não voltou ao estoque)' }}@if (! $loop->last), @endif
                                    @endforeach
                                </div>
                                <div class="small text-body-secondary">
                                    Motivo: {{ $return->reason }} ·
                                    @if ($return->authorizer) Autorizada por {{ $return->authorizer->name }} · @endif
                                    @if ($return->storeCredit)
                                        Vale-troca <strong id="credit-code">{{ $return->storeCredit->code }}</strong> (saldo {{ Money::format($return->storeCredit->balance) }})
                                    @else
                                        Reembolso em {{ $return->method->name }}
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div class="col-12 col-xl-4">
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="h6 mb-3">Comprovante</h2>
                    <a href="{{ route('sales.receipt', $sale->id) }}" target="_blank" rel="noopener" class="btn btn-outline-primary w-100 mb-3"><i class="bi bi-printer" aria-hidden="true"></i> Ver e imprimir</a>

                    <label for="phone" class="form-label">WhatsApp do cliente</label>
                    <input id="phone" type="tel" maxlength="20" class="form-control mb-2" placeholder="DDD e número" wire:model.live.blur="phone">
                    @if ($whatsappUrl)
                        <a id="whatsapp-link" href="{{ $whatsappUrl }}" target="_blank" rel="noopener" class="btn btn-light w-100 mb-3"><i class="bi bi-whatsapp" aria-hidden="true"></i> Abrir no WhatsApp</a>
                    @else
                        <p class="small text-body-secondary">Informe o número com DDD para enviar.</p>
                    @endif

                    <form wire:submit="sendEmail" novalidate>
                        <x-text-field name="email" label="E-mail do cliente" type="email" maxlength="190" />
                        <button type="submit" class="btn btn-light w-100 mt-2" wire:loading.attr="disabled"><i class="bi bi-envelope" aria-hidden="true"></i> Enviar por e-mail</button>
                    </form>
                </div>
            </div>

            @if ($canReturn || $canCancel)
                <div class="card">
                    <div class="card-body">
                        <h2 class="h6 mb-3">Pós-venda</h2>
                        @if ($canReturn)
                            <a href="{{ route('sales.return', $sale->id) }}" class="btn btn-outline-primary w-100 mb-2">Devolução ou troca</a>
                        @endif
                        @if ($canCancel)
                            @if ($confirmingCancel)
                                <form wire:submit="cancel" novalidate>
                                    <div class="mb-2"><x-text-field name="cancelReason" label="Motivo do cancelamento" required maxlength="255" /></div>
                                    @if ($needsSupervisor) <x-supervisor-fields /> @endif
                                    <div class="d-flex gap-2 mt-2">
                                        <button type="submit" class="btn btn-danger" wire:loading.attr="disabled">Confirmar</button>
                                        <button type="button" class="btn btn-light" wire:click="$set('confirmingCancel', false)">Voltar</button>
                                    </div>
                                </form>
                            @else
                                <button type="button" class="btn btn-outline-danger w-100" wire:click="$set('confirmingCancel', true)">Cancelar venda</button>
                            @endif
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
