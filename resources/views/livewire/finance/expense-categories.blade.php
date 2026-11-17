<div>
    <x-flash />

    <div class="mb-3"><a href="{{ route('finance.payables') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Voltar às contas a pagar</a></div>

    <div class="card mb-3">
        <div class="card-body">
            <h2 class="h6 mb-3">{{ $editingId ? 'Editar categoria' : 'Nova categoria' }}</h2>
            <form wire:submit="save" class="row g-3 align-items-start" novalidate>
                <div class="col-12 col-md-6"><x-text-field name="name" label="Nome" required maxlength="60" /></div>
                <div class="col-6 col-md-2 form-check-aligned">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="isActive" wire:model="isActive">
                        <label class="form-check-label" for="isActive">Ativa</label>
                    </div>
                </div>
                <div class="col-12 col-md-4 d-flex gap-2 form-actions-aligned">
                    <button type="submit" class="btn btn-primary">Salvar</button>
                    @if ($editingId)
                        <button type="button" class="btn btn-light" wire:click="cancel">Cancelar</button>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle">
                <caption class="visually-hidden">Categorias de despesa</caption>
                <thead><tr><th scope="col">Nome</th><th scope="col">Contas</th><th scope="col">Situação</th><th scope="col" class="text-end">Ações</th></tr></thead>
                <tbody>
                    @foreach ($categories as $category)
                        <tr wire:key="category-{{ $category->id }}">
                            <td>
                                {{ $category->name }}
                                @if ($category->system_key === 'mercadorias')
                                    <div class="small text-body-secondary">Sai do caixa, mas não entra como despesa no resultado: o custo das peças já aparece no custo das vendas.</div>
                                @elseif ($category->system_key === 'devolucoes')
                                    <div class="small text-body-secondary">Lançada automaticamente nos reembolsos de devolução.</div>
                                @endif
                            </td>
                            <td>{{ $category->payables_count }}</td>
                            <td><span @class(['badge', 'text-bg-success' => $category->is_active, 'text-bg-secondary' => ! $category->is_active])>{{ $category->is_active ? 'Ativa' : 'Inativa' }}</span></td>
                            <td class="text-end"><button type="button" class="btn btn-sm btn-outline-primary" wire:click="edit({{ $category->id }})">Editar</button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
