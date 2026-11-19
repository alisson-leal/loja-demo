@php use App\Support\Money; @endphp
<div>
    <x-flash />

    <form wire:submit="save" novalidate>
        <div class="card mb-3">
            <div class="card-body">
                <h2 class="h6 mb-3">Dados do produto</h2>
                <div class="row g-3">
                    <div class="col-12 col-lg-6"><x-text-field name="name" label="Nome do produto" required maxlength="150" /></div>
                    <div class="col-6 col-lg-3"><x-text-field name="internalCode" label="Código interno" maxlength="30" hint="Vazio: gerado automaticamente." /></div>
                    <div class="col-6 col-lg-3"><x-text-field name="sku" label="SKU base" maxlength="64" hint="Vazio: usa o código interno." /></div>

                    <div class="col-12 col-md-4">
                        <label for="categoryId" class="form-label">Categoria</label>
                        <select id="categoryId" required wire:model="categoryId" class="form-select @error('categoryId') is-invalid @enderror">
                            <option value="">Selecione</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->parent_id ? '— ' : '' }}{{ $category->name }}</option>
                            @endforeach
                        </select>
                        @error('categoryId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        @if ($categories->isEmpty())
                            <div class="form-text"><a href="{{ route('categories.index') }}">Cadastre uma categoria primeiro.</a></div>
                        @endif
                    </div>
                    <div class="col-6 col-md-4">
                        <label for="brandId" class="form-label">Marca</label>
                        <select id="brandId" wire:model="brandId" class="form-select @error('brandId') is-invalid @enderror">
                            <option value="">Sem marca</option>
                            @foreach ($brands as $brand)
                                <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                            @endforeach
                        </select>
                        @error('brandId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-6 col-md-4">
                        <label for="supplierId" class="form-label">Fornecedor</label>
                        <select id="supplierId" wire:model="supplierId" class="form-select @error('supplierId') is-invalid @enderror">
                            <option value="">Sem fornecedor</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                            @endforeach
                        </select>
                        @error('supplierId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-12">
                        <label for="description" class="form-label">Descrição</label>
                        <textarea id="description" rows="2" maxlength="5000" wire:model="description" class="form-control @error('description') is-invalid @enderror"></textarea>
                        @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    @if ($canSeeCost)
                        <div class="col-6 col-md-3">
                            <label for="costPrice" class="form-label">Custo base (R$)</label>
                            <input id="costPrice" type="number" step="0.01" min="0" wire:model.live.blur="costPrice" class="form-control @error('costPrice') is-invalid @enderror">
                            @error('costPrice') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    @endif
                    <div class="col-6 col-md-3">
                        <label for="salePrice" class="form-label">Preço base (R$)</label>
                        <input id="salePrice" type="number" step="0.01" min="0" wire:model.live.blur="salePrice" class="form-control @error('salePrice') is-invalid @enderror">
                        @error('salePrice') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    @if ($canSeeCost)
                        <div class="col-6 col-md-3">
                            <span class="form-label d-block">Margem</span>
                            <div class="form-control-plaintext fw-bold" id="base-margin">{{ Money::formatMargin(is_numeric($costPrice) ? $costPrice : 0, is_numeric($salePrice) ? $salePrice : 0) }}</div>
                        </div>
                    @endif
                    <div class="col-6 col-md-3 form-check-aligned">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="isActive" wire:model="isActive">
                            <label class="form-check-label" for="isActive">Produto ativo</label>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <label for="warrantyMonths" class="form-label">Garantia (meses)</label>
                        <input id="warrantyMonths" type="number" step="1" min="0" max="120" placeholder="Sem garantia" wire:model="warrantyMonths" class="form-control @error('warrantyMonths') is-invalid @enderror" aria-describedby="warrantyHelp">
                        @error('warrantyMonths') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div id="warrantyHelp" class="form-text">Sai no comprovante, contada da data da venda.</div>
                    </div>
                    <div class="col-12 col-md-9 form-check-aligned">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="tracksSerial" wire:model.live="tracksSerial" aria-describedby="serialHelp">
                            <label class="form-check-label" for="tracksSerial">Controlar número de série</label>
                        </div>
                        <div id="serialHelp" class="form-text">Cada unidade tem o seu número, informado na entrada e na venda. Para eletrônicos, celulares e ferramentas.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <h2 class="h6">Imagens</h2>
                <p class="small text-body-secondary">Até {{ $maxImages }} imagens JPG, PNG ou WEBP, com no máximo 5 MB cada. A primeira é a principal.</p>

                <div class="d-flex flex-wrap gap-2 mb-3">
                    @foreach ($images as $image)
                        @php($isPrimary = $primaryImageId ? $image->id === $primaryImageId : $loop->first)
                        <div @class(['image-tile', 'image-tile-primary' => $isPrimary]) wire:key="image-{{ $image->id }}">
                            <img src="{{ $image->url() }}" alt="Imagem {{ $loop->iteration }} do produto">
                            <div class="d-flex gap-1">
                                @unless ($isPrimary)
                                    <button type="button" class="btn btn-sm btn-light" wire:click="makePrimary({{ $image->id }})" title="Definir como principal" aria-label="Definir imagem {{ $loop->iteration }} como principal"><i class="bi bi-star" aria-hidden="true"></i></button>
                                @endunless
                                <button type="button" class="btn btn-sm btn-light text-danger" wire:click="removeImage({{ $image->id }})" aria-label="Remover imagem {{ $loop->iteration }}"><i class="bi bi-trash" aria-hidden="true"></i></button>
                            </div>
                        </div>
                    @endforeach
                    @foreach ($uploads as $index => $upload)
                        <div class="image-tile" wire:key="upload-{{ $index }}">
                            <img src="{{ $upload->temporaryUrl() }}" alt="Nova imagem {{ $index + 1 }}">
                            <div class="d-flex gap-1">
                                <span class="small text-body-secondary">Nova</span>
                                <button type="button" class="btn btn-sm btn-light text-danger" wire:click="removeUpload({{ $index }})" aria-label="Remover nova imagem {{ $index + 1 }}"><i class="bi bi-trash" aria-hidden="true"></i></button>
                            </div>
                        </div>
                    @endforeach
                </div>

                <label for="uploads" class="form-label">Adicionar imagens</label>
                <input id="uploads" type="file" multiple accept="image/jpeg,image/png,image/webp" wire:model="uploads" class="form-control @error('uploads.*') is-invalid @enderror">
                @error('uploads.*') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="small text-body-secondary mt-1" wire:loading wire:target="uploads">Enviando imagens...</div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <h2 class="h6">Variações</h2>
                <p class="small text-body-secondary">
                    Para produto sem variações, deixe tudo desmarcado. Faltou algum valor?
                    @can('cadastros.gerir') <a href="{{ route('attributes.index') }}" target="_blank" rel="noopener">Cadastre em Variações</a> e recarregue a página. @else Peça o cadastro a um gerente. @endcan
                </p>

                <div class="row g-3 mb-3">
                    @foreach ($variationAttributes as $attribute)
                        @if ($attribute->values->isNotEmpty())
                            <div class="col-12 col-md-6" wire:key="attr-{{ $attribute->id }}">
                                <fieldset class="permission-group">
                                    <legend>{{ $attribute->name }}</legend>
                                    @foreach ($attribute->values as $value)
                                        <div class="form-check form-check-inline" wire:key="val-{{ $value->id }}">
                                            <input class="form-check-input" type="checkbox" id="val-{{ $value->id }}" value="{{ $value->id }}" wire:model="selectedValues.{{ $attribute->id }}">
                                            <label class="form-check-label" for="val-{{ $value->id }}">{{ $value->value }}</label>
                                        </div>
                                    @endforeach
                                </fieldset>
                            </div>
                        @endif
                    @endforeach
                </div>
                @error('selectedValues') <div class="alert alert-danger">{{ $message }}</div> @enderror

                <div class="d-flex flex-wrap gap-2 mb-3">
                    <button type="button" class="btn btn-outline-primary" wire:click="generate" wire:loading.attr="disabled">Gerar combinações</button>
                    <button type="button" class="btn btn-light" wire:click="applyBasePrices">Aplicar preço base a todas</button>
                    @if ($canEnterStock && $hasNewRows && ! $tracksSerial)
                        <div class="input-group w-auto">
                            <label for="initialForAll" class="visually-hidden">Estoque inicial para todas as variações novas</label>
                            <input id="initialForAll" type="number" min="0" step="1" class="form-control grid-medium" placeholder="Qtd." wire:model="initialForAll">
                            <button type="button" class="btn btn-light" wire:click="applyInitialStock">Estoque inicial em todas</button>
                        </div>
                    @endif
                </div>
                @error('rows') <div class="alert alert-danger">{{ $message }}</div> @enderror

                <div class="table-responsive">
                    <table class="table align-middle mb-0 variant-grid">
                        <caption class="visually-hidden">Variações do produto</caption>
                        <thead>
                            <tr>
                                <th scope="col">Variação</th>
                                <th scope="col">SKU</th>
                                <th scope="col">Código de barras</th>
                                @if ($canSeeCost) <th scope="col">Custo</th> @endif
                                <th scope="col">Preço</th>
                                @if ($canSeeCost) <th scope="col">Margem</th> @endif
                                <th scope="col">Mínimo</th>
                                <th scope="col">{{ $canEnterStock && $hasNewRows && ! $tracksSerial ? 'Saldo / estoque inicial' : 'Saldo' }}</th>
                                <th scope="col">Ativa</th>
                                <th scope="col"><span class="visually-hidden">Remover</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $i => $row)
                                <tr wire:key="row-{{ $row['id'] ?? 'new-'.$row['key'] }}">
                                    <td class="text-nowrap fw-bold">{{ $row['label'] }}</td>
                                    <td>
                                        <input type="text" maxlength="64" placeholder="Automático" aria-label="SKU de {{ $row['label'] }}" wire:model="rows.{{ $i }}.sku" class="form-control form-control-sm @error('rows.'.$i.'.sku') is-invalid @enderror">
                                        @error('rows.'.$i.'.sku') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </td>
                                    <td>
                                        <input type="text" maxlength="64" aria-label="Código de barras de {{ $row['label'] }}" wire:model="rows.{{ $i }}.barcode" class="form-control form-control-sm @error('rows.'.$i.'.barcode') is-invalid @enderror">
                                        @error('rows.'.$i.'.barcode') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </td>
                                    @if ($canSeeCost)
                                        <td>
                                            <input type="number" step="0.01" min="0" aria-label="Custo de {{ $row['label'] }}" wire:model.live.blur="rows.{{ $i }}.cost_price" class="form-control form-control-sm @error('rows.'.$i.'.cost_price') is-invalid @enderror">
                                            @error('rows.'.$i.'.cost_price') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        </td>
                                    @endif
                                    <td>
                                        <input type="number" step="0.01" min="0" aria-label="Preço de {{ $row['label'] }}" wire:model.live.blur="rows.{{ $i }}.sale_price" class="form-control form-control-sm @error('rows.'.$i.'.sale_price') is-invalid @enderror">
                                        @error('rows.'.$i.'.sale_price') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </td>
                                    @if ($canSeeCost)
                                        <td class="text-nowrap">{{ Money::formatMargin(is_numeric($row['cost_price']) ? $row['cost_price'] : 0, is_numeric($row['sale_price']) ? $row['sale_price'] : 0) }}</td>
                                    @endif
                                    <td>
                                        <input type="number" step="1" min="0" aria-label="Estoque mínimo de {{ $row['label'] }}" wire:model="rows.{{ $i }}.min_stock" class="form-control form-control-sm grid-small @error('rows.'.$i.'.min_stock') is-invalid @enderror">
                                        @error('rows.'.$i.'.min_stock') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </td>
                                    <td>
                                        @if (! $row['id'] && $canEnterStock && ! $tracksSerial)
                                            <input type="number" step="1" min="0" placeholder="0" aria-label="Estoque inicial de {{ $row['label'] }}" wire:model="rows.{{ $i }}.initial_stock" class="form-control form-control-sm grid-small @error('rows.'.$i.'.initial_stock') is-invalid @enderror">
                                            @error('rows.'.$i.'.initial_stock') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        @else
                                            {{ $row['on_hand'] }}
                                        @endif
                                    </td>
                                    <td><input class="form-check-input" type="checkbox" aria-label="{{ $row['label'] }} ativa" wire:model="rows.{{ $i }}.is_active"></td>
                                    <td>
                                        @if ($row['can_remove'])
                                            <button type="button" class="btn btn-sm btn-light text-danger" wire:click="removeRow({{ $i }})" aria-label="Remover {{ $row['label'] }}"><i class="bi bi-trash" aria-hidden="true"></i></button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="small text-body-secondary mt-2 mb-0">O saldo é alterado somente pela entrada de mercadorias e pelas movimentações de estoque. O estoque inicial das variações novas vira uma entrada de mercadorias ao salvar.</p>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Salvar</button>
            <a href="{{ route('products.index') }}" class="btn btn-light">Cancelar</a>
        </div>
    </form>
</div>
