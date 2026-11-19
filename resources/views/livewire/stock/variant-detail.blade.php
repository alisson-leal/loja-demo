@php use App\Support\Money; @endphp
<div>
    <div class="mb-3"><a href="{{ route('stock.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Voltar aos saldos</a></div>

    <div class="card mb-3">
        <div class="card-body">
            <h2 class="h5 mb-1">{{ $variant->fullName() }}</h2>
            <p class="small text-body-secondary">SKU {{ $variant->sku }}@if ($variant->barcode) · Código de barras {{ $variant->barcode }}@endif</p>
            @php($here = $variant->stockIn())
            <div class="row g-3">
                <div class="col-6 col-md-3"><div class="stat-value">{{ $here['on_hand'] }}</div><div class="stat-label">Saldo físico{{ $multiple ? ' nesta loja' : '' }}</div></div>
                <div class="col-6 col-md-3"><div class="stat-value">{{ $here['reserved'] }}</div><div class="stat-label">Reservado</div></div>
                <div class="col-6 col-md-3"><div class="stat-value">{{ $here['available'] }}</div><div class="stat-label">Disponível</div></div>
                <div class="col-6 col-md-3"><div class="stat-value">{{ $variant->min_stock }}</div><div class="stat-label">Estoque mínimo</div></div>
            </div>
            @if ($multiple)
                <div class="table-responsive mt-3">
                    <table class="table table-sm align-middle mb-0">
                        <caption class="visually-hidden">Saldo por loja</caption>
                        <thead><tr><th scope="col">Loja</th><th scope="col">Físico</th><th scope="col">Reservado</th><th scope="col">Disponível</th></tr></thead>
                        <tbody>
                            @foreach ($stores as $store)
                                @php($s = $variant->stockIn($store->id))
                                <tr wire:key="store-stock-{{ $store->id }}"><td>{{ $store->name }}</td><td>{{ $s['on_hand'] }}</td><td>{{ $s['reserved'] }}</td><td class="fw-semibold">{{ $s['available'] }}</td></tr>
                            @endforeach
                            <tr class="report-total"><th scope="row">Rede</th><td>{{ $variant->stock_on_hand }}</td><td>{{ $variant->stock_reserved }}</td><td>{{ $variant->available() }}</td></tr>
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    @if ($variant->product->tracks_serial)
        <div class="card mb-3" id="serial-units">
            <div class="card-body">
                <h2 class="h6">Números de série em estoque ({{ $units->count() }})</h2>
                @if ($units->isEmpty())
                    <p class="text-body-secondary mb-0">Nenhuma unidade em estoque.</p>
                @else
                    <ul class="list-inline mb-0">
                        @foreach ($units as $unit)
                            <li class="list-inline-item mb-1" wire:key="unit-{{ $unit->id }}">
                                @if ($canSeeWarranty)
                                    <a href="{{ route('warranty.index', ['busca' => $unit->serial]) }}" class="badge text-bg-light text-decoration-none text-break">{{ $unit->serial }}</a>
                                @else
                                    <span class="badge text-bg-light text-break">{{ $unit->serial }}</span>
                                @endif
                                @if ($multiple && $unit->store)<span class="small text-body-secondary">{{ $unit->store->name }}</span>@endif
                                @if ($unit->status === \App\Models\SerialNumber::IN_TRANSIT)<span class="small text-warning">em transferência</span>@endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-body pb-0"><h2 class="h6">Movimentações</h2></div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th scope="col">Data e hora</th><th scope="col">Tipo</th><th scope="col">Quantidade</th><th scope="col">Saldo após</th><th scope="col">Motivo</th><th scope="col">Usuário</th></tr></thead>
                <tbody>
                    @forelse ($movements as $movement)
                        <tr wire:key="movement-{{ $movement->id }}">
                            <td class="text-nowrap">{{ $movement->created_at->format('d/m/Y H:i') }}</td>
                            <td class="text-nowrap">{{ $movement->type->label() }}</td>
                            <td @class(['fw-bold', 'text-success' => $movement->quantity > 0, 'text-danger' => $movement->quantity < 0])>{{ $movement->quantity > 0 ? '+' : '' }}{{ $movement->quantity }}</td>
                            <td>{{ $movement->balance_after }}</td>
                            <td>{{ $movement->reason }}@if ($multiple && $movement->store)<div class="small text-body-secondary">{{ $movement->store->name }}</div>@endif</td>
                            <td>{{ $movement->user?->name ?? 'Sistema' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-body-secondary py-4">Nenhuma movimentação.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mb-3">{{ $movements->links() }}</div>

    @if ($canSeeCost)
        <div class="card">
            <div class="card-body pb-0"><h2 class="h6">Histórico de custo</h2></div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th scope="col">Data</th><th scope="col">Custo anterior</th><th scope="col">Novo custo</th><th scope="col">Origem</th><th scope="col">Fornecedor</th><th scope="col">Usuário</th></tr></thead>
                    <tbody>
                        @forelse ($costs as $cost)
                            <tr wire:key="cost-{{ $cost->id }}">
                                <td class="text-nowrap">{{ $cost->created_at->format('d/m/Y H:i') }}</td>
                                <td>{{ Money::format($cost->previous_cost) }}</td>
                                <td>{{ Money::format($cost->new_cost) }}</td>
                                <td>{{ ucfirst($cost->source) }}</td>
                                <td>{{ $cost->supplier?->name ?? '-' }}</td>
                                <td>{{ $cost->user?->name ?? 'Sistema' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-body-secondary py-4">Nenhuma alteração de custo.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
