<?php

namespace App\Livewire\Suppliers;

use App\Models\Supplier;
use App\Services\CatalogService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Form extends Component
{
    #[Locked]
    public ?int $supplierId = null;

    public string $name = '';

    public string $document = '';

    public string $contact_name = '';

    public string $phone = '';

    public string $whatsapp = '';

    public string $email = '';

    public string $address = '';

    public string $city = '';

    public string $state = '';

    public string $zip_code = '';

    public string $notes = '';

    public bool $is_active = true;

    public function mount($supplier = null): void
    {
        if ($supplier === null) {
            Gate::authorize('fornecedores.criar');

            return;
        }

        Gate::authorize('fornecedores.editar');

        $model = Supplier::findOrFail($supplier);
        $this->supplierId = $model->id;

        foreach (['name', 'document', 'contact_name', 'phone', 'whatsapp', 'email', 'address', 'city', 'state', 'zip_code', 'notes'] as $field) {
            $this->{$field} = (string) $model->{$field};
        }

        $this->is_active = $model->is_active;
    }

    public function save(CatalogService $catalog): void
    {
        Gate::authorize($this->supplierId ? 'fornecedores.editar' : 'fornecedores.criar');

        $this->name = trim($this->name);
        $this->document = preg_replace('/\D/', '', $this->document);
        $this->zip_code = preg_replace('/\D/', '', $this->zip_code);
        $this->state = Str::upper(trim($this->state));
        $this->email = Str::lower(trim($this->email));

        $validated = $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'document' => ['nullable', 'regex:/^(\d{11}|\d{14})$/', Rule::unique('suppliers', 'document')->ignore($this->supplierId)],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:20'],
            'whatsapp' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:190'],
            'address' => ['nullable', 'string', 'max:190'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'alpha', 'size:2'],
            'zip_code' => ['nullable', 'digits:8'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ], [
            'document.regex' => 'Informe um CPF (11 dígitos) ou CNPJ (14 dígitos).',
        ], [
            'name' => 'nome', 'document' => 'CPF/CNPJ', 'contact_name' => 'contato', 'phone' => 'telefone',
            'address' => 'endereço', 'city' => 'cidade', 'state' => 'UF', 'zip_code' => 'CEP', 'notes' => 'observações',
        ]);

        $data = collect($validated)->map(fn ($value) => $value === '' ? null : $value)->all();

        $catalog->saveSupplier($this->supplierId ? Supplier::findOrFail($this->supplierId) : null, $data);

        session()->flash('success', 'Fornecedor salvo.');
        $this->redirectRoute('suppliers.index');
    }

    public function render(): View
    {
        return view('livewire.suppliers.form')->title($this->supplierId ? 'Editar fornecedor' : 'Novo fornecedor');
    }
}
