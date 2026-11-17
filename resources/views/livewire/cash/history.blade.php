@php use App\Support\Money; @endphp
<div>
    <x-flash />

    @if ($seesAll)
        <div class="row mb-3">
            <div class="col-12 col-md-4">
                <label for="filter-user" class="form-label">Operador</label>
                <select id="filter-user" class="form-select" wire:model.live="userId">
                    <option value="">Todos</option>
                    @foreach ($users as $option)
                        <option value="{{ $option->id }}">{{ $option->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle">
                <caption class="visually-hidden">Histórico de caixas</caption>
                <thead>
                    <tr>
                        <th scope="col">Caixa</th>
                        <th scope="col">Operador</th>
                        <th scope="col">Abertura</th>
                        <th scope="col">Fechamento</th>
                        <th scope="col">Esperado</th>
                        <th scope="col">Contado</th>
                        <th scope="col">Diferença</th>
                        <th scope="col" class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sessions as $session)
                        <tr wire:key="session-{{ $session->id }}">
                            <td>#{{ $session->id }}</td>
                            <td>{{ $session->user->name }}</td>
                            <td class="text-nowrap">{{ $session->opened_at->format('d/m/Y H:i') }}</td>
                            <td class="text-nowrap">
                                @if ($session->isOpen())
                                    <span class="badge text-bg-success">Aberto</span>
                                @else
                                    {{ $session->closed_at->format('d/m/Y H:i') }}
                                @endif
                            </td>
                            <td class="text-nowrap">{{ $session->isOpen() ? '-' : Money::format($session->expected_amount) }}</td>
                            <td class="text-nowrap">{{ $session->isOpen() ? '-' : Money::format($session->counted_amount) }}</td>
                            <td @class(['text-nowrap', 'fw-bold', 'text-danger' => $session->difference < 0, 'text-success' => $session->difference > 0])>{{ $session->isOpen() ? '-' : Money::format($session->difference) }}</td>
                            <td class="text-end"><a href="{{ route('cash.report', $session->id) }}" class="btn btn-sm btn-outline-primary">Ver</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-body-secondary py-4">Nenhum caixa registrado.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $sessions->links() }}</div>
</div>
