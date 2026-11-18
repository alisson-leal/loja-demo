<?php

namespace App\Livewire\Customers;

use App\Models\Customer;
use App\Rules\Cpf;
use App\Services\CustomerService;
use App\Support\StoreSegment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Form extends Component
{
    #[Locked]
    public ?int $customerId = null;

    public string $name = '';

    public string $cpf = '';

    public string $birth_date = '';

    public string $gender = '';

    public string $phone = '';

    public string $whatsapp = '';

    public string $email = '';

    public string $address = '';

    public string $neighborhood = '';

    public string $city = '';

    public string $zip_code = '';

    public string $notes = '';

    public bool $email_opt_in = false;

    public bool $whatsapp_opt_in = false;

    /** Limite de crediário (R$); só quem tem "crediario.gerir" altera. */
    public string $credit_limit = '';

    /** @var list<array{id: ?int, name: string, birth_date: string, clothing_size: string}> */
    public array $children = [];

    public function mount($customer = null): void
    {
        if ($customer === null) {
            Gate::authorize('clientes.criar');

            return;
        }

        Gate::authorize('clientes.editar');

        $model = Customer::with('children')->findOrFail($customer);
        $this->customerId = $model->id;

        foreach (['name', 'cpf', 'gender', 'phone', 'whatsapp', 'email', 'address', 'neighborhood', 'city', 'zip_code', 'notes'] as $field) {
            $this->{$field} = (string) $model->{$field};
        }

        $this->email_opt_in = $model->email_opt_in;
        $this->whatsapp_opt_in = $model->whatsapp_opt_in;
        $this->credit_limit = $model->credit_limit === null ? '' : (string) $model->credit_limit;
        $this->birth_date = (string) $model->birth_date?->toDateString();
        $this->children = $model->children->map(fn ($child) => [
            'id' => $child->id,
            'name' => $child->name,
            'birth_date' => (string) $child->birth_date?->toDateString(),
            'clothing_size' => (string) $child->clothing_size,
        ])->all();
    }

    public function addChild(): void
    {
        $this->children[] = ['id' => null, 'name' => '', 'birth_date' => '', 'clothing_size' => ''];
    }

    public function removeChild(int $index): void
    {
        unset($this->children[$index]);
        $this->children = array_values($this->children);
    }

    public function save(CustomerService $customers): void
    {
        Gate::authorize($this->customerId ? 'clientes.editar' : 'clientes.criar');

        $this->name = trim($this->name);
        $this->email = Str::lower(trim($this->email));

        foreach (['cpf', 'phone', 'whatsapp', 'zip_code'] as $field) {
            $this->{$field} = preg_replace('/\D/', '', $this->{$field});
        }

        $validated = $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'cpf' => ['nullable', new Cpf, Rule::unique('customers', 'cpf')->ignore($this->customerId)],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today', 'after:1900-01-01'],
            'gender' => ['nullable', Rule::in(array_keys(Customer::GENDER_LABELS))],
            'phone' => ['nullable', 'digits_between:10,11'],
            'whatsapp' => ['nullable', 'digits_between:10,11'],
            'email' => ['nullable', 'required_if_accepted:email_opt_in', 'email', 'max:190'],
            'email_opt_in' => ['boolean'],
            'whatsapp_opt_in' => ['boolean'],
            'address' => ['nullable', 'string', 'max:190'],
            'neighborhood' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'zip_code' => ['nullable', 'digits:8'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'credit_limit' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'children' => ['array', 'max:20'],
            'children.*.name' => ['required', 'string', 'min:2', 'max:100'],
            'children.*.birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'children.*.clothing_size' => ['nullable', 'string', 'max:20'],
        ], [
            'phone.digits_between' => 'Informe o telefone com DDD.',
            'whatsapp.digits_between' => 'Informe o WhatsApp com DDD.',
            'email.required_if_accepted' => 'Informe o e-mail para enviar novidades por e-mail.',
        ], [
            'name' => 'nome', 'cpf' => 'CPF', 'birth_date' => 'data de nascimento', 'gender' => 'sexo',
            'phone' => 'telefone', 'address' => 'endereço', 'neighborhood' => 'bairro', 'city' => 'cidade',
            'zip_code' => 'CEP', 'notes' => 'observações',
            'children.*.name' => 'nome do filho', 'children.*.birth_date' => 'nascimento do filho', 'children.*.clothing_size' => 'tamanho',
        ]);

        if ($this->whatsapp_opt_in && $this->whatsapp === '' && $this->phone === '') {
            $this->addError('whatsapp', 'Informe o WhatsApp para enviar novidades por WhatsApp.');

            return;
        }

        $data = collect($validated)->except(['children', 'email_opt_in', 'whatsapp_opt_in'])
            // O limite de crédito só muda com permissão de gerir o crediário.
            ->when(! Gate::allows('crediario.gerir'), fn ($c) => $c->except('credit_limit'))
            ->map(fn ($value) => $value === '' ? null : $value)->all();

        // Em loja adulta a tela não mostra filhos: os já cadastrados ficam como estão.
        $children = ! StoreSegment::childrenEnabled() ? null : array_map(fn (array $child) => [
            'id' => $child['id'] ?? null,
            'name' => trim($child['name']),
            'birth_date' => $child['birth_date'] ?: null,
            'clothing_size' => trim((string) $child['clothing_size']) ?: null,
        ], array_values($this->children));

        $customer = $customers->save($this->customerId ? Customer::findOrFail($this->customerId) : null, $data, $children, [
            'email' => $this->email_opt_in,
            'whatsapp' => $this->whatsapp_opt_in,
        ]);

        session()->flash('success', 'Cliente salvo.');

        if (Auth::user()->can('clientes.ver')) {
            $this->redirectRoute('customers.show', ['customer' => $customer->id]);
        } else {
            $this->redirectRoute('dashboard');
        }
    }

    public function render(): View
    {
        return view('livewire.customers.form', [
            'genders' => Customer::GENDER_LABELS,
        ])->title($this->customerId ? 'Editar cliente' : 'Novo cliente');
    }
}
