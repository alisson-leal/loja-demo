@php
    use App\Support\Money;
    $pct = fn ($cents) => $r['net'] > 0 ? number_format($cents / $r['net'] * 100, 1, ',', '.').'%' : '-';
    $minus = fn ($cents) => $cents > 0 ? '- '.Money::format($cents / 100) : Money::format(0);
@endphp
<div>
    <x-period-filter :error="$rangeError" />

    <div class="row g-3 mb-3">
        <div class="col-12 col-sm-6 col-xl-3"><x-money-card label="Vendas líquidas" :cents="$r['net']" icon="cash-coin" tone="blue" /></div>
        <div class="col-12 col-sm-6 col-xl-3"><x-money-card label="Lucro bruto" :cents="$r['gross_profit']" icon="graph-up-arrow" tone="blue" /></div>
        <div class="col-12 col-sm-6 col-xl-3"><x-money-card label="Despesas" :cents="$r['expense_total']" icon="receipt-cutoff" /></div>
        <div class="col-12 col-sm-6 col-xl-3"><x-money-card :label="$r['result'] >= 0 ? 'Lucro do período' : 'Prejuízo do período'" :cents="$r['result']" icon="trophy" /></div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-7">
            <div class="card">
                <div class="card-body pb-0">
                    <h2 class="h6">Resultado de {{ $periodFrom->format('d/m/Y') }} a {{ $periodTo->format('d/m/Y') }}</h2>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <caption class="visually-hidden">Demonstrativo de resultado</caption>
                        <thead><tr><th scope="col">Item</th><th scope="col" class="text-end">Valor</th><th scope="col" class="text-end">% das vendas</th></tr></thead>
                        <tbody>
                            <tr><td>Vendas brutas</td><td class="text-end text-nowrap">{{ Money::format($r['gross'] / 100) }}</td><td></td></tr>
                            <tr><td>(-) Devoluções</td><td class="text-end text-nowrap">{{ $minus($r['returns']) }}</td><td></td></tr>
                            <tr class="report-total"><th scope="row">= Vendas líquidas</th><td class="text-end text-nowrap">{{ Money::format($r['net'] / 100) }}</td><td class="text-end">100%</td></tr>
                            <tr><td>(-) Custo das peças vendidas</td><td class="text-end text-nowrap">{{ $minus($r['cost']) }}</td><td class="text-end">{{ $pct($r['cost']) }}</td></tr>
                            <tr><td>(-) Taxas de cartão</td><td class="text-end text-nowrap">{{ $minus($r['fees']) }}</td><td class="text-end">{{ $pct($r['fees']) }}</td></tr>
                            <tr class="report-total"><th scope="row">= Lucro bruto</th><td class="text-end text-nowrap" id="gross-profit">{{ Money::format($r['gross_profit'] / 100) }}</td><td class="text-end">{{ $pct($r['gross_profit']) }}</td></tr>
                            @foreach ($r['expenses'] as $expense)
                                <tr><td>(-) {{ $expense->name }}</td><td class="text-end text-nowrap">{{ $minus($expense->total) }}</td><td class="text-end">{{ $pct($expense->total) }}</td></tr>
                            @endforeach
                            @if ($r['expenses']->isEmpty())
                                <tr><td class="text-body-secondary">(-) Despesas</td><td class="text-end text-nowrap">{{ Money::format(0) }}</td><td></td></tr>
                            @endif
                            @if ($r['interest'] > 0)
                                <tr><td>(+) Multa e juros do crediário</td><td class="text-end text-nowrap">{{ Money::format($r['interest'] / 100) }}</td><td class="text-end">{{ $pct($r['interest']) }}</td></tr>
                            @endif
                            <tr class="report-total">
                                <th scope="row">= {{ $r['result'] >= 0 ? 'Lucro' : 'Prejuízo' }} do período</th>
                                <td @class(['text-end', 'text-nowrap', 'text-danger' => $r['result'] < 0]) id="period-result">{{ Money::format($r['result'] / 100) }}</td>
                                <td class="text-end">{{ $pct($r['result']) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-5">
            <div class="card">
                <div class="card-body small">
                    <h2 class="h6">Como ler</h2>
                    <ul class="mb-0">
                        <li><strong>Custo das peças</strong> usa o custo de cada produto no momento da venda; peças devolvidas saem do custo.</li>
                        <li><strong>Taxas de cartão</strong> seguem as condições cadastradas em Formas de pagamento.</li>
                        <li><strong>Despesas</strong> são as contas a pagar com vencimento no período (pagas ou não), exceto compra de mercadorias e reembolsos de devolução.</li>
                        <li>Compras de mercadorias com vencimento no período: <strong>{{ Money::format($r['merchandise'] / 100) }}</strong>. Elas saem do caixa, mas o custo só entra no resultado quando as peças são vendidas.</li>
                        <li>É um resultado gerencial, sem impostos sobre o lucro. Para a contabilidade oficial, consulte o contador.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
