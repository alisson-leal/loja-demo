@php use App\Support\Money; use App\Models\Consignment; @endphp
<div>
    <x-flash />

    <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
        <a href="{{ route('consignments.index') }}" class="align-self-center"><i class="bi bi-arrow-left" aria-hidden="true"></i> Voltar às condicionais</a>
        <div class="d-flex flex-wrap gap-2">
            @if ($whatsapp)
                <a href="{{ $whatsapp }}" target="_blank" rel="noopener" class="btn btn-success"><i class="bi bi-whatsapp" aria-hidden="true"></i> Lembrar o cliente</a>
            @endif
            <a href="{{ route('consignments.print', $consignment->id) }}" target="_blank" class="btn btn-outline-primary"><i class="bi bi-printer" aria-hidden="true"></i> Comprovante</a>
            @if ($consignment->status === Consignment::AWAITING_SALE)
                @can('pdv.vender')
                    <button type="button" class="btn btn-primary" wire:click="toCashier">Cobrar no caixa</button>
                @endcan
                @can('condicional.acertar')
                    <button type="button" class="btn btn-light text-danger" wire:click="closeWithoutSale" wire:confirm="O cliente desistiu das peças? Elas voltam a ficar disponíveis.">Encerrar sem venda</button>
                @endcan
            @endif
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between gap-2">
                <div>
                    <h2 class="h6 mb-1">{{ $consignment->customer->name }}</h2>
                    <div class="small text-body-secondary">
                        Aberta por {{ $consignment->user->name }} em {{ $consignment->created_at->format('d/m/Y H:i') }}
                        · devolver até <strong>{{ $consignment->due_date->format('d/m/Y') }}</strong>
                    </div>
                    @if ($consignment->notes) <div class="small mt-1">{{ $consignment->notes }}</div> @endif
                </div>
                <div class="text-end">
                    <span class="badge text-bg-{{ $consignment->statusTone() }}">{{ $consignment->statusLabel() }}</span>
                    <div class="small mt-1">Valor das peças: <strong>{{ Money::format($total / 100) }}</strong></div>
                    @if ($consignment->sale)
                        <div class="small">Paga na <a href="{{ route('sales.show', $consignment->sale_id) }}">venda #{{ $consignment->sale->number() }}</a></div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <form wire:submit="settle" class="card" novalidate>
        <div class="table-responsive">
            <table class="table align-middle">
                <caption class="visually-hidden">Peças da condicional</caption>
                <thead>
                    <tr>
                        <th scope="col">Peça</th>
                        <th scope="col" class="text-end">Preço</th>
                        <th scope="col">Levou</th>
                        <th scope="col">{{ $consignment->status === Consignment::OPEN ? 'Devolveu' : 'Devolvidas' }}</th>
                        @if ($consignment->status !== Consignment::OPEN) <th scope="col">Ficou</th> @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($consignment->items as $item)
                        <tr wire:key="item-{{ $item->id }}">
                            <td>{{ $item->description }}</td>
                            <td class="text-end text-nowrap">{{ Money::format($item->unit_price) }}</td>
                            <td>{{ $item->quantity }}</td>
                            @if ($consignment->status === Consignment::OPEN)
                                <td>
                                    <label for="returned-{{ $item->id }}" class="visually-hidden">Peças devolvidas de {{ $item->description }}</label>
                                    <input id="returned-{{ $item->id }}" type="number" min="0" max="{{ $item->quantity }}" class="form-control form-control-sm grid-small" wire:model="returned.{{ $item->id }}" @cannot('condicional.acertar') disabled @endcannot>
                                </td>
                            @else
                                <td>{{ $item->returned_quantity }}</td>
                                <td class="fw-semibold">{{ $item->kept_quantity }}</td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($consignment->status === Consignment::OPEN)
            @can('condicional.acertar')
                <div class="card-body border-top">
                    <p class="small text-body-secondary">Informe quantas peças de cada item voltaram. O que não voltar fica com o cliente e vai para o caixa para pagamento.</p>
                    <button type="submit" class="btn btn-primary" wire:confirm="Registrar o acerto desta condicional?">Fazer o acerto</button>
                </div>
            @endcan
        @endif
    </form>
</div>
