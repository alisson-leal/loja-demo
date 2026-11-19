<div>
    <x-flash />

    @if ($incoming > 0)
        <div class="alert alert-info">{{ $incoming }} transferência(s) a caminho desta loja. Confira as peças e confirme o recebimento.</div>
    @endif

    <div class="row g-2 mb-3 align-items-end">
        <div class="col-8 col-md-4">
            <label for="view" class="form-label">Mostrar</label>
            <select id="view" class="form-select" wire:model.live="view">
                <option value="a_receber">A receber nesta loja</option>
                <option value="enviadas">Enviadas por esta loja</option>
                <option value="recebidas">Recebidas nesta loja</option>
                <option value="todas">Todas das minhas lojas</option>
            </select>
        </div>
        @can('estoque.transferir')
            <div class="col-4 col-md-8 text-end">
                <a href="{{ route('stock.transfers.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> Nova</a>
            </div>
        @endcan
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle">
                <caption class="visually-hidden">Transferências</caption>
                <thead><tr><th scope="col">Nº</th><th scope="col">De → Para</th><th scope="col">Peças</th><th scope="col">Enviada</th><th scope="col">Situação</th><th scope="col" class="text-end">Ações</th></tr></thead>
                <tbody>
                    @forelse ($transfers as $transfer)
                        <tr wire:key="transfer-{{ $transfer->id }}">
                            <td>#{{ $transfer->number() }}</td>
                            <td>{{ $transfer->from->name }} <i class="bi bi-arrow-right" aria-hidden="true"></i> {{ $transfer->to->name }}</td>
                            {{-- Por metro ou quilo, cada item conta como uma peça. --}}
                            <td>{{ $transfer->items->sum(fn ($item) => $item->variant->product->isFractional() ? 1 : $item->quantity) }}</td>
                            <td class="text-nowrap">{{ $transfer->created_at->format('d/m/Y H:i') }}<div class="small text-body-secondary">{{ $transfer->user->name }}</div></td>
                            <td>
                                <span class="badge text-bg-{{ $transfer->statusTone() }}">{{ $transfer->statusLabel() }}</span>
                                @if ($transfer->received_at)<div class="small text-body-secondary">{{ $transfer->received_at->format('d/m H:i') }} · {{ $transfer->receiver?->name }}</div>@endif
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                                    <button type="button" class="btn btn-sm btn-light" wire:click="toggle({{ $transfer->id }})">{{ $openId === $transfer->id ? 'Fechar' : 'Ver peças' }}</button>
                                    @if ($transfer->status === 'in_transit')
                                        @can('estoque.transferir')
                                            @if (in_array($transfer->to_store_id, $mine, true))
                                                <button type="button" class="btn btn-sm btn-primary" wire:click="receive({{ $transfer->id }})" wire:confirm="Confirmar que as peças chegaram em {{ $transfer->to->name }}?">Receber</button>
                                            @endif
                                            @if (in_array($transfer->from_store_id, $mine, true))
                                                <button type="button" class="btn btn-sm btn-light text-danger" wire:click="cancel({{ $transfer->id }})" wire:confirm="Cancelar? As peças voltam para {{ $transfer->from->name }}.">Cancelar</button>
                                            @endif
                                        @endcan
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @if ($openId === $transfer->id)
                            <tr wire:key="transfer-items-{{ $transfer->id }}">
                                <td colspan="6">
                                    <ul class="mb-0 small">
                                        @foreach ($transfer->items as $item)
                                            <li>{{ $item->variant->product->isFractional() ? $item->variant->formatQuantity($item->quantity) : $item->quantity.'x' }} {{ $item->variant->fullName() }} <span class="text-body-secondary">({{ $item->variant->sku }})</span></li>
                                        @endforeach
                                    </ul>
                                    @if ($transfer->notes)<div class="small text-body-secondary mt-1">{{ $transfer->notes }}</div>@endif
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr><td colspan="6" class="text-center text-body-secondary py-4">Nenhuma transferência nesta lista.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $transfers->links() }}</div>
</div>
