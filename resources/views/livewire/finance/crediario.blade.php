@php use App\Support\Money; use App\Models\Receivable; @endphp
<div>
    <x-flash />

    <div class="row g-3 mb-3">
        <div class="col-12 col-sm-6 col-xl-3"><x-money-card label="Em aberto" :cents="$cards['open']" icon="journal-text" tone="blue" /></div>
        <div class="col-12 col-sm-6 col-xl-3"><x-money-card label="Vencido" :cents="$cards['overdue']" icon="exclamation-octagon" /></div>
        <div class="col-12 col-sm-6 col-xl-3"><x-money-card label="Vence em 7 dias" :cents="$cards['week']" icon="calendar-week" tone="blue" /></div>
        <div class="col-12 col-sm-6 col-xl-3"><x-money-card label="Recebido no mês" :cents="$cards['received']" icon="check2-circle" /></div>
    </div>

    <div class="row g-2 align-items-end mb-3">
        <div class="col-6 col-md-3">
            <label for="view" class="form-label">Mostrar</label>
            <select id="view" class="form-select" wire:model.live="view">
                <option value="abertas">Parcelas em aberto</option>
                <option value="vencidas">Vencidas</option>
                <option value="semana">Vencem em 7 dias</option>
                <option value="recebidas">Recebidas</option>
            </select>
        </div>
        <div class="col-6 col-md-4">
            <label for="search" class="form-label">Cliente</label>
            <input id="search" type="search" class="form-control" placeholder="Nome, telefone ou CPF" wire:model.live.debounce.300ms="search">
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle">
                <caption class="visually-hidden">Parcelas do crediário</caption>
                <thead>
                    <tr>
                        <th scope="col">Cliente</th>
                        <th scope="col">Parcela</th>
                        <th scope="col">Vencimento</th>
                        <th scope="col" class="text-end">Valor</th>
                        <th scope="col" class="text-end">{{ $view === 'recebidas' ? 'Recebido' : 'A pagar hoje' }}</th>
                        <th scope="col" class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($installments as $installment)
                        @php $charge = $charges[$installment->id]; @endphp
                        <tr wire:key="installment-{{ $installment->id }}">
                            <td>
                                {{ $installment->sale->customer?->name ?? '-' }}
                                <div class="small"><a href="{{ route('sales.show', $installment->sale_id) }}">Venda #{{ $installment->sale->number() }}</a> · <a href="{{ route('crediario.carne', $installment->sale_id) }}" target="_blank">carnê</a></div>
                            </td>
                            <td>{{ $installment->installment }}/{{ $installment->installments }}</td>
                            <td class="text-nowrap">
                                {{ $installment->due_date->format('d/m/Y') }}
                                @if ($installment->isOverdue()) <span class="badge text-bg-danger">{{ $charge['days'] }} dia(s)</span> @endif
                            </td>
                            <td class="text-end text-nowrap">{{ Money::format($installment->net_amount) }}</td>
                            <td class="text-end text-nowrap fw-semibold">
                                @if ($installment->status === Receivable::RECEIVED)
                                    {{ Money::format($installment->received_amount ?? $installment->net_amount) }}
                                    <div class="small text-body-secondary">em {{ $installment->received_at->format('d/m/Y') }}{{ $installment->charges_waived ? ', encargos dispensados' : '' }}</div>
                                @elseif ($charge)
                                    {{ Money::format($charge['total'] / 100) }}
                                    @if ($charge['fee'] + $charge['interest'] > 0)
                                        <div class="small text-body-secondary">multa {{ Money::format($charge['fee'] / 100) }} + juros {{ Money::format($charge['interest'] / 100) }}</div>
                                    @endif
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                                    @if ($installment->status === Receivable::PENDING)
                                        @if ($installment->isOverdue() && ($link = $reminder($installment)))
                                            <a href="{{ $link }}" target="_blank" rel="noopener" class="btn btn-sm btn-success" aria-label="Lembrar {{ $installment->sale->customer?->name }} pelo WhatsApp"><i class="bi bi-whatsapp" aria-hidden="true"></i></a>
                                        @endif
                                        @can('crediario.receber')
                                            <button type="button" class="btn btn-sm btn-primary" wire:click="startReceive({{ $installment->id }})">Receber</button>
                                        @endcan
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @if ($receivingId === $installment->id)
                            <tr wire:key="receive-{{ $installment->id }}">
                                <td colspan="6">
                                    <form wire:submit="receive" class="d-flex flex-wrap gap-2 align-items-center" novalidate>
                                        <label for="methodId" class="small">Recebido em</label>
                                        <select id="methodId" class="form-select form-select-sm w-auto" wire:model="methodId">
                                            @foreach ($methods as $method)
                                                <option value="{{ $method->id }}">{{ $method->name }}</option>
                                            @endforeach
                                        </select>
                                        @can('crediario.gerir')
                                            @if ($charge && $charge['fee'] + $charge['interest'] > 0)
                                                <div class="form-check mb-0">
                                                    <input class="form-check-input" type="checkbox" id="waive" wire:model="waive">
                                                    <label class="form-check-label small" for="waive">Dispensar multa e juros</label>
                                                </div>
                                            @endif
                                        @endcan
                                        <button type="submit" class="btn btn-sm btn-primary">Confirmar recebimento</button>
                                        <button type="button" class="btn btn-sm btn-light" wire:click="$set('receivingId', null)">Voltar</button>
                                        <span class="small text-body-secondary">Em dinheiro, o valor entra no seu caixa aberto.</span>
                                    </form>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr><td colspan="6" class="text-center text-body-secondary py-4">Nenhuma parcela encontrada.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $installments->links() }}</div>
</div>
