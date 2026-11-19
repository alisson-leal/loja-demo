<div>
    <x-flash />

    <div class="mb-3"><a href="{{ route('products.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Voltar aos produtos</a></div>

    <div class="row g-3">
        <div class="col-12 col-xl-6">
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="h6">1. Prepare a planilha</h2>
                    <p class="small">Uma linha por variação ({{ mb_strtolower(\App\Support\StoreSegment::primaryVariation()) }} e cor). Linhas com o mesmo nome viram um só produto com várias variações. A coluna <code>variacao</code> vira a variação "{{ \App\Support\StoreSegment::primaryVariation() }}".</p>
                    <a href="{{ route('products.import.template') }}" class="btn btn-outline-primary btn-sm mb-3"><i class="bi bi-download" aria-hidden="true"></i> Baixar planilha modelo</a>
                    <ul class="small mb-0">
                        @foreach ($columns as $column => $description)
                            <li><code>{{ $column }}</code>: {{ $description }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-6">
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="h6">2. Envie o arquivo</h2>
                    <p class="small text-body-secondary">No Excel: Arquivo &gt; Salvar como &gt; CSV (separado por ponto e vírgula).</p>
                    <form wire:submit="analyze" novalidate>
                        <label for="file" class="visually-hidden">Arquivo CSV</label>
                        <input id="file" type="file" accept=".csv,text/csv" class="form-control mb-2 @error('file') is-invalid @enderror" wire:model="file">
                        @error('file') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="file,analyze">Conferir planilha</button>
                    </form>
                </div>
            </div>

            @if ($token)
                <div class="card">
                    <div class="card-body">
                        <h2 class="h6">3. Confira e importe</h2>
                        @if ($errorsFound)
                            <div class="alert alert-danger">
                                <strong>{{ count($errorsFound) }} problema(s) encontrado(s).</strong> Corrija na planilha e envie de novo:
                                <ul class="mb-0 mt-2 small" id="import-errors">
                                    @foreach ($errorsFound as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @else
                            <ul class="mb-3" id="import-summary">
                                <li><strong>{{ $summary['products'] }}</strong> produto(s), <strong>{{ $summary['variants'] }}</strong> variação(ões)</li>
                                <li><strong>{{ $summary['stock'] }}</strong> peça(s) de estoque inicial</li>
                                @if ($summary['new_categories']) <li>Categorias novas: {{ implode(', ', $summary['new_categories']) }}</li> @endif
                                @if ($summary['new_brands']) <li>Marcas novas: {{ implode(', ', $summary['new_brands']) }}</li> @endif
                                @if ($summary['new_suppliers']) <li>Fornecedores novos: {{ implode(', ', $summary['new_suppliers']) }}</li> @endif
                            </ul>
                            @if ($canEnterStock && $summary['stock'] > 0)
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="withStock" wire:model="withStock">
                                    <label class="form-check-label" for="withStock">Lançar o estoque inicial (entrada de mercadorias)</label>
                                </div>
                            @endif
                            <button type="button" class="btn btn-primary" wire:click="import" wire:loading.attr="disabled" wire:confirm="Importar {{ $summary['products'] }} produto(s)?">Importar</button>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
