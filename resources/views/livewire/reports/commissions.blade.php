@php use App\Support\Money; @endphp
<div>
    <x-flash />

    <div class="row g-2 align-items-end mb-3 no-print">
        <div class="col-8 col-md-3">
            <label for="month" class="form-label">Mês</label>
            <input id="month" type="month" class="form-control" wire:model.live="month">
        </div>
        <div class="col-4 col-md-9 text-end">
            <button type="button" class="btn btn-outline-primary btn-sm" onclick="window.print()"><i class="bi bi-printer" aria-hidden="true"></i> Imprimir</button>
        </div>
    </div>

    <p class="small text-body-secondary">
        Comissões de <strong>{{ $monthLabel }}</strong>: percentual de cada vendedor sobre as vendas líquidas
        (vendas concluídas menos as devoluções delas no mês).
        @if ($closed)
            <span class="badge text-bg-success">Mês fechado</span>
        @else
            <span class="badge text-bg-secondary">Prévia: os valores mudam até o fechamento</span>
        @endif
    </p>

    <div class="card mb-3">
        <div class="table-responsive">
            <table class="table align-middle">
                <caption class="visually-hidden">Comissões do mês</caption>
                <thead>
                    <tr>
                        <th scope="col">Vendedor</th>
                        <th scope="col" class="text-end">Vendas</th>
                        <th scope="col" class="text-end">Devoluções</th>
                        <th scope="col" class="text-end">Base</th>
                        <th scope="col" class="text-end">%</th>
                        <th scope="col" class="text-end">Comissão</th>
                        <th scope="col">Pagamento</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr wire:key="commission-{{ $row->user->id }}">
                            <td>{{ $row->user->name }}</td>
                            <td class="text-end text-nowrap">{{ Money::format($row->sales / 100) }}</td>
                            <td class="text-end text-nowrap">{{ Money::format($row->returns / 100) }}</td>
                            <td class="text-end text-nowrap">{{ Money::format($row->base / 100) }}</td>
                            <td class="text-end text-nowrap">
                                @if ($seeAll && ! $row->closing)
                                    <form wire:submit="savePercent({{ $row->user->id }})" class="d-inline-flex gap-1 justify-content-end no-print" novalidate>
                                        <label for="percent-{{ $row->user->id }}" class="visually-hidden">Percentual de {{ $row->user->name }}</label>
                                        <input id="percent-{{ $row->user->id }}" type="number" min="0" max="50" step="0.01" class="form-control form-control-sm grid-small @error('percents.'.$row->user->id) is-invalid @enderror" wire:model="percents.{{ $row->user->id }}" placeholder="0">
                                        <button type="submit" class="btn btn-sm btn-light" aria-label="Salvar percentual de {{ $row->user->name }}"><i class="bi bi-check-lg" aria-hidden="true"></i></button>
                                    </form>
                                @else
                                    {{ number_format($row->percent, 2, ',', '.') }}%
                                @endif
                            </td>
                            <td class="text-end text-nowrap fw-semibold">{{ Money::format($row->amount / 100) }}</td>
                            <td class="small">
                                @if ($row->closing?->payable)
                                    {{ $row->closing->payable->statusLabel() }}, venc. {{ $row->closing->payable->due_date->format('d/m/Y') }}
                                @elseif ($row->closing)
                                    Sem valor a pagar
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-body-secondary py-4">Nenhuma venda com comissão neste mês.</td></tr>
                    @endforelse
                </tbody>
                @if ($rows->count() > 1)
                    <tfoot>
                        <tr class="report-total">
                            <th scope="row">Total</th>
                            <td class="text-end text-nowrap">{{ Money::format($rows->sum('sales') / 100) }}</td>
                            <td class="text-end text-nowrap">{{ Money::format($rows->sum('returns') / 100) }}</td>
                            <td class="text-end text-nowrap">{{ Money::format($rows->sum('base') / 100) }}</td>
                            <td></td>
                            <td class="text-end text-nowrap" id="commission-total">{{ Money::format($rows->sum('amount') / 100) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    @if ($canClose)
        <div class="card no-print">
            <div class="card-body">
                @if ($closed)
                    <p class="mb-2">O mês está fechado e as comissões estão em <a href="{{ route('finance.payables') }}">Contas a pagar</a>.</p>
                    <button type="button" class="btn btn-light text-danger" wire:click="reopen" wire:confirm="Reabrir o mês? As contas a pagar das comissões serão canceladas.">Reabrir mês</button>
                @else
                    <h2 class="h6">Fechar o mês</h2>
                    <p class="small text-body-secondary">Congela os valores acima e lança a comissão de cada vendedor em Contas a pagar, na categoria Comissões.</p>
                    <form wire:submit="close" class="row g-2 align-items-end" novalidate>
                        <div class="col-8 col-md-3">
                            <label for="dueDate" class="form-label">Pagar em</label>
                            <input id="dueDate" type="date" class="form-control @error('dueDate') is-invalid @enderror" wire:model="dueDate">
                            @error('dueDate') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-4 col-md-3">
                            <button type="submit" class="btn btn-primary" wire:confirm="Fechar as comissões deste mês?">Fechar mês</button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    @endif
</div>
