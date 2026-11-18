@php use App\Support\Money; @endphp
<div>
    <x-flash />

    <div class="row g-3">
        <div class="col-12 col-xl-7">
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="h6">Peças</h2>
                    <livewire:stock.variant-picker />
                </div>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <caption class="visually-hidden">Peças da condicional</caption>
                        <thead><tr><th scope="col">Produto</th><th scope="col" class="text-end">Preço</th><th scope="col">Qtd.</th><th scope="col">Disponível</th><th scope="col"><span class="visually-hidden">Remover</span></th></tr></thead>
                        <tbody>
                            @forelse ($items as $id => $quantity)
                                @php $variant = $variants[$id] ?? null; @endphp
                                @if ($variant)
                                    <tr wire:key="item-{{ $id }}">
                                        <td>{{ $variant->fullName() }}</td>
                                        <td class="text-end text-nowrap">{{ Money::format($variant->sale_price) }}</td>
                                        <td>
                                            <label for="qty-{{ $id }}" class="visually-hidden">Quantidade de {{ $variant->fullName() }}</label>
                                            <input id="qty-{{ $id }}" type="number" min="1" class="form-control form-control-sm grid-small" wire:model.live.debounce.400ms="items.{{ $id }}">
                                        </td>
                                        <td @class(['text-danger' => (int) $quantity > $variant->available()])>{{ $variant->available() }}</td>
                                        <td class="text-end"><button type="button" class="btn btn-sm btn-light text-danger" wire:click="remove({{ $id }})" aria-label="Remover {{ $variant->fullName() }}"><i class="bi bi-trash" aria-hidden="true"></i></button></td>
                                    </tr>
                                @endif
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-4">Busque as peças que o cliente vai levar para provar.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-5">
            <form wire:submit="save" class="card" novalidate>
                <div class="card-body">
                    <h2 class="h6">Cliente</h2>
                    @if ($customer)
                        <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                            <div><strong>{{ $customer->name }}</strong><div class="small text-body-secondary">{{ $customer->whatsapp ?: $customer->phone }}</div></div>
                            <button type="button" class="btn btn-sm btn-light" wire:click="$set('customerId', null)">Trocar</button>
                        </div>
                    @else
                        <label for="customerTerm" class="visually-hidden">Buscar cliente</label>
                        <input id="customerTerm" type="search" autocomplete="off" class="form-control @error('customerTerm') is-invalid @enderror" placeholder="Nome, telefone ou CPF" wire:model.live.debounce.300ms="customerTerm">
                        @error('customerTerm') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        @if ($customerResults->isNotEmpty())
                            <div class="list-group mt-2 mb-3">
                                @foreach ($customerResults as $result)
                                    <button type="button" class="list-group-item list-group-item-action" wire:key="customer-{{ $result->id }}" wire:click="selectCustomer({{ $result->id }})">{{ $result->name }}</button>
                                @endforeach
                            </div>
                        @else
                            <p class="small text-body-secondary mt-2">A condicional precisa de um cliente cadastrado.</p>
                        @endif
                    @endif

                    <label for="dueDate" class="form-label">Devolver até</label>
                    <input id="dueDate" type="date" class="form-control mb-3 @error('dueDate') is-invalid @enderror" wire:model="dueDate">
                    @error('dueDate') <div class="invalid-feedback">{{ $message }}</div> @enderror

                    <label for="notes" class="form-label">Observação</label>
                    <textarea id="notes" rows="2" maxlength="500" class="form-control mb-3" wire:model="notes"></textarea>

                    <p class="mb-3"><strong>{{ array_sum(array_map('intval', $items)) }}</strong> peça(s)</p>
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Abrir condicional</button>
                </div>
            </form>
        </div>
    </div>
</div>
