@php use App\Support\Money; @endphp
<div>
    <div class="row g-3 mb-3">
        <div class="col-12 col-sm-6 col-xl-4"><x-stat-card label="Peças em estoque" :value="$stock['quantity']" icon="boxes" tone="blue" /></div>
        @if ($showCost)
            <div class="col-12 col-sm-6 col-xl-4"><x-money-card label="Estoque pelo custo" :cents="$stock['cost']" icon="box-seam" /></div>
        @endif
        <div class="col-12 col-sm-6 col-xl-4"><x-money-card label="Estoque pelo preço de venda" :cents="$stock['sale']" icon="tags" tone="blue" /></div>
    </div>

    <x-period-filter :error="$rangeError" />

    <div class="card mb-3">
        <div class="card-body pb-0">
            <h2 class="h6">Mais vendidos de {{ $periodFrom->format('d/m/Y') }} a {{ $periodTo->format('d/m/Y') }}</h2>
        </div>
        <div class="table-responsive">
            <table class="table align-middle">
                <caption class="visually-hidden">Produtos mais vendidos</caption>
                <thead>
                    <tr>
                        <th scope="col">Produto</th><th scope="col" class="text-end">Peças</th><th scope="col" class="text-end">Receita</th>
                        @if ($showCost)<th scope="col" class="text-end">Lucro bruto</th><th scope="col" class="text-end">Margem</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($top as $row)
                        <tr>
                            <td>{{ $row->name }}</td>
                            <td class="text-end">{{ $row->quantity }}</td>
                            <td class="text-end text-nowrap">{{ Money::format($row->revenue / 100) }}</td>
                            @if ($showCost)
                                <td class="text-end text-nowrap">{{ Money::format($row->profit / 100) }}</td>
                                <td class="text-end text-nowrap">{{ $row->margin === null ? '-' : number_format($row->margin, 1, ',', '.').'%' }}</td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-4">Nenhuma venda no período.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-body pb-0 d-flex flex-wrap justify-content-between align-items-end gap-2">
            <div>
                <h2 class="h6 mb-1">Estoque parado</h2>
                <p class="small text-body-secondary mb-2">Peças com estoque e sem nenhuma venda nos últimos {{ $days }} dias. Boas candidatas a promoção.</p>
            </div>
            <div class="mb-2 no-print">
                <label for="stalledDays" class="form-label small mb-1">Sem vender há</label>
                <select id="stalledDays" class="form-select form-select-sm" wire:model.live.number="stalledDays">
                    @foreach ([30, 60, 90, 180, 365] as $option)
                        <option value="{{ $option }}">{{ $option }} dias</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table align-middle">
                <caption class="visually-hidden">Estoque parado</caption>
                <thead>
                    <tr>
                        <th scope="col">Produto</th><th scope="col">SKU</th><th scope="col" class="text-end">Estoque</th>
                        @if ($showCost)<th scope="col" class="text-end">Valor pelo custo</th>@endif
                        <th scope="col">Última venda</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($stalled as $row)
                        <tr wire:key="stalled-{{ $row->id }}">
                            <td><a href="{{ route('stock.variant', $row->id) }}">{{ $row->product }}</a></td>
                            <td class="text-nowrap">{{ $row->sku }}</td>
                            <td class="text-end">{{ $row->quantity }}</td>
                            @if ($showCost)<td class="text-end text-nowrap">{{ Money::format($row->cost_value) }}</td>@endif
                            <td class="text-nowrap">{{ $row->last_sale ? \Illuminate\Support\Carbon::parse($row->last_sale)->format('d/m/Y') : 'Nunca vendida' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-4">Nenhuma peça parada. Ótimo!</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
