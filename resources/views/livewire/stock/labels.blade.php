@php use App\Support\Money; @endphp
<div>
    <x-flash />

    <div class="row g-3">
        <div class="col-12 col-xl-7">
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="h6">Produtos</h2>
                    <p class="small text-body-secondary">Busque pelo nome, SKU ou leitor de código de barras. Cada leitura soma uma etiqueta.</p>
                    <livewire:stock.variant-picker />
                </div>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <caption class="visually-hidden">Etiquetas a imprimir</caption>
                        <thead><tr><th scope="col">Produto</th><th scope="col">Código</th><th scope="col" class="text-end">Preço</th><th scope="col">Etiquetas</th><th scope="col"><span class="visually-hidden">Remover</span></th></tr></thead>
                        <tbody>
                            @forelse ($items as $id => $quantity)
                                @php $variant = $variants[$id] ?? null; @endphp
                                @if ($variant)
                                    <tr wire:key="label-{{ $id }}">
                                        <td>{{ $variant->fullName() }}</td>
                                        <td class="small text-nowrap">{{ \App\Support\LabelPrinter::code($variant) }}</td>
                                        <td class="text-end text-nowrap">{{ Money::format($variant->sale_price) }}</td>
                                        <td>
                                            <label for="qty-{{ $id }}" class="visually-hidden">Etiquetas de {{ $variant->fullName() }}</label>
                                            <input id="qty-{{ $id }}" type="number" min="0" max="500" class="form-control form-control-sm grid-small" wire:model.live.debounce.400ms="items.{{ $id }}">
                                        </td>
                                        <td class="text-end"><button type="button" class="btn btn-sm btn-light text-danger" wire:click="remove({{ $id }})" aria-label="Remover {{ $variant->fullName() }}"><i class="bi bi-trash" aria-hidden="true"></i></button></td>
                                    </tr>
                                @endif
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-4">Nenhum produto na lista.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-5">
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="h6">Carregar de uma entrada de mercadorias</h2>
                    <div class="input-group">
                        <label for="entryId" class="visually-hidden">Entrada</label>
                        <select id="entryId" class="form-select" wire:model="entryId">
                            <option value="">Escolha a entrada</option>
                            @foreach ($entries as $entry)
                                <option value="{{ $entry->id }}">{{ $entry->created_at->format('d/m/Y') }} - {{ $entry->document_number ?: 'Entrada #'.$entry->id }}{{ $entry->supplier ? ' - '.$entry->supplier->name : '' }}</option>
                            @endforeach
                        </select>
                        <button type="button" class="btn btn-outline-primary" wire:click="loadEntry">Carregar</button>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h2 class="h6">Impressão</h2>
                    <label for="format" class="form-label">Papel</label>
                    <select id="format" class="form-select mb-3" wire:model="format">
                        @foreach ($formats as $key => $info)
                            <option value="{{ $key }}">{{ $info['label'] }}</option>
                        @endforeach
                    </select>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="showPrice" wire:model="showPrice">
                        <label class="form-check-label" for="showPrice">Mostrar o preço na etiqueta</label>
                    </div>
                    <p class="mb-3"><strong id="labels-total">{{ $total }}</strong> {{ $total === 1 ? 'etiqueta' : 'etiquetas' }}</p>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-primary" wire:click="print"><i class="bi bi-printer" aria-hidden="true"></i> Imprimir etiquetas</button>
                        <button type="button" class="btn btn-light" wire:click="clear" wire:confirm="Limpar a lista?">Limpar</button>
                    </div>
                    <p class="small text-body-secondary mt-3 mb-0">Na janela de impressão, escolha tamanho real (100%) e sem margens.</p>
                </div>
            </div>
        </div>
    </div>
</div>
