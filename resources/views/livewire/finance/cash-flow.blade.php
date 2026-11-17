@php use App\Support\Money; @endphp
<div>
    <x-period-filter :error="$rangeError" future>
        <div class="form-check align-self-center ms-2">
            <input class="form-check-input" type="checkbox" id="allDays" wire:model.live="allDays">
            <label class="form-check-label small" for="allDays">Mostrar dias sem movimento</label>
        </div>
    </x-period-filter>

    @if ($flow['overdue_payables'] > 0 || $flow['late_receivables'] > 0)
        <div class="alert alert-warning">
            @if ($flow['overdue_payables'] > 0)
                Há <strong>{{ Money::format($flow['overdue_payables'] / 100) }}</strong> em <a href="{{ route('finance.payables') }}">contas vencidas</a> ainda não pagas.
            @endif
            @if ($flow['late_receivables'] > 0)
                Há <strong>{{ Money::format($flow['late_receivables'] / 100) }}</strong> de cartão com data prevista já passada: <a href="{{ route('finance.receivables') }}">confira os recebimentos</a>.
            @endif
        </div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-12 col-sm-6 col-xl-3"><x-money-card label="Entradas realizadas" :cents="$flow['totals']['received']" icon="arrow-down-circle" tone="blue" /></div>
        <div class="col-12 col-sm-6 col-xl-3"><x-money-card label="Entradas previstas" :cents="$flow['totals']['expected']" icon="hourglass-split" tone="blue" /></div>
        <div class="col-12 col-sm-6 col-xl-3"><x-money-card label="Saídas pagas" :cents="$flow['totals']['paid']" icon="arrow-up-circle" /></div>
        <div class="col-12 col-sm-6 col-xl-3"><x-money-card label="Saídas a pagar" :cents="$flow['totals']['open']" icon="receipt-cutoff" /></div>
    </div>

    <div class="card">
        <div class="card-body pb-0">
            <h2 class="h6">Movimento diário de {{ $periodFrom->format('d/m/Y') }} a {{ $periodTo->format('d/m/Y') }}</h2>
            <p class="small text-body-secondary mb-2">Entradas pelo valor líquido (depois da taxa do cartão), na data em que o dinheiro cai. Saídas pela data de pagamento ou, se em aberto, pelo vencimento.</p>
        </div>
        <div class="table-responsive">
            <table class="table align-middle">
                <caption class="visually-hidden">Fluxo de caixa diário</caption>
                <thead>
                    <tr>
                        <th scope="col">Dia</th>
                        <th scope="col" class="text-end">Entrou</th>
                        <th scope="col" class="text-end">Vai entrar</th>
                        <th scope="col" class="text-end">Pago</th>
                        <th scope="col" class="text-end">A pagar</th>
                        <th scope="col" class="text-end">Saldo do dia</th>
                        <th scope="col" class="text-end">Acumulado</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($days as $day)
                        <tr wire:key="day-{{ $day['date']->toDateString() }}" @class(['table-active' => $day['date']->isToday()])>
                            <td class="text-nowrap">{{ $day['date']->format('d/m') }} <span class="small text-body-secondary">{{ $day['date']->locale('pt_BR')->isoFormat('ddd') }}</span></td>
                            <td class="text-end text-nowrap">{{ $day['received'] ? Money::format($day['received'] / 100) : '-' }}</td>
                            <td class="text-end text-nowrap">{{ $day['expected'] ? Money::format($day['expected'] / 100) : '-' }}</td>
                            <td class="text-end text-nowrap">{{ $day['paid'] ? Money::format($day['paid'] / 100) : '-' }}</td>
                            <td class="text-end text-nowrap">{{ $day['open'] ? Money::format($day['open'] / 100) : '-' }}</td>
                            <td @class(['text-end', 'text-nowrap', 'text-danger' => $day['balance'] < 0])>{{ Money::format($day['balance'] / 100) }}</td>
                            <td @class(['text-end', 'text-nowrap', 'fw-semibold', 'text-danger' => $day['running'] < 0])>{{ Money::format($day['running'] / 100) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-body-secondary py-4">Nenhum movimento no período.</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="report-total">
                        <th scope="row">Total</th>
                        <td class="text-end text-nowrap">{{ Money::format($flow['totals']['received'] / 100) }}</td>
                        <td class="text-end text-nowrap">{{ Money::format($flow['totals']['expected'] / 100) }}</td>
                        <td class="text-end text-nowrap">{{ Money::format($flow['totals']['paid'] / 100) }}</td>
                        <td class="text-end text-nowrap">{{ Money::format($flow['totals']['open'] / 100) }}</td>
                        <td @class(['text-end', 'text-nowrap', 'text-danger' => $flow['totals']['balance'] < 0]) id="flow-balance">{{ Money::format($flow['totals']['balance'] / 100) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
