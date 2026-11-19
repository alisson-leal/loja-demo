<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VariationAttribute;
use App\Models\VariationValue;
use App\Support\Money;
use App\Support\StoreSegment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Importa produtos de uma planilha salva como CSV: uma linha por variação
 * (variação principal e cor), agrupadas pelo nome do produto. Primeiro valida tudo e
 * mostra a prévia; a importação é tudo ou nada.
 */
class ProductImportService
{
    public const MAX_ROWS = 3000;

    public const COLUMNS = [
        'nome' => 'Nome do produto (obrigatório; linhas com o mesmo nome viram variações do mesmo produto)',
        'categoria' => 'Categoria (obrigatório; criada se não existir)',
        'subcategoria' => 'Subcategoria dentro da categoria (opcional)',
        'marca' => 'Marca (opcional; criada se não existir)',
        'fornecedor' => 'Fornecedor (opcional; criado se não existir)',
        'variacao' => 'Variação principal: tamanho, numeração, voltagem ou largura (opcional)',
        'cor' => 'Cor (opcional)',
        'preco_venda' => 'Preço de venda (obrigatório, ex.: 129,90)',
        'preco_custo' => 'Preço de custo (opcional)',
        'codigo_barras' => 'Código de barras EAN (opcional)',
        'sku' => 'SKU da variação (opcional; gerado se vazio)',
        'estoque_inicial' => 'Quantidade em estoque (opcional; vira uma entrada de mercadorias)',
        'estoque_minimo' => 'Estoque mínimo (opcional)',
        'descricao' => 'Descrição do produto (opcional)',
    ];

    private const ALIASES = [
        'produto' => 'nome', 'preco' => 'preco_venda', 'valor' => 'preco_venda', 'custo' => 'preco_custo',
        'ean' => 'codigo_barras', 'codigo_de_barras' => 'codigo_barras', 'numeracao' => 'variacao', 'tamanho' => 'variacao', 'voltagem' => 'variacao', 'largura' => 'variacao', 'medida' => 'variacao', 'capacidade' => 'variacao',
        'estoque' => 'estoque_inicial', 'quantidade' => 'estoque_inicial', 'qtd' => 'estoque_inicial', 'minimo' => 'estoque_minimo',
    ];

    public function __construct(
        private readonly ProductService $products,
        private readonly StockEntryService $entries,
        private readonly CatalogService $catalog,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Lê e valida a planilha.
     *
     * @return array{products: list<array<string, mixed>>, errors: list<string>, summary: array<string, int|list<string>>}
     */
    public function analyze(string $path): array
    {
        [$header, $lines] = $this->read($path);
        $errors = [];

        foreach (['nome', 'categoria', 'preco_venda'] as $required) {
            if (! in_array($required, $header, true)) {
                $errors[] = "Falta a coluna obrigatória \"{$required}\" no cabeçalho.";
            }
        }

        if ($errors !== []) {
            return ['products' => [], 'errors' => $errors, 'summary' => []];
        }

        if (count($lines) > self::MAX_ROWS) {
            return ['products' => [], 'errors' => ['A planilha tem mais de '.self::MAX_ROWS.' linhas. Divida em arquivos menores.'], 'summary' => []];
        }

        $products = [];
        $barcodes = [];
        $skus = [];

        foreach ($lines as $number => $cells) {
            $row = [];
            foreach ($header as $i => $column) {
                $row[$column] = trim((string) ($cells[$i] ?? ''));
            }

            if (implode('', $row) === '') {
                continue;
            }

            $line = "Linha {$number}";
            $name = Str::limit($row['nome'] ?? '', 150, '');
            $price = $this->money($row['preco_venda'] ?? '');
            $cost = ($row['preco_custo'] ?? '') === '' ? 0.0 : $this->money($row['preco_custo']);
            $stock = ($row['estoque_inicial'] ?? '') === '' ? 0 : (ctype_digit($row['estoque_inicial']) ? (int) $row['estoque_inicial'] : null);
            $minimum = ($row['estoque_minimo'] ?? '') === '' ? 0 : (ctype_digit($row['estoque_minimo']) ? (int) $row['estoque_minimo'] : null);

            if (mb_strlen($name) < 2) {
                $errors[] = "{$line}: informe o nome do produto.";
            }
            if (($row['categoria'] ?? '') === '') {
                $errors[] = "{$line}: informe a categoria.";
            }
            if ($price === null || $price <= 0) {
                $errors[] = "{$line}: preço de venda inválido (\"{$row['preco_venda']}\").";
            }
            if ($cost === null) {
                $errors[] = "{$line}: preço de custo inválido (\"{$row['preco_custo']}\").";
            }
            if ($stock === null || $minimum === null) {
                $errors[] = "{$line}: estoque deve ser um número inteiro.";
            }

            foreach (['codigo_barras' => &$barcodes, 'sku' => &$skus] as $field => &$seen) {
                $code = $row[$field] ?? '';
                if ($code === '') {
                    continue;
                }
                if (! preg_match('/^[A-Za-z0-9._-]{1,64}$/', $code)) {
                    $errors[] = "{$line}: {$field} com caracteres inválidos.";
                } elseif (isset($seen[Str::lower($code)])) {
                    $errors[] = "{$line}: {$field} {$code} repetido na planilha (linha {$seen[Str::lower($code)]}).";
                } elseif (ProductVariant::where($field === 'codigo_barras' ? 'barcode' : 'sku', $code)->exists()) {
                    $errors[] = "{$line}: {$field} {$code} já está cadastrado no sistema.";
                }
                $seen[Str::lower($code)] = $number;
            }
            unset($seen);

            $key = Str::lower($name);
            $variantKey = Str::lower(($row['variacao'] ?? '').'|'.($row['cor'] ?? ''));

            $products[$key] ??= [
                'name' => $name,
                'category' => $row['categoria'] ?? '',
                'subcategory' => $row['subcategoria'] ?? '',
                'brand' => $row['marca'] ?? '',
                'supplier' => $row['fornecedor'] ?? '',
                'description' => $row['descricao'] ?? '',
                'line' => $number,
                'variants' => [],
            ];

            if (isset($products[$key]['variants'][$variantKey])) {
                $errors[] = "{$line}: a variação ".trim(($row['variacao'] ?? '').' '.($row['cor'] ?? '')).' já apareceu para este produto.';
            }

            $products[$key]['variants'][$variantKey] = [
                'size' => $row['variacao'] ?? '',
                'color' => $row['cor'] ?? '',
                'price' => $price,
                'cost' => $cost ?? 0,
                'barcode' => $row['codigo_barras'] ?? '',
                'sku' => $row['sku'] ?? '',
                'stock' => $stock ?? 0,
                'minimum' => $minimum ?? 0,
            ];
        }

        foreach ($products as $product) {
            if (Product::where('name', $product['name'])->exists()) {
                $errors[] = "Linha {$product['line']}: o produto \"{$product['name']}\" já existe. Remova da planilha ou mude o nome.";
            }

            $withSize = collect($product['variants'])->contains(fn ($v) => $v['size'] !== '');
            $withColor = collect($product['variants'])->contains(fn ($v) => $v['color'] !== '');
            if (count($product['variants']) > 1 && ! $withSize && ! $withColor) {
                $errors[] = "Linha {$product['line']}: o produto \"{$product['name']}\" aparece em várias linhas sem variação nem cor.";
            }
        }

        $products = array_values(array_map(fn ($p) => [...$p, 'variants' => array_values($p['variants'])], $products));

        return [
            'products' => $products,
            'errors' => array_slice($errors, 0, 200),
            'summary' => [
                'products' => count($products),
                'variants' => array_sum(array_map(fn ($p) => count($p['variants']), $products)),
                'stock' => array_sum(array_map(fn ($p) => array_sum(array_column($p['variants'], 'stock')), $products)),
                'new_categories' => $this->missing(array_column($products, 'category'), Category::whereNull('parent_id')->pluck('name')->all()),
                'new_brands' => $this->missing(array_column($products, 'brand'), Brand::pluck('name')->all()),
                'new_suppliers' => $this->missing(array_column($products, 'supplier'), Supplier::pluck('name')->all()),
            ],
        ];
    }

    /** @param list<array<string, mixed>> $products resultado já validado de analyze() */
    public function import(array $products, User $actor, bool $withStock): int
    {
        if ($products === []) {
            throw new BusinessRuleException('Nada para importar.');
        }

        return DB::transaction(function () use ($products, $actor, $withStock) {
            // A coluna "variacao" vira o atributo principal do segmento (Tamanho, Voltagem, Largura...).
            $size = VariationAttribute::firstOrCreate(['name' => StoreSegment::primaryVariation()], ['position' => 1]);
            $color = VariationAttribute::firstOrCreate(['name' => 'Cor'], ['position' => 2]);
            $stockBySupplier = [];

            foreach ($products as $item) {
                $category = $this->category($item['category'], $item['subcategory']);
                $brand = $item['brand'] !== '' ? Brand::firstOrCreate(['name' => $item['brand']], ['is_active' => true]) : null;
                $supplier = $item['supplier'] !== '' ? Supplier::firstOrCreate(['name' => $item['supplier']], ['is_active' => true]) : null;
                $first = $item['variants'][0];

                $rows = array_map(fn (array $v) => [
                    'id' => null,
                    'value_ids' => array_values(array_filter([
                        $v['size'] !== '' ? $this->value($size, $v['size']) : null,
                        $v['color'] !== '' ? $this->value($color, $v['color']) : null,
                    ])),
                    'sku' => $v['sku'] ?: null,
                    'barcode' => $v['barcode'] ?: null,
                    'sale_price' => Money::fromCents(Money::toCents($v['price'])),
                    'cost_price' => Money::fromCents(Money::toCents($v['cost'])),
                    'min_stock' => $v['minimum'],
                    'is_active' => true,
                ], $item['variants']);

                $product = $this->products->save($actor, null, [
                    'name' => $item['name'],
                    'description' => $item['description'] ?: null,
                    'category_id' => $category->id,
                    'brand_id' => $brand?->id,
                    'supplier_id' => $supplier?->id,
                    'sale_price' => $rows[0]['sale_price'],
                    'cost_price' => $rows[0]['cost_price'],
                    'is_active' => true,
                    'internal_code' => null,
                    'sku' => null,
                ], $rows);

                if (! $withStock) {
                    continue;
                }

                $variants = $product->variants()->get()->keyBy('combination_key');
                foreach ($rows as $index => $row) {
                    $quantity = $item['variants'][$index]['stock'];
                    $ids = collect($row['value_ids'])->sort()->implode('-');
                    if ($quantity > 0 && isset($variants[$ids])) {
                        $stockBySupplier[$supplier?->id ?? 0][] = ['variant_id' => $variants[$ids]->id, 'quantity' => $quantity, 'unit_cost' => $row['cost_price']];
                    }
                }
            }

            foreach ($stockBySupplier as $supplierId => $items) {
                $this->entries->register($actor, $supplierId ?: null, null, 'Estoque inicial importado de planilha', $items);
            }

            $this->audit->record('product.imported', count($products).' produto(s) importado(s) de planilha.');

            return count($products);
        });
    }

    /** Modelo de planilha com cabeçalho e exemplos. */
    public static function template(): string
    {
        // Exemplos do segmento da loja (moda, eletrônicos, armarinho ou variedades).
        $rows = [array_keys(self::COLUMNS), ...StoreSegment::importExamples()];

        return "\u{FEFF}".implode("\r\n", array_map(fn ($row) => implode(';', array_map(fn ($cell) => str_contains($cell, ';') ? '"'.$cell.'"' : $cell, $row)), $rows))."\r\n";
    }

    /** @return array{0: list<string>, 1: array<int, list<string>>} */
    private function read(string $path): array
    {
        $content = (string) file_get_contents($path);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        $firstLine = strtok($content, "\n");
        $delimiter = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);

        $header = array_map(fn ($h) => $this->column((string) $h), fgetcsv($handle, 0, $delimiter, '"', '') ?: []);
        $lines = [];
        $number = 1;

        while (($cells = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            $number++;
            $lines[$number] = $cells;
        }

        fclose($handle);

        return [$header, $lines];
    }

    private function column(string $name): string
    {
        $key = Str::of($name)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();

        return self::ALIASES[$key] ?? $key;
    }

    /** Aceita 1.299,90 / 1299,90 / 1299.90. */
    private function money(string $value): ?float
    {
        $value = str_replace(['R$', ' '], '', $value);

        if (preg_match('/^\d{1,3}(\.\d{3})*,\d{1,2}$/', $value) || preg_match('/^\d+,\d{1,2}$/', $value)) {
            $value = str_replace(['.', ','], ['', '.'], $value);
        }

        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    /** @return list<string> */
    private function missing(array $names, array $existing): array
    {
        $existing = array_map(fn ($n) => Str::lower($n), $existing);

        return array_values(array_unique(array_filter($names, fn ($n) => $n !== '' && ! in_array(Str::lower($n), $existing, true))));
    }

    private function category(string $name, string $subcategory): Category
    {
        $parent = Category::whereNull('parent_id')->where('name', $name)->first()
            ?? $this->catalog->saveCategory(null, $name, null, true);

        if ($subcategory === '') {
            return $parent;
        }

        return Category::where('parent_id', $parent->id)->where('name', $subcategory)->first()
            ?? $this->catalog->saveCategory(null, $subcategory, $parent->id, true);
    }

    private function value(VariationAttribute $attribute, string $value): int
    {
        return VariationValue::where('variation_attribute_id', $attribute->id)->where('value', $value)->value('id')
            ?? $this->catalog->addValue($attribute, $value)->id;
    }
}
