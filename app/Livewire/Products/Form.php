<?php

namespace App\Livewire\Products;

use App\Exceptions\BusinessRuleException;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\VariationAttribute;
use App\Models\VariationValue;
use App\Services\ProductService;
use App\Services\StockEntryService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

class Form extends Component
{
    use WithFileUploads;

    private const MAX_COMBINATIONS = 200;

    private const IMAGE_RULES = ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'];

    #[Locked]
    public ?int $productId = null;

    public string $name = '';

    public string $description = '';

    public string $categoryId = '';

    public string $brandId = '';

    public string $supplierId = '';

    public string $internalCode = '';

    public string $sku = '';

    public string $costPrice = '0.00';

    public string $salePrice = '0.00';

    public bool $isActive = true;

    /** @var array<int, list<string>> */
    public array $selectedValues = [];

    /** @var list<array<string, mixed>> */
    public array $rows = [];

    /** @var list<int> */
    #[Locked]
    public array $removedVariantIds = [];

    public array $uploads = [];

    /** @var list<int> */
    #[Locked]
    public array $removedImageIds = [];

    #[Locked]
    public ?int $primaryImageId = null;

    public function mount($product = null): void
    {
        foreach (VariationAttribute::pluck('id') as $attributeId) {
            $this->selectedValues[$attributeId] = [];
        }

        if ($product === null) {
            Gate::authorize('produtos.criar');
            $this->rows = [$this->blankRow([], 'Único')];

            return;
        }

        Gate::authorize('produtos.editar');
        $this->load(Product::with('variants.values.attribute')->findOrFail($product));
    }

    private function load(Product $product): void
    {
        $canSeeCost = Auth::user()->can('produtos.ver_custo');

        $this->productId = $product->id;
        $this->name = $product->name;
        $this->description = (string) $product->description;
        $this->categoryId = (string) $product->category_id;
        $this->brandId = (string) $product->brand_id;
        $this->supplierId = (string) $product->supplier_id;
        $this->internalCode = (string) $product->internal_code;
        $this->sku = (string) $product->sku;
        $this->costPrice = $canSeeCost ? (string) $product->cost_price : '0.00';
        $this->salePrice = (string) $product->sale_price;
        $this->isActive = $product->is_active;

        foreach ($product->variants as $variant) {
            foreach ($variant->values as $value) {
                $this->selectedValues[$value->variation_attribute_id][] = (string) $value->id;
            }

            $this->rows[] = [
                'id' => $variant->id,
                'value_ids' => $variant->values->pluck('id')->all(),
                'key' => $variant->combination_key,
                'label' => $variant->label(),
                'sku' => $variant->sku,
                'barcode' => (string) $variant->barcode,
                'cost_price' => $canSeeCost ? (string) $variant->cost_price : '0.00',
                'sale_price' => (string) $variant->sale_price,
                'min_stock' => (string) $variant->min_stock,
                'is_active' => $variant->is_active,
                'on_hand' => $variant->stock_on_hand,
                'can_remove' => ! $variant->hasHistory(),
            ];
        }

        foreach ($this->selectedValues as $attributeId => $values) {
            $this->selectedValues[$attributeId] = array_values(array_unique($values));
        }
    }

    /** @param list<int> $valueIds */
    private function blankRow(array $valueIds, string $label): array
    {
        return [
            'id' => null,
            'value_ids' => $valueIds,
            'key' => implode('-', $valueIds),
            'label' => $label,
            'sku' => '',
            'barcode' => '',
            'cost_price' => $this->costPrice,
            'sale_price' => $this->salePrice,
            'min_stock' => '0',
            'is_active' => true,
            'on_hand' => 0,
            'initial_stock' => '',
            'can_remove' => true,
        ];
    }

    /** Quantidade usada pelo botão "Aplicar a todas" do estoque inicial. */
    public string $initialForAll = '';

    public function applyInitialStock(): void
    {
        $quantity = ctype_digit(trim($this->initialForAll)) ? trim($this->initialForAll) : '';

        foreach ($this->rows as $index => $row) {
            if (! $row['id']) {
                $this->rows[$index]['initial_stock'] = $quantity;
            }
        }
    }

    /** Monta a grade com todas as combinações dos valores marcados. */
    public function generate(): void
    {
        $combinations = $this->combinations();

        if (count($combinations) > self::MAX_COMBINATIONS) {
            $this->addError('selectedValues', 'Limite de '.self::MAX_COMBINATIONS.' combinações por produto. Reduza os valores marcados.');

            return;
        }

        $values = VariationValue::with('attribute')->whereIn('id', array_merge(...$combinations))->get()->keyBy('id');
        $wanted = [];

        foreach ($combinations as $combination) {
            sort($combination);
            $wanted[implode('-', $combination)] = $combination;
        }

        $this->rows = collect($this->rows)
            ->filter(function (array $row) use ($wanted) {
                if (array_key_exists($row['key'], $wanted)) {
                    return true;
                }

                if (! $row['id']) {
                    return false;
                }

                // O item "Único" ainda sem histórico dá lugar às combinações.
                if ($row['key'] === '' && $row['can_remove']) {
                    $this->removedVariantIds[] = $row['id'];

                    return false;
                }

                return true;
            })
            ->values()
            ->all();

        $existing = array_column($this->rows, 'key');

        foreach ($wanted as $key => $combination) {
            if (! in_array((string) $key, array_map('strval', $existing), true)) {
                $this->rows[] = $this->blankRow($combination, $this->labelFor($combination, $values));
            }
        }

        $this->resetValidation('selectedValues');
    }

    /** @return list<list<int>> */
    private function combinations(): array
    {
        $result = [[]];

        foreach ($this->selectedValues as $values) {
            if ($values === []) {
                continue;
            }

            $next = [];

            foreach ($result as $partial) {
                foreach ($values as $value) {
                    $next[] = [...$partial, (int) $value];

                    if (count($next) > self::MAX_COMBINATIONS) {
                        return $next;
                    }
                }
            }

            $result = $next;
        }

        return $result;
    }

    /** @param list<int> $valueIds */
    private function labelFor(array $valueIds, Collection $values): string
    {
        $label = collect($valueIds)
            ->map(fn (int $id) => $values[$id])
            ->sortBy(fn (VariationValue $value) => [$value->attribute->position, $value->attribute->id])
            ->pluck('value')
            ->implode(' / ');

        return $label !== '' ? $label : 'Único';
    }

    public function applyBasePrices(): void
    {
        foreach ($this->rows as $index => $row) {
            $this->rows[$index]['sale_price'] = $this->salePrice;
            $this->rows[$index]['cost_price'] = $this->costPrice;
        }
    }

    public function removeRow(int $index): void
    {
        $row = $this->rows[$index] ?? null;

        if (! $row || ! $row['can_remove']) {
            return;
        }

        if ($row['id']) {
            $this->removedVariantIds[] = $row['id'];
        }

        unset($this->rows[$index]);
        $this->rows = array_values($this->rows);
    }

    public function updatedUploads(): void
    {
        try {
            $this->validate(['uploads.*' => self::IMAGE_RULES], [
                'uploads.*.image' => 'Envie apenas imagens.',
                'uploads.*.mimes' => 'Use imagens JPG, PNG ou WEBP.',
                'uploads.*.max' => 'Cada imagem pode ter no máximo 5 MB.',
            ]);
        } catch (ValidationException $exception) {
            $this->uploads = [];
            throw $exception;
        }
    }

    public function removeUpload(int $index): void
    {
        unset($this->uploads[$index]);
        $this->uploads = array_values($this->uploads);
    }

    public function removeImage(int $imageId): void
    {
        $this->removedImageIds[] = $imageId;

        if ($this->primaryImageId === $imageId) {
            $this->primaryImageId = null;
        }
    }

    public function makePrimary(int $imageId): void
    {
        $this->primaryImageId = $imageId;
    }

    private function rulesFor(): array
    {
        $money = ['required', 'numeric', 'min:0', 'max:99999999.99'];
        $code = ['nullable', 'string', 'regex:/^[A-Za-z0-9._-]+$/'];

        $rules = [
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'categoryId' => ['required', 'exists:categories,id'],
            'brandId' => ['nullable', 'exists:brands,id'],
            'supplierId' => ['nullable', 'exists:suppliers,id'],
            'internalCode' => [...$code, 'max:30', Rule::unique('products', 'internal_code')->ignore($this->productId)],
            'sku' => [...$code, 'max:64', Rule::unique('products', 'sku')->ignore($this->productId)],
            'costPrice' => $money,
            'salePrice' => $money,
            'isActive' => ['boolean'],
            'rows' => ['required', 'array', 'min:1', 'max:'.self::MAX_COMBINATIONS],
            'uploads.*' => self::IMAGE_RULES,
        ];

        foreach ($this->rows as $i => $row) {
            $rules["rows.{$i}.sku"] = [...$code, 'max:64', Rule::unique('product_variants', 'sku')->ignore($row['id'])];
            $rules["rows.{$i}.barcode"] = [...$code, 'max:64', Rule::unique('product_variants', 'barcode')->ignore($row['id'])];
            $rules["rows.{$i}.cost_price"] = $money;
            $rules["rows.{$i}.sale_price"] = $money;
            $rules["rows.{$i}.min_stock"] = ['required', 'integer', 'min:0', 'max:1000000'];
            $rules["rows.{$i}.is_active"] = ['boolean'];
            $rules["rows.{$i}.initial_stock"] = ['nullable', 'integer', 'min:0', 'max:100000'];
        }

        return $rules;
    }

    private function attributeNames(): array
    {
        $names = [
            'name' => 'nome', 'description' => 'descrição', 'categoryId' => 'categoria', 'brandId' => 'marca',
            'supplierId' => 'fornecedor', 'internalCode' => 'código interno', 'sku' => 'SKU',
            'costPrice' => 'custo', 'salePrice' => 'preço de venda', 'rows' => 'variações',
        ];

        foreach (array_keys($this->rows) as $i) {
            $names["rows.{$i}.sku"] = 'SKU';
            $names["rows.{$i}.barcode"] = 'código de barras';
            $names["rows.{$i}.cost_price"] = 'custo';
            $names["rows.{$i}.sale_price"] = 'preço';
            $names["rows.{$i}.min_stock"] = 'estoque mínimo';
        }

        return $names;
    }

    public function save(ProductService $products): void
    {
        Gate::authorize($this->productId ? 'produtos.editar' : 'produtos.criar');

        $this->name = trim($this->name);
        $this->validate($this->rulesFor(), [], $this->attributeNames());

        $nullable = fn (string $value) => $value === '' ? null : $value;

        $data = [
            'name' => $this->name,
            'description' => $nullable($this->description),
            'category_id' => (int) $this->categoryId,
            'brand_id' => $this->brandId === '' ? null : (int) $this->brandId,
            'supplier_id' => $this->supplierId === '' ? null : (int) $this->supplierId,
            'internal_code' => $nullable($this->internalCode),
            'sku' => $nullable($this->sku),
            'cost_price' => $this->costPrice,
            'sale_price' => $this->salePrice,
            'is_active' => $this->isActive,
        ];

        try {
            $product = $products->save(
                Auth::user(),
                $this->productId ? Product::findOrFail($this->productId) : null,
                $data,
                array_values($this->rows),
                $this->removedVariantIds,
                $this->uploads,
                $this->removedImageIds,
                $this->primaryImageId,
            );
        } catch (BusinessRuleException $exception) {
            session()->now('error', $exception->getMessage());

            return;
        }

        $message = 'Produto salvo.';
        $entryId = $this->registerInitialStock($product);

        if ($entryId) {
            $message = 'Produto salvo e estoque inicial registrado.';
            session()->flash('labels_entry', $entryId);
        }

        session()->flash('success', $message);
        $this->redirectRoute('products.index');
    }

    /** Variações novas com estoque inicial viram uma entrada de mercadorias. */
    private function registerInitialStock(Product $product): ?int
    {
        $wanted = collect($this->rows)
            ->filter(fn (array $row) => ! $row['id'] && (int) ($row['initial_stock'] ?? 0) > 0);

        if ($wanted->isEmpty() || ! Gate::allows('estoque.entrada')) {
            return null;
        }

        $variants = $product->variants()->get()->keyBy('combination_key');
        $items = $wanted->map(fn (array $row) => isset($variants[$row['key']]) ? [
            'variant_id' => $variants[$row['key']]->id,
            'quantity' => (int) $row['initial_stock'],
            'unit_cost' => Gate::allows('produtos.ver_custo') ? $row['cost_price'] : null,
        ] : null)->filter()->values()->all();

        if ($items === []) {
            return null;
        }

        return app(StockEntryService::class)->register(Auth::user(), $product->supplier_id, null, 'Estoque inicial do cadastro', $items)->id;
    }

    public function render(): View
    {
        $selectable = fn ($item, string $current) => $item->is_active || (string) $item->id === $current;

        return view('livewire.products.form', [
            'categories' => Category::ordered()->filter(fn ($c) => $selectable($c, $this->categoryId)),
            'brands' => Brand::orderBy('name')->get()->filter(fn ($b) => $selectable($b, $this->brandId)),
            'suppliers' => Supplier::orderBy('name')->get()->filter(fn ($s) => $selectable($s, $this->supplierId)),
            'variationAttributes' => VariationAttribute::with('values')->orderBy('position')->orderBy('id')->get(),
            'images' => $this->productId
                ? Product::findOrFail($this->productId)->images()->whereNotIn('id', $this->removedImageIds)->get()
                : collect(),
            'canSeeCost' => Auth::user()->can('produtos.ver_custo'),
            'canEnterStock' => Auth::user()->can('estoque.entrada'),
            'hasNewRows' => collect($this->rows)->contains(fn (array $row) => ! $row['id']),
            'maxImages' => ProductService::MAX_IMAGES,
        ])->title($this->productId ? 'Editar produto' : 'Novo produto');
    }
}
