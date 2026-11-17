@php use App\Support\Money; @endphp
<div>
    <x-flash />

    <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
        <a href="{{ route('customers.index') }}" class="align-self-center"><i class="bi bi-arrow-left" aria-hidden="true"></i> Voltar aos clientes</a>
        @can('clientes.editar')
            <a href="{{ route('customers.edit', $customer->id) }}" class="btn btn-outline-primary">Editar</a>
        @endcan
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-sm-6 col-xl-3"><x-money-card label="Total comprado" :cents="\App\Support\Money::toCents($customer->total_spent)" icon="bag-heart" /></div>
        <div class="col-12 col-sm-6 col-xl-3"><x-stat-card label="Compras" :value="$customer->purchases_count" icon="receipt" tone="blue" /></div>
        <div class="col-12 col-sm-6 col-xl-3"><x-money-card label="Ticket médio" :cents="$ticket" icon="graph-up-arrow" /></div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card stat-card stat-card-blue h-100">
                <div class="card-body">
                    <span class="stat-icon stat-icon-blue"><i class="bi bi-calendar-check" aria-hidden="true"></i></span>
                    <div>
                        <div class="stat-value">{{ $customer->last_purchase_at?->format('d/m/Y') ?? '-' }}</div>
                        <div class="stat-label">{{ $frequency !== null ? "Compra a cada {$frequency} dias, em média" : 'Última compra' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-xl-7">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h2 class="h6 mb-0">Dados</h2>
                        <span class="badge text-bg-secondary">{{ $customer->statusLabel() }}</span>
                    </div>
                    <dl class="row mb-0 small">
                        <dt class="col-4">WhatsApp</dt><dd class="col-8">{{ $customer->whatsapp ?: '-' }}</dd>
                        <dt class="col-4">Telefone</dt><dd class="col-8">{{ $customer->phone ?: '-' }}</dd>
                        <dt class="col-4">E-mail</dt><dd class="col-8">{{ $customer->email ?: '-' }}</dd>
                        <dt class="col-4">CPF</dt><dd class="col-8">{{ $customer->cpf ?: '-' }}</dd>
                        <dt class="col-4">Nascimento</dt><dd class="col-8">{{ $customer->birth_date?->format('d/m/Y') ?? '-' }}</dd>
                        <dt class="col-4">Endereço</dt><dd class="col-8">{{ collect([$customer->address, $customer->neighborhood, $customer->city, $customer->zip_code])->filter()->implode(', ') ?: '-' }}</dd>
                        <dt class="col-4">Cadastro</dt><dd class="col-8">{{ $customer->created_at->format('d/m/Y') }}</dd>
                        @if ($customer->notes)
                            <dt class="col-4">Observações</dt><dd class="col-8">{{ $customer->notes }}</dd>
                        @endif
                        <dt class="col-4">Comunicação</dt>
                        <dd class="col-8">
                            @foreach (['whatsapp' => 'WhatsApp', 'email' => 'E-mail'] as $channel => $label)
                                @if ($customer->{$channel.'_opt_in'})
                                    <span class="badge text-bg-success me-1">{{ $label }}: autorizado</span>
                                @else
                                    <span class="badge text-bg-secondary me-1">{{ $label }}: não autorizado</span>
                                @endif
                            @endforeach
                        </dd>
                    </dl>
                    @if ($consents->isNotEmpty())
                        <details class="mt-2 small">
                            <summary>Histórico de consentimento (LGPD)</summary>
                            <ul class="list-unstyled mt-2 mb-0">
                                @foreach ($consents as $consent)
                                    <li class="py-1 border-bottom" wire:key="consent-{{ $consent->id }}">
                                        {{ $consent->created_at->format('d/m/Y H:i') }} -
                                        <strong>{{ $consent->channelLabel() }} {{ $consent->granted ? 'autorizado' : 'revogado' }}</strong>
                                        <span class="text-body-secondary">({{ $consent->sourceLabel() }}{{ $consent->user ? ', por '.$consent->user->name : '' }})</span>
                                    </li>
                                @endforeach
                            </ul>
                        </details>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-5">
            <div class="card h-100">
                <div class="card-body">
                    <h2 class="h6">Filhos</h2>
                    @forelse ($customer->children as $child)
                        <div class="d-flex justify-content-between py-2 border-bottom" wire:key="child-{{ $child->id }}">
                            <span>{{ $child->name }}<div class="small text-body-secondary">{{ $child->birth_date?->format('d/m/Y') }}</div></span>
                            <span class="text-end">{{ $child->ageLabel() }}<div class="small text-body-secondary">{{ $child->clothing_size ? 'Tam. '.$child->clothing_size : '' }}</div></span>
                        </div>
                    @empty
                        <p class="small text-body-secondary mb-0">Nenhum filho cadastrado.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-7">
            <div class="card">
                <div class="card-body pb-0"><h2 class="h6">Histórico de compras</h2></div>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead><tr><th scope="col">Venda</th><th scope="col">Data</th><th scope="col">Atendimento</th><th scope="col" class="text-end">Total</th></tr></thead>
                        <tbody>
                            @forelse ($sales as $sale)
                                <tr wire:key="sale-{{ $sale->id }}">
                                    <td><a href="{{ route('sales.show', $sale->id) }}">#{{ $sale->number() }}</a>@unless ($sale->isCompleted()) <span class="badge text-bg-danger">Cancelada</span>@endunless</td>
                                    <td class="text-nowrap">{{ $sale->created_at->format('d/m/Y H:i') }}</td>
                                    <td>{{ $sale->user->name }}</td>
                                    <td class="text-end text-nowrap">{{ Money::format($sale->total) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-4">Nenhuma compra registrada.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="mt-3">{{ $sales->links() }}</div>
        </div>
        <div class="col-12 col-xl-5">
            <div class="card">
                <div class="card-body">
                    <h2 class="h6">Produtos adquiridos</h2>
                    @forelse ($products as $product)
                        <div class="d-flex justify-content-between py-2 border-bottom" wire:key="product-{{ $loop->index }}">
                            <span>{{ $product->description }}</span><strong class="ms-2">{{ (int) $product->quantity }}</strong>
                        </div>
                    @empty
                        <p class="small text-body-secondary mb-0">Nenhum produto adquirido.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
