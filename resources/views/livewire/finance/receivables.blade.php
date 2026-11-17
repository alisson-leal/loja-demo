@php use App\Support\Money; @endphp
<div>
    <x-flash />

    <div class="row g-3 mb-3">
        <div class="col-12 col-sm-6 col-xl-3"><x-money-card label="Atrasados (a conferir)" :cents="$cards['late']" icon="exclamation-octagon" /></div>
        <div class="col-12 col-sm-6 col-xl-3"><x-money-card label="Previsto para hoje" :cents="$cards['today']" icon="calendar-event" /></div>
        <div class="col-12 col-sm-6 col-xl-3"><x-money-card label="Próximos 7 dias" :cents="$cards['week']" icon="calendar-week" tone="blue" /></div>
        <div class="col-12 col-sm-6 col-xl-3"><x-money-card label="Próximos 30 dias" :cents="$cards['month']" icon="calendar3" tone="blue" /></div>
    </div>

    <p class="small text-body-secondary">
        Valores líquidos, já descontada a taxa da maquininha. Quando o dinheiro cair na conta, selecione e confirme o recebimento.
        Dinheiro e Pix entram como recebidos na hora da venda.
    </p>

    <div class="row g-2 align-items-end mb-3">
        <div class="col-6 col-md-3">
            <label for="view" class="form-label">Mostrar</label>
            <select id="view" class="form-select" wire:model.live="view">
                <option value="ate_hoje">A receber até hoje</option>
                <option value="futuros">A receber depois de hoje</option>
                <option value="recebidos">Recebidos</option>
            </select>
        </div>
        <div class="col-6 col-md-3">
            <label for="methodId" class="form-label">Forma</label>
            <select id="methodId" class="form-select" wire:model.live="methodId">
                <option value="">Todas</option>
                @foreach ($methods as $method)
                    <option value="{{ $method->id }}">{{ $method->name }}</option>
                @endforeach
            </select>
        </div>
        @can('financeiro.receber')
            @if ($view !== 'recebidos')
                <div class="col-6 col-md-2">
                    <label for="receivedDate" class="form-label">Caiu na conta em</label>
                    <input id="receivedDate" type="date" class="form-control @error('receivedDate') is-invalid @enderror" wire:model="receivedDate">
                    @error('receivedDate') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-6 col-md-4 d-flex flex-wrap gap-2 justify-content-end">
                    <button type="button" class="btn btn-light" wire:click="selectAllDue">Selecionar todos até hoje</button>
                    <button type="button" class="btn btn-primary" wire:click="confirm" @disabled(count($selected) === 0)>Confirmar recebimento ({{ count($selected) }})</button>
                </div>
            @endif
        @endcan
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle">
                <caption class="visually-hidden">Recebimentos</caption>
                <thead>
                    <tr>
                        @if ($view !== 'recebidos') @can('financeiro.receber') <th scope="col"><span class="visually-hidden">Selecionar</span></th> @endcan @endif
                        <th scope="col">{{ $view === 'recebidos' ? 'Recebido em' : 'Previsto para' }}</th>
                        <th scope="col">Forma</th>
                        <th scope="col">Venda</th>
                        <th scope="col" class="text-end">Bruto</th>
                        <th scope="col" class="text-end">Taxa</th>
                        <th scope="col" class="text-end">Líquido</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($receivables as $receivable)
                        <tr wire:key="receivable-{{ $receivable->id }}">
                            @if ($view !== 'recebidos')
                                @can('financeiro.receber')
                                    <td><input class="form-check-input" type="checkbox" value="{{ $receivable->id }}" wire:model.live="selected" aria-label="Selecionar recebimento de {{ Money::format($receivable->net_amount) }}"></td>
                                @endcan
                            @endif
                            <td class="text-nowrap">
                                {{ ($view === 'recebidos' ? $receivable->received_at : $receivable->due_date)->format('d/m/Y') }}
                                @if ($view !== 'recebidos' && $receivable->due_date->lt(today()))<span class="badge text-bg-danger">Atrasado</span>@endif
                            </td>
                            <td>{{ $receivable->method->name }}@if ($receivable->installments > 1) <span class="text-body-secondary">({{ $receivable->installment }}/{{ $receivable->installments }})</span>@endif</td>
                            <td class="text-nowrap"><a href="{{ route('sales.show', $receivable->sale_id) }}">#{{ $receivable->sale->number() }}</a><div class="small text-body-secondary">{{ $receivable->sale->created_at->format('d/m/Y') }}</div></td>
                            <td class="text-end text-nowrap">{{ Money::format($receivable->gross_amount) }}</td>
                            <td class="text-end text-nowrap">{{ Money::format($receivable->fee_amount) }}</td>
                            <td class="text-end text-nowrap fw-semibold">{{ Money::format($receivable->net_amount) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-body-secondary py-4">Nenhum recebimento nesta lista.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $receivables->links() }}</div>
</div>
