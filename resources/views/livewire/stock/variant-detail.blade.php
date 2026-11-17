@php use App\Support\Money; @endphp
<div>
    <div class="mb-3"><a href="{{ route('stock.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Voltar aos saldos</a></div>

    <div class="card mb-3">
        <div class="card-body">
            <h2 class="h5 mb-1">{{ $variant->fullName() }}</h2>
            <p class="small text-body-secondary">SKU {{ $variant->sku }}@if ($variant->barcode) · Código de barras {{ $variant->barcode }}@endif</p>
            <div class="row g-3">
                <div class="col-6 col-md-3"><div class="stat-value">{{ $variant->stock_on_hand }}</div><div class="stat-label">Saldo físico</div></div>
                <div class="col-6 col-md-3"><div class="stat-value">{{ $variant->stock_reserved }}</div><div class="stat-label">Reservado</div></div>
                <div class="col-6 col-md-3"><div class="stat-value">{{ $variant->available() }}</div><div class="stat-label">Disponível</div></div>
                <div class="col-6 col-md-3"><div class="stat-value">{{ $variant->min_stock }}</div><div class="stat-label">Estoque mínimo</div></div>
            </div>
        </div>
    </div>

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
                            <td>{{ $movement->reason }}</td>
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
