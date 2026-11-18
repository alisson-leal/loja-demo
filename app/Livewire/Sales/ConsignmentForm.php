<?php

namespace App\Livewire\Sales;

use App\Livewire\Concerns\HandlesBusinessRules;
use App\Models\Customer;
use App\Models\ProductVariant;
use App\Services\ConsignmentService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

#[Title('Nova condicional')]
class ConsignmentForm extends Component
{
    use HandlesBusinessRules;

    public ?int $customerId = null;

    public string $customerTerm = '';

    /** @var array<int, int> id da variação => quantidade */
    public array $items = [];

    public string $dueDate = '';

    public string $notes = '';

    public function mount(): void
    {
        Gate::authorize('condicional.criar');
        $this->dueDate = today()->addDays(max(1, (int) config('loja.consignment_days')))->toDateString();

        if (ctype_digit((string) request('cliente'))) {
            $this->customerId = Customer::find((int) request('cliente'))?->id;
        }
    }

    #[On('variant-picked')]
    public function addVariant(int $id): void
    {
        $this->items[$id] = ($this->items[$id] ?? 0) + 1;
    }

    public function remove(int $id): void
    {
        unset($this->items[$id]);
    }

    public function selectCustomer(int $id): void
    {
        $this->customerId = Customer::findOrFail($id)->id;
        $this->reset('customerTerm');
    }

    public function save(ConsignmentService $consignments): void
    {
        Gate::authorize('condicional.criar');

        if (! $this->customerId) {
            $this->addError('customerTerm', 'Escolha o cliente.');

            return;
        }

        try {
            $due = Carbon::createFromFormat('!Y-m-d', $this->dueDate);
        } catch (Throwable) {
            $this->addError('dueDate', 'Informe o prazo de devolução.');

            return;
        }

        $created = null;
        $items = collect($this->items)->map(fn ($qty, $id) => ['variant_id' => (int) $id, 'quantity' => max(0, (int) $qty)])->values()->all();

        if ($this->attempt(function () use ($consignments, $due, $items, &$created) {
            $created = $consignments->open(Auth::user(), $this->customerId, $items, $due, trim($this->notes) ?: null);
        }, 'Condicional aberta.')) {
            session()->flash('success', 'Condicional aberta. As peças ficam reservadas até o acerto.');
            $this->redirectRoute('consignments.show', ['consignment' => $created->id]);
        }
    }

    public function render(): View
    {
        $term = trim($this->customerTerm);

        return view('livewire.sales.consignment-form', [
            'customer' => $this->customerId ? Customer::find($this->customerId) : null,
            'customerResults' => mb_strlen($term) >= 2 ? Customer::search($term)->orderBy('name')->limit(6)->get() : collect(),
            'variants' => ProductVariant::with(['product', 'values.attribute', 'storeStocks'])->whereIn('id', array_keys($this->items))->get()->keyBy('id'),
        ]);
    }
}
