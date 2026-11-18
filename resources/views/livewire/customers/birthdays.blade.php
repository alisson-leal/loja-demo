<div>
    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3">
            <label for="month" class="form-label">Mês</label>
            <select id="month" class="form-select" wire:model.live.number="month">
                @foreach ($months as $number => $label)
                    <option value="{{ $number }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        @if ($withChildren)
            <div class="col-6 col-md-3">
                <label for="who" class="form-label">Quem</label>
                <select id="who" class="form-select" wire:model.live="who">
                    <option value="todos">Clientes e filhos</option>
                    <option value="clientes">Só clientes</option>
                    <option value="filhos">Só filhos</option>
                </select>
            </div>
        @endif
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle">
                <caption class="visually-hidden">Aniversariantes do mês</caption>
                <thead>
                    <tr><th scope="col">Dia</th><th scope="col">Nome</th><th scope="col">Tipo</th><th scope="col">Completa</th><th scope="col">Responsável</th><th scope="col">Contato</th></tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr wire:key="{{ $row['key'] }}">
                            <td class="fw-bold">{{ $row['date']->format('d') }}</td>
                            <td>{{ $row['name'] }}</td>
                            <td>{{ $row['kind'] }}</td>
                            <td class="text-nowrap">{{ $year - $row['date']->year }} anos</td>
                            <td><a href="{{ route('customers.show', $row['customer']->id) }}">{{ $row['customer']->name }}</a></td>
                            <td>{{ $row['customer']->whatsapp ?: $row['customer']->phone ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-body-secondary py-4">Nenhum aniversariante neste mês.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
