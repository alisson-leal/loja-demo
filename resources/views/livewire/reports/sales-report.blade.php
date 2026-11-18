@php use App\Support\Money; @endphp
<div>
    <div class="row g-2"><x-store-filter :options="$storeOptions" /></div>
    <x-period-filter :error="$rangeError" />

    <p class="small text-body-secondary">De {{ $periodFrom->format('d/m/Y') }} a {{ $periodTo->format('d/m/Y') }}. Vendas canceladas não entram; as devoluções do período são descontadas.</p>

    <div class="row g-3 mb-3">
        <div class="col-12 col-sm-6 col-xl-3"><x-money-card label="Vendas brutas" :cents="$summary['gross']" icon="bag-check" /></div>
        <div class="col-12 col-sm-6 col-xl-3"><x-money-card label="Devoluções" :cents="$summary['returns']" icon="arrow-return-left" /></div>
        <div class="col-12 col-sm-6 col-xl-3"><x-money-card label="Vendas líquidas" :cents="$summary['net']" icon="cash-coin" tone="blue" /></div>
        <div class="col-12 col-sm-6 col-xl-3"><x-money-card label="Ticket médio" :cents="$summary['ticket']" icon="graph-up-arrow" tone="blue" /></div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <dl class="row mb-0 small">
                <dt class="col-7 col-md-3">Vendas concluídas</dt><dd class="col-5 col-md-3" id="sales-count">{{ $summary['count'] }}</dd>
                <dt class="col-7 col-md-3">Peças vendidas</dt><dd class="col-5 col-md-3">{{ $summary['items'] }}</dd>
                <dt class="col-7 col-md-3">Descontos concedidos</dt><dd class="col-5 col-md-3">{{ Money::format($summary['discounts'] / 100) }}</dd>
                <dt class="col-7 col-md-3">Vendas canceladas</dt><dd class="col-5 col-md-3">{{ $summary['cancelled'] }}</dd>
            </dl>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-xl-7">
            <div class="card h-100">
                <div class="card-body pb-0"><h2 class="h6">Por forma de pagamento</h2></div>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <caption class="visually-hidden">Vendas por forma de pagamento</caption>
                        <thead><tr><th scope="col">Forma</th><th scope="col" class="text-end">Pagamentos</th><th scope="col" class="text-end">Bruto</th><th scope="col" class="text-end">Taxa</th><th scope="col" class="text-end">Líquido</th></tr></thead>
                        <tbody>
                            @forelse ($byMethod as $row)
                                <tr>
                                    <td>{{ $row->name }}</td>
                                    <td class="text-end">{{ $row->payments }}</td>
                                    <td class="text-end text-nowrap">{{ Money::format($row->gross / 100) }}</td>
                                    <td class="text-end text-nowrap">{{ Money::format($row->fee / 100) }}</td>
                                    <td class="text-end text-nowrap">{{ Money::format($row->net / 100) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-4">Nenhuma venda no período.</td></tr>
                            @endforelse
                        </tbody>
                        @if ($byMethod->isNotEmpty())
                            <tfoot>
                                <tr class="report-total">
                                    <th scope="row">Total</th>
                                    <td class="text-end">{{ $byMethod->sum('payments') }}</td>
                                    <td class="text-end text-nowrap">{{ Money::format($byMethod->sum('gross') / 100) }}</td>
                                    <td class="text-end text-nowrap" id="fees-total">{{ Money::format($byMethod->sum('fee') / 100) }}</td>
                                    <td class="text-end text-nowrap">{{ Money::format($byMethod->sum('net') / 100) }}</td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-5">
            <div class="card h-100">
                <div class="card-body pb-0"><h2 class="h6">Por vendedor</h2></div>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <caption class="visually-hidden">Vendas por vendedor</caption>
                        <thead><tr><th scope="col">Vendedor</th><th scope="col" class="text-end">Vendas</th><th scope="col" class="text-end">Total</th><th scope="col" class="text-end">Ticket</th></tr></thead>
                        <tbody>
                            @forelse ($bySeller as $row)
                                <tr>
                                    <td>{{ $row->name }}</td>
                                    <td class="text-end">{{ $row->sales }}</td>
                                    <td class="text-end text-nowrap">{{ Money::format($row->total / 100) }}</td>
                                    <td class="text-end text-nowrap">{{ Money::format($row->ticket / 100) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-4">Nenhuma venda no período.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body pb-0"><h2 class="h6">Por categoria</h2></div>
        <div class="table-responsive">
            <table class="table align-middle">
                <caption class="visually-hidden">Vendas por categoria</caption>
                <thead>
                    <tr>
                        <th scope="col">Categoria</th><th scope="col" class="text-end">Peças</th><th scope="col" class="text-end">Receita</th>
                        @if ($showCost)<th scope="col" class="text-end">Custo</th><th scope="col" class="text-end">Lucro bruto</th><th scope="col" class="text-end">Margem</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($byCategory as $row)
                        <tr>
                            <td>{{ $row->name }}</td>
                            <td class="text-end">{{ $row->quantity }}</td>
                            <td class="text-end text-nowrap">{{ Money::format($row->revenue / 100) }}</td>
                            @if ($showCost)
                                <td class="text-end text-nowrap">{{ Money::format($row->cost / 100) }}</td>
                                <td class="text-end text-nowrap">{{ Money::format($row->profit / 100) }}</td>
                                <td class="text-end text-nowrap">{{ $row->margin === null ? '-' : number_format($row->margin, 1, ',', '.').'%' }}</td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-body-secondary py-4">Nenhuma venda no período.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
