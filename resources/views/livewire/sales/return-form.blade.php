@php use App\Support\Money; @endphp
<div class="row justify-content-center">
    <div class="col-12 col-xl-9">
        <x-flash />

        <div class="mb-3"><a href="{{ route('sales.show', $sale->id) }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Voltar à venda #{{ $sale->number() }}</a></div>

        <form wire:submit="save" novalidate>
            <div class="card mb-3">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <caption class="visually-hidden">Itens da venda</caption>
                        <thead><tr><th scope="col">Item</th><th scope="col">Pago</th><th scope="col">Disponível</th><th scope="col">Devolver</th><th scope="col">Volta ao estoque</th></tr></thead>
                        <tbody>
                            @foreach ($sale->items as $item)
                                <tr wire:key="item-{{ $item->id }}">
                                    <td>
                                        {{ $item->description }}<div class="small text-body-secondary">{{ $item->sku }}</div>
                                        @if ($item->warranty_until)
                                            <div class="small {{ $item->underWarranty() ? 'text-success' : 'text-body-secondary' }}">Garantia até {{ $item->warranty_until->format('d/m/Y') }}{{ $item->underWarranty() ? '' : ' (vencida)' }}</div>
                                        @endif
                                    </td>
                                    <td class="text-nowrap">{{ Money::format($item->total) }}</td>
                                    <td class="text-nowrap">{{ $item->formatQuantity($item->returnableQuantity()) }}</td>
                                    <td>
                                        @if ($item->serials)
                                            <fieldset>
                                                <legend class="visually-hidden">Números de série devolvidos de {{ $item->description }}</legend>
                                                @forelse ($outstanding[$item->id] ?? [] as $serial)
                                                    <div class="form-check" wire:key="serial-{{ $item->id }}-{{ $serial }}">
                                                        <input class="form-check-input" type="checkbox" id="rs-{{ $item->id }}-{{ $loop->index }}" value="{{ $serial }}" wire:model.live="returnSerials.{{ $item->id }}">
                                                        <label class="form-check-label small text-break" for="rs-{{ $item->id }}-{{ $loop->index }}">{{ $serial }}</label>
                                                    </div>
                                                @empty
                                                    <span class="small text-body-secondary">Tudo devolvido</span>
                                                @endforelse
                                            </fieldset>
                                        @elseif ($item->isFractional())
                                            <div class="input-group input-group-sm grid-medium has-validation">
                                                <input type="text" inputmode="decimal" autocomplete="off" aria-label="Quantidade a devolver de {{ $item->description }}, em {{ $item->unit === 'm' ? 'metros' : 'quilos' }}" wire:model="quantities.{{ $item->id }}" class="form-control text-end @error('quantities.'.$item->id) is-invalid @enderror">
                                                <span class="input-group-text">{{ $item->unit }}</span>
                                                @error('quantities.'.$item->id) <div class="invalid-feedback">{{ $message }}</div> @enderror
                                            </div>
                                        @else
                                            <input type="number" min="0" max="{{ $item->returnableQuantity() }}" step="1" aria-label="Quantidade a devolver de {{ $item->description }}" wire:model="quantities.{{ $item->id }}" class="form-control form-control-sm grid-small @error('quantities.'.$item->id) is-invalid @enderror">
                                        @endif
                                    </td>
                                    <td><input type="checkbox" class="form-check-input" aria-label="{{ $item->description }} volta ao estoque" wire:model="restock.{{ $item->id }}"></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-6"><x-text-field name="reason" label="Motivo" required maxlength="255" /></div>
                        <div class="col-12 col-md-6">
                            <label for="settlement" class="form-label">Como devolver o valor</label>
                            <select id="settlement" wire:model="settlement" class="form-select">
                                <option value="credit">Vale-troca (para troca por outro produto)</option>
                                @foreach ($methods as $method)
                                    <option value="{{ $method->id }}">Reembolso em {{ $method->name }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">Reembolso em dinheiro sai do seu caixa aberto.</div>
                        </div>
                    </div>
                </div>
            </div>

            @if ($needsSupervisor)
                <div class="card mb-3"><div class="card-body"><x-supervisor-fields /></div></div>
            @endif

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Registrar devolução</button>
                <a href="{{ route('sales.show', $sale->id) }}" class="btn btn-light">Cancelar</a>
            </div>
        </form>
    </div>
</div>
