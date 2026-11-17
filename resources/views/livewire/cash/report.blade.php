@php use App\Support\Money; @endphp
<div>
    <x-flash />

    <div class="mb-3"><a href="{{ route('cash.history') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Voltar ao histórico</a></div>

    <div class="card mb-3">
        <div class="card-body">
            <h2 class="h5 mb-1">Caixa #{{ $session->id }} · {{ $session->user->name }}</h2>
            <p class="small text-body-secondary">
                Aberto em {{ $session->opened_at->format('d/m/Y H:i') }}
                @unless ($session->isOpen()) · fechado em {{ $session->closed_at->format('d/m/Y H:i') }} por {{ $session->closer->name }} @endunless
                · {{ $salesCount }} {{ $salesCount === 1 ? 'venda' : 'vendas' }}
            </p>
            <div class="row g-3">
                <div class="col-6 col-md-3"><div class="stat-value">{{ Money::format($session->opening_amount) }}</div><div class="stat-label">Saldo inicial</div></div>
                <div class="col-6 col-md-3"><div class="stat-value">{{ Money::format($session->isOpen() ? $expected / 100 : $session->expected_amount) }}</div><div class="stat-label">Esperado em dinheiro</div></div>
                @unless ($session->isOpen())
                    <div class="col-6 col-md-3"><div class="stat-value">{{ Money::format($session->counted_amount) }}</div><div class="stat-label">Contado</div></div>
                    <div class="col-6 col-md-3"><div @class(['stat-value', 'text-danger' => $session->difference < 0, 'text-success' => $session->difference > 0])>{{ Money::format($session->difference) }}</div><div class="stat-label">Diferença</div></div>
                @endunless
            </div>
            @if ($session->closing_notes)
                <p class="mt-3 mb-0"><strong>Observação:</strong> {{ $session->closing_notes }}</p>
            @endif
        </div>
    </div>

    @if ($session->isOpen() && $session->user_id !== auth()->id())
        @can('caixa.gerir')
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="h6 mb-3">Fechar caixa do operador</h2>
                    <form wire:submit="closeForOperator" class="row g-3 align-items-start" novalidate>
                        <div class="col-12 col-md-4"><x-text-field name="countedAmount" label="Dinheiro contado (R$)" type="number" required min="0" step="0.01" /></div>
                        <div class="col-12 col-md-5"><x-text-field name="closingNotes" label="Observação" maxlength="255" /></div>
                        <div class="col-12 col-md-3 form-actions-aligned"><button type="submit" class="btn btn-primary" wire:confirm="Fechar este caixa?">Fechar caixa</button></div>
                    </form>
                </div>
            </div>
        @endcan
    @endif

    <div class="row g-3">
        <div class="col-12 col-xl-4">
            <div class="card h-100">
                <div class="card-body">
                    <h2 class="h6">Vendas por forma de pagamento</h2>
                    @forelse ($methodTotals as $row)
                        <div class="d-flex justify-content-between"><span>{{ $row->name }}</span><strong>{{ Money::format($row->total) }}</strong></div>
                    @empty
                        <p class="small text-body-secondary mb-0">Nenhuma venda neste caixa.</p>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-8">
            <div class="card">
                <div class="card-body pb-0"><h2 class="h6">Movimentações em dinheiro</h2></div>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead><tr><th scope="col">Data e hora</th><th scope="col">Tipo</th><th scope="col">Motivo</th><th scope="col">Usuário</th><th scope="col" class="text-end">Valor</th></tr></thead>
                        <tbody>
                            @foreach ($movements as $movement)
                                <tr wire:key="movement-{{ $movement->id }}">
                                    <td class="text-nowrap">{{ $movement->created_at->format('d/m H:i') }}</td>
                                    <td class="text-nowrap">{{ $movement->type->label() }}</td>
                                    <td>{{ $movement->reason }}</td>
                                    <td>{{ $movement->user->name }}</td>
                                    <td @class(['text-end', 'text-nowrap', 'fw-bold', 'text-success' => $movement->amount > 0, 'text-danger' => $movement->amount < 0])>{{ Money::format($movement->amount) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
