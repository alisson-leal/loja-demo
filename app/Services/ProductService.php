<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\SerialNumber;
use App\Models\User;
use App\Models\VariantCostHistory;
use App\Models\VariationValue;
use App\Support\Quantity;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ProductService
{
    public const MAX_IMAGES = 8;

    private const DISK = 'public';

    public function __construct(
        private readonly VariantCostService $costs,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Cria ou atualiza o produto com variações e imagens em uma única transação.
     *
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $rows  Linhas de variação do formulário.
     * @param  list<int>  $removedVariantIds
     * @param  list<UploadedFile>  $uploads
     * @param  list<int>  $removedImageIds
     */
    public function save(
        User $actor,
        ?Product $product,
        array $data,
        array $rows,
        array $removedVariantIds = [],
        array $uploads = [],
        array $removedImageIds = [],
        ?int $primaryImageId = null,
    ): Product {
        if ($rows === []) {
            throw new BusinessRuleException('O produto precisa ter ao menos uma variação.');
        }

        $this->assertNoDuplicates($rows);

        $storedPaths = [];
        $pathsToDelete = [];

        try {
            $saved = DB::transaction(function () use (
                $actor, $product, $data, $rows, $removedVariantIds, $uploads,
                $removedImageIds, $primaryImageId, &$storedPaths, &$pathsToDelete
            ) {
                $isNew = $product === null;
                $canEditCost = $actor->can('produtos.ver_custo');

                $product = $this->persistProduct($product ?? new Product, $data, $canEditCost);
                $changed = array_keys($product->getChanges());

                $this->removeVariants($product, $removedVariantIds);
                $this->persistVariants($product, $rows, $actor, $canEditCost);

                if (! $product->variants()->exists()) {
                    throw new BusinessRuleException('O produto precisa ter ao menos uma variação.');
                }

                $pathsToDelete = $this->removeImages($product, $removedImageIds);
                $this->storeImages($product, $uploads, $storedPaths);
                $this->applyPrimaryImage($product, $primaryImageId);

                $this->audit->record(
                    $isNew ? 'product.created' : 'product.updated',
                    "Produto {$product->name} ".($isNew ? 'cadastrado.' : 'alterado.'),
                    $product,
                    ['campos_alterados' => $changed, 'variacoes' => count($rows)],
                );

                return $product;
            });
        } catch (UniqueConstraintViolationException) {
            $this->deleteFiles($storedPaths);
            throw new BusinessRuleException('Já existe um produto ou variação com este código, SKU ou código de barras.');
        } catch (Throwable $exception) {
            $this->deleteFiles($storedPaths);
            throw $exception;
        }

        $this->deleteFiles($pathsToDelete);

        return $saved;
    }

    /**
     * Ligar o controle de série exige estoque zerado (as unidades entram depois,
     * com os números). Desligar exige que nenhuma unidade esteja em estoque.
     */
    private function assertCanChangeSerialControl(Product $product, bool $enable): void
    {
        if (! $product->exists) {
            return;
        }

        $variantIds = $product->variants()->pluck('id');

        if ($enable && ProductVariant::whereIn('id', $variantIds)->where(fn ($q) => $q->where('stock_on_hand', '!=', 0)->orWhere('stock_reserved', '!=', 0))->exists()) {
            throw new BusinessRuleException('Para controlar o número de série, o estoque do produto precisa estar zerado. Zere o saldo e registre a entrada informando os números.');
        }

        if (! $enable && SerialNumber::whereIn('product_variant_id', $variantIds)->whereIn('status', [SerialNumber::IN_STOCK, SerialNumber::IN_TRANSIT])->exists()) {
            throw new BusinessRuleException('Há unidades com número de série em estoque ou em transferência. Dê saída nelas antes de desligar o controle.');
        }
    }

    /**
     * A unidade define a escala das quantidades já registradas: só muda enquanto
     * o produto não tem movimentação de estoque, venda ou reserva.
     */
    private function assertCanChangeUnit(Product $product, string $unit): void
    {
        if (! array_key_exists($unit, Quantity::UNITS)) {
            throw new BusinessRuleException('Unidade de venda inválida.');
        }

        if ($product->exists && $product->variants()->get()->contains(fn (ProductVariant $variant) => $variant->hasHistory() || $variant->stock_on_hand !== 0)) {
            throw new BusinessRuleException('A unidade de venda só pode mudar enquanto o produto não tem movimentação de estoque. Cadastre um novo produto com a unidade desejada.');
        }
    }

    /** @param array<string, mixed> $data */
    private function persistProduct(Product $product, array $data, bool $canEditCost): Product
    {
        $product->fill([
            'name' => $data['name'],
            'description' => $data['description'],
            'category_id' => $data['category_id'],
            'brand_id' => $data['brand_id'],
            'supplier_id' => $data['supplier_id'],
            'sale_price' => $data['sale_price'],
            'is_active' => $data['is_active'],
        ]);

        if (array_key_exists('warranty_months', $data)) {
            $product->warranty_months = $data['warranty_months'] ?: null;
        }

        if (array_key_exists('tracks_serial', $data) && (bool) $data['tracks_serial'] !== (bool) $product->tracks_serial) {
            $this->assertCanChangeSerialControl($product, (bool) $data['tracks_serial']);
            $product->tracks_serial = (bool) $data['tracks_serial'];
        }

        if (array_key_exists('unit', $data) && $data['unit'] !== $product->unit) {
            $this->assertCanChangeUnit($product, $data['unit']);
            $product->unit = $data['unit'];
        }

        if ($product->isFractional() && $product->tracks_serial) {
            throw new BusinessRuleException('Produto vendido por metro ou quilo não tem controle de número de série.');
        }

        if ($canEditCost) {
            $product->cost_price = $data['cost_price'];
        }

        if (filled($data['internal_code'])) {
            $product->internal_code = $data['internal_code'];
        }

        if (filled($data['sku'])) {
            $product->sku = $data['sku'];
        }

        $product->save();

        // O código automático depende do ID, conhecido só depois de inserir.
        $product->internal_code ??= 'LD'.str_pad((string) $product->id, 6, '0', STR_PAD_LEFT);
        $product->sku ??= $product->internal_code;

        if ($product->isDirty()) {
            $product->saveQuietly();
        }

        return $product;
    }

    /** @param list<int> $variantIds */
    private function removeVariants(Product $product, array $variantIds): void
    {
        foreach ($product->variants()->whereIn('id', $variantIds)->get() as $variant) {
            if ($variant->hasHistory()) {
                throw new BusinessRuleException("A variação {$variant->sku} tem movimentações e não pode ser removida. Desative-a.");
            }

            $variant->delete();
        }
    }

    /** @param list<array<string, mixed>> $rows */
    private function persistVariants(Product $product, array $rows, User $actor, bool $canEditCost): void
    {
        foreach ($rows as $row) {
            $variant = filled($row['id'] ?? null)
                ? $product->variants()->findOrFail($row['id'])
                : $this->newVariant($product, $row);

            $variant->fill([
                'sku' => filled($row['sku']) ? $row['sku'] : $variant->sku,
                'barcode' => filled($row['barcode']) ? $row['barcode'] : null,
                'sale_price' => $row['sale_price'],
                'min_stock' => $row['min_stock'],
                'is_active' => $row['is_active'],
            ])->save();

            if ($canEditCost) {
                $this->costs->change($variant, $row['cost_price'], VariantCostHistory::SOURCE_MANUAL, $actor);
            }
        }
    }

    /** @param array<string, mixed> $row */
    private function newVariant(Product $product, array $row): ProductVariant
    {
        $valueIds = collect($row['value_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->sort()->values();

        $values = VariationValue::whereIn('id', $valueIds)->get();

        if ($values->count() !== $valueIds->count()
            || $values->pluck('variation_attribute_id')->unique()->count() !== $values->count()) {
            throw new BusinessRuleException('Combinação de variação inválida.');
        }

        $variant = new ProductVariant([
            'product_id' => $product->id,
            'combination_key' => $valueIds->implode('-'),
            'cost_price' => 0,
        ]);
        $variant->sku = $this->generateSku($product, $values->sortBy('id')->pluck('value')->implode(' '));
        $variant->save();
        $variant->values()->sync($valueIds->all());

        return $variant;
    }

    private function generateSku(Product $product, string $suffix): string
    {
        $slug = Str::upper(Str::slug($suffix));
        $base = Str::limit($product->sku.($slug !== '' ? "-{$slug}" : ''), 56, '');
        $candidate = $base;
        $counter = 2;

        while (ProductVariant::where('sku', $candidate)->exists()) {
            $candidate = "{$base}-{$counter}";
            $counter++;
        }

        return $candidate;
    }

    /** @param list<array<string, mixed>> $rows */
    private function assertNoDuplicates(array $rows): void
    {
        foreach (['sku' => 'SKU', 'barcode' => 'código de barras'] as $field => $label) {
            $values = collect($rows)->pluck($field)->filter(fn ($value) => filled($value))->map(fn ($value) => Str::lower($value));
            $repeated = $values->duplicates()->first();

            if ($repeated !== null) {
                throw new BusinessRuleException("O {$label} {$repeated} foi informado em mais de uma variação.");
            }
        }
    }

    /**
     * @param  list<int>  $imageIds
     * @return list<string>
     */
    private function removeImages(Product $product, array $imageIds): array
    {
        $images = $product->images()->whereIn('id', $imageIds)->get();
        $paths = $images->pluck('path')->all();

        ProductImage::whereIn('id', $images->pluck('id'))->delete();

        return $paths;
    }

    /**
     * @param  list<UploadedFile>  $uploads
     * @param  list<string>  $storedPaths
     */
    private function storeImages(Product $product, array $uploads, array &$storedPaths): void
    {
        $count = $product->images()->count();

        if ($count + count($uploads) > self::MAX_IMAGES) {
            throw new BusinessRuleException('Cada produto pode ter no máximo '.self::MAX_IMAGES.' imagens.');
        }

        $position = ((int) $product->images()->max('position')) + 1;

        foreach ($uploads as $upload) {
            $path = $upload->store("product-images/{$product->id}", self::DISK);
            $storedPaths[] = $path;
            $product->images()->create(['path' => $path, 'position' => $position++]);
        }
    }

    private function applyPrimaryImage(Product $product, ?int $primaryImageId): void
    {
        if ($primaryImageId === null || ! $product->images()->whereKey($primaryImageId)->exists()) {
            return;
        }

        $position = 1;

        foreach ($product->images()->get() as $image) {
            $image->update(['position' => $image->id === $primaryImageId ? 0 : $position++]);
        }
    }

    /** @param list<string> $paths */
    private function deleteFiles(array $paths): void
    {
        if ($paths !== []) {
            Storage::disk(self::DISK)->delete($paths);
        }
    }
}
