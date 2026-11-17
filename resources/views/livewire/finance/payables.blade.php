@php use App\Support\Money; @endphp
<div>
    <x-flash />

    <div class="row g-3 mb-3">
        <div class="col-12 col-sm-6 col-xl-3"><x-money-card label="Vencidas em aberto" :cents="$cards['overdue']" icon="exclamation-octagon" /></div>
        <div class="col-12 col-sm-6 col-xl-3"><x-money-card label="Vencem hoje" :cents="$cards['today']" icon="calendar-event" /></div>
        <div class="col-12 col-sm-6 col-xl-3"><x-money-card label="Próximos 7 dias" :cents="$cards['week']" icon="calendar-week" tone="blue" /></div>
        <div class="col-12 col-sm-6 col-xl-3"><x-money-card label="Pagas no período" :cents="$cards['paid']" icon="check2-circle" tone="blue" /></div>
    </div>

    <x-period-filter :error="$rangeError" future />

    <div class="row g-2 align-items-end mb-3 no-print">
        <div class="col-12 col-md-4">
            <label for="view" class="form-label">Mostrar</label>
            <select id="view" class="form-select" wire:model.live="view">
                @foreach ($views as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-3">
            <label for="categoryId" class="form-label">Categoria</label>
            <select id="categoryId" class="form-select" wire:model.live="categoryId">
                <option value="">Todas</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        @can('financeiro.pagar')
            <div class="col-6 col-md-2">
                <label for="paymentDate" class="form-label">Pagar com data de</label>
                <input id="paymentDate" type="date" class="form-control @error('paymentDate') is-invalid @enderror" wire:model="paymentDate">
                @error('paymentDate') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-6 col-md-3 d-flex gap-2 justify-content-end">
                <a href="{{ route('finance.categories') }}" class="btn btn-light">Categorias</a>
                <a href="{{ route('finance.payables.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> Nova conta</a>
            </div>
        @endcan
    </div>

    <p class="small text-body-secondary" id="payables-count">{{ $payables->total() }} {{ $payables->total() === 1 ? 'conta' : 'contas' }}, total {{ Money::format($listTotal / 100) }}</p>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle">
                <caption class="visually-hidden">Contas a pagar</caption>
                <thead>
                    <tr>
                        <th scope="col">Vencimento</th>
                        <th scope="col">Descrição</th>
                        <th scope="col">Categoria</th>
                        <th scope="col" class="text-end">Valor</th>
                        <th scope="col">Situação</th>
                        @can('financeiro.pagar') <th scope="col" class="text-end no-print">Ações</th> @endcan
                    </tr>
                </thead>
                <tbody>
                    @forelse ($payables as $payable)
                        <tr wire:key="payable-{{ $payable->id }}">
                            <td class="text-nowrap">{{ $payable->due_date->format('d/m/Y') }}</td>
                            <td>
                                {{ $payable->description }} @if ($payable->installmentLabel())<span class="text-body-secondary">({{ $payable->installmentLabel() }})</span>@endif
                                <div class="small text-body-secondary">{{ collect([$payable->supplier?->name, $payable->document ? 'Doc. '.$payable->document : null])->filter()->implode(' - ') }}</div>
                            </td>
                            <td>{{ $payable->category->name }}</td>
                            <td class="text-end text-nowrap">{{ Money::format($payable->amount) }}</td>
                            <td>
                                <span class="badge text-bg-{{ $payable->statusTone() }}">{{ $payable->statusLabel() }}</span>
                                @if ($payable->paid_at)<div class="small text-body-secondary">em {{ $payable->paid_at->format('d/m/Y') }}</div>@endif
                                @if ($payable->cancel_reason)<div class="small text-body-secondary">{{ $payable->cancel_reason }}</div>@endif
                            </td>
                            @can('financeiro.pagar')
                                <td class="text-end no-print">
                                    <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                                        @if ($payable->isOpen())
                                            <button type="button" class="btn btn-sm btn-primary" wire:click="pay({{ $payable->id }})"
                                                    wire:confirm="Registrar o pagamento de {{ Money::format($payable->amount) }} na data escolhida?">Pagar</button>
                                            <a href="{{ route('finance.payables.edit', $payable->id) }}" class="btn btn-sm btn-outline-primary">Editar</a>
                                            <button type="button" class="btn btn-sm btn-light text-danger" wire:click="startCancel({{ $payable->id }})">Cancelar</button>
                                        @elseif ($payable->status === \App\Models\Payable::PAID && $payable->source_type === null)
                                            <button type="button" class="btn btn-sm btn-light" wire:click="reopen({{ $payable->id }})" wire:confirm="Desfazer o pagamento desta conta?">Desfazer pagamento</button>
                                        @endif
                                    </div>
                                </td>
                            @endcan
                        </tr>
                        @if ($cancelingId === $payable->id)
                            <tr wire:key="cancel-{{ $payable->id }}">
                                <td colspan="6">
                                    <form wire:submit="confirmCancel" class="d-flex flex-wrap gap-2 align-items-start" novalidate>
                                        <div class="flex-grow-1">
                                            <label for="cancelReason" class="visually-hidden">Motivo do cancelamento</label>
                                            <input id="cancelReason" type="text" maxlength="200" placeholder="Motivo do cancelamento" class="form-control @error('cancelReason') is-invalid @enderror" wire:model="cancelReason">
                                            @error('cancelReason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        </div>
                                        <button type="submit" class="btn btn-danger">Cancelar conta</button>
                                        <button type="button" class="btn btn-light" wire:click="$set('cancelingId', null)">Voltar</button>
                                    </form>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr><td colspan="6" class="text-center text-body-secondary py-4">Nenhuma conta encontrada.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $payables->links() }}</div>
</div>
