<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Supplier;
use App\Models\VariationAttribute;
use App\Models\VariationValue;

/**
 * Cadastros de apoio: categorias, marcas, atributos de variação e fornecedores.
 */
class CatalogService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function saveCategory(?Category $category, string $name, ?int $parentId, bool $isActive): Category
    {
        if ($parentId !== null) {
            $parent = Category::findOrFail($parentId);

            if ($parent->parent_id !== null) {
                throw new BusinessRuleException('Uma subcategoria não pode ter subcategorias.');
            }

            if ($category && ($parent->id === $category->id || $category->children()->exists())) {
                throw new BusinessRuleException('Esta categoria não pode ser movida para dentro de outra.');
            }
        }

        $category ??= new Category;
        $category->fill(['name' => $name, 'parent_id' => $parentId, 'is_active' => $isActive])->save();

        $this->audit->record('catalog.saved', "Categoria {$name} salva.", $category);

        return $category;
    }

    public function deleteCategory(Category $category): void
    {
        if ($category->children()->exists() || $category->products()->exists()) {
            throw new BusinessRuleException('Esta categoria tem subcategorias ou produtos vinculados. Você pode inativá-la.');
        }

        $this->audit->record('catalog.deleted', "Categoria {$category->name} excluída.");
        $category->delete();
    }

    public function saveBrand(?Brand $brand, string $name, bool $isActive): Brand
    {
        $brand ??= new Brand;
        $brand->fill(['name' => $name, 'is_active' => $isActive])->save();

        $this->audit->record('catalog.saved', "Marca {$name} salva.", $brand);

        return $brand;
    }

    public function deleteBrand(Brand $brand): void
    {
        if ($brand->products()->exists()) {
            throw new BusinessRuleException('Esta marca tem produtos vinculados. Você pode inativá-la.');
        }

        $this->audit->record('catalog.deleted', "Marca {$brand->name} excluída.");
        $brand->delete();
    }

    public function saveAttribute(?VariationAttribute $attribute, string $name): VariationAttribute
    {
        $attribute ??= new VariationAttribute(['position' => (int) VariationAttribute::max('position') + 1]);
        $attribute->name = $name;
        $attribute->save();

        $this->audit->record('catalog.saved', "Atributo de variação {$name} salvo.", $attribute);

        return $attribute;
    }

    public function deleteAttribute(VariationAttribute $attribute): void
    {
        if ($attribute->values()->whereHas('variants')->exists()) {
            throw new BusinessRuleException('Este atributo está em uso por produtos e não pode ser excluído.');
        }

        $this->audit->record('catalog.deleted', "Atributo de variação {$attribute->name} excluído.");
        $attribute->values()->delete();
        $attribute->delete();
    }

    public function addValue(VariationAttribute $attribute, string $value): VariationValue
    {
        $created = $attribute->values()->create([
            'value' => $value,
            'position' => (int) $attribute->values()->max('position') + 1,
        ]);

        $this->audit->record('catalog.saved', "Valor {$value} incluído em {$attribute->name}.", $created);

        return $created;
    }

    public function deleteValue(VariationValue $value): void
    {
        if ($value->variants()->exists()) {
            throw new BusinessRuleException("O valor {$value->value} está em uso por produtos e não pode ser excluído.");
        }

        $this->audit->record('catalog.deleted', "Valor {$value->value} excluído de {$value->attribute->name}.");
        $value->delete();
    }

    /** @param array<string, mixed> $data */
    public function saveSupplier(?Supplier $supplier, array $data): Supplier
    {
        $supplier ??= new Supplier;
        $supplier->fill($data)->save();

        $this->audit->record('supplier.saved', "Fornecedor {$supplier->name} salvo.", $supplier);

        return $supplier;
    }
}
