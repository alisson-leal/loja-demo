<?php

namespace App\Livewire\Catalog;

use App\Livewire\Concerns\HandlesBusinessRules;
use App\Models\VariationAttribute;
use App\Models\VariationValue;
use App\Services\CatalogService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Variações')]
class Attributes extends Component
{
    use HandlesBusinessRules;

    public string $newAttribute = '';

    /** @var array<int, string> */
    public array $newValues = [];

    public function mount(): void
    {
        Gate::authorize('cadastros.ver');
    }

    public function addAttribute(CatalogService $catalog): void
    {
        Gate::authorize('cadastros.gerir');

        $this->newAttribute = trim($this->newAttribute);
        $this->validate([
            'newAttribute' => ['required', 'string', 'min:2', 'max:60', 'unique:variation_attributes,name'],
        ], [], ['newAttribute' => 'nome do atributo']);

        $this->attempt(fn () => $catalog->saveAttribute(null, $this->newAttribute), 'Atributo cadastrado.');
        $this->reset('newAttribute');
    }

    public function deleteAttribute(int $id, CatalogService $catalog): void
    {
        Gate::authorize('cadastros.gerir');

        $this->attempt(fn () => $catalog->deleteAttribute(VariationAttribute::findOrFail($id)), 'Atributo excluído.');
    }

    public function addValue(int $attributeId, CatalogService $catalog): void
    {
        Gate::authorize('cadastros.gerir');

        $attribute = VariationAttribute::findOrFail($attributeId);
        $this->newValues[$attributeId] = trim($this->newValues[$attributeId] ?? '');

        $this->validate([
            "newValues.{$attributeId}" => ['required', 'string', 'max:60', Rule::unique('variation_values', 'value')
                ->where('variation_attribute_id', $attributeId)],
        ], [], ["newValues.{$attributeId}" => 'valor']);

        $this->attempt(fn () => $catalog->addValue($attribute, $this->newValues[$attributeId]), 'Valor incluído.');
        $this->newValues[$attributeId] = '';
    }

    public function deleteValue(int $id, CatalogService $catalog): void
    {
        Gate::authorize('cadastros.gerir');

        $this->attempt(fn () => $catalog->deleteValue(VariationValue::findOrFail($id)), 'Valor excluído.');
    }

    public function render(): View
    {
        return view('livewire.catalog.attributes', [
            'variationAttributes' => VariationAttribute::with('values')->orderBy('position')->orderBy('id')->get(),
        ]);
    }
}
