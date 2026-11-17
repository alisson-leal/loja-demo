<?php

namespace App\Livewire\Finance;

use App\Livewire\Concerns\HandlesBusinessRules;
use App\Models\ExpenseCategory;
use App\Models\Payable;
use App\Models\Supplier;
use App\Services\PayableService;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class PayableForm extends Component
{
    use HandlesBusinessRules;

    #[Locked]
    public ?int $payableId = null;

    public string $description = '';

    public string $categoryId = '';

    public string $supplierId = '';

    public string $document = '';

    public string $dueDate = '';

    public string $amount = '';

    public string $notes = '';

    /** unica | parcelada (divide o valor) | mensal (repete o valor) */
    public string $mode = 'unica';

    public string $times = '2';

    public function mount($payable = null): void
    {
        Gate::authorize('financeiro.pagar');

        if ($payable === null) {
            $this->dueDate = today()->toDateString();

            return;
        }

        $model = Payable::findOrFail($payable);
        abort_unless($model->isOpen() && $model->source_type === null, 403, 'Só é possível editar contas em aberto.');

        $this->payableId = $model->id;
        $this->description = $model->description;
        $this->categoryId = (string) $model->expense_category_id;
        $this->supplierId = (string) $model->supplier_id;
        $this->document = (string) $model->document;
        $this->dueDate = $model->due_date->toDateString();
        $this->amount = (string) $model->amount;
        $this->notes = (string) $model->notes;
    }

    public function save(PayableService $payables): void
    {
        Gate::authorize('financeiro.pagar');

        $this->description = trim($this->description);
        $this->amount = str_replace(',', '.', trim($this->amount));

        $this->validate([
            'description' => ['required', 'string', 'min:2', 'max:150'],
            'categoryId' => ['required', Rule::exists('expense_categories', 'id')->where('is_active', true)],
            'supplierId' => ['nullable', Rule::exists('suppliers', 'id')],
            'document' => ['nullable', 'string', 'max:60'],
            'dueDate' => ['required', 'date', 'after:2000-01-01', 'before:2100-01-01'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'notes' => ['nullable', 'string', 'max:500'],
            'mode' => ['required', Rule::in(['unica', 'parcelada', 'mensal'])],
            'times' => $this->mode === 'unica' ? ['nullable'] : ['required', 'integer', 'min:2', 'max:'.PayableService::MAX_REPEAT],
        ], [], [
            'description' => 'descrição', 'categoryId' => 'categoria', 'supplierId' => 'fornecedor', 'document' => 'documento',
            'dueDate' => 'vencimento', 'amount' => 'valor', 'notes' => 'observação', 'times' => 'quantidade de meses',
        ]);

        $data = [
            'description' => $this->description,
            'expense_category_id' => (int) $this->categoryId,
            'supplier_id' => $this->supplierId !== '' ? (int) $this->supplierId : null,
            'document' => trim($this->document) ?: null,
            'due_date' => $this->dueDate,
            'amount_cents' => Money::toCents($this->amount),
            'notes' => trim($this->notes) ?: null,
        ];

        $ok = $this->attempt(function () use ($payables, $data) {
            if ($this->payableId) {
                $payables->update(Payable::findOrFail($this->payableId), $data);
            } else {
                $payables->create($data, $this->mode === 'unica' ? 1 : (int) $this->times, $this->mode === 'parcelada', Auth::user());
            }
        }, 'Conta salva.');

        if ($ok) {
            session()->flash('success', $this->payableId ? 'Conta alterada.' : 'Conta lançada.');
            $this->redirectRoute('finance.payables');
        }
    }

    public function render(): View
    {
        $cents = Money::toCents(str_replace(',', '.', $this->amount));
        $times = max(2, min(PayableService::MAX_REPEAT, (int) $this->times));

        return view('livewire.finance.payable-form', [
            'categories' => ExpenseCategory::where('is_active', true)->orWhere('id', (int) $this->categoryId)->ordered()->get(['id', 'name']),
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
            'preview' => match ($this->mode) {
                'parcelada' => $cents > 0 ? "{$times} parcelas mensais de cerca de ".Money::format(intdiv($cents, $times) / 100).'.' : null,
                'mensal' => $cents > 0 ? "{$times} contas mensais de ".Money::format($cents / 100).', total '.Money::format($cents * $times / 100).'.' : null,
                default => null,
            },
        ])->title($this->payableId ? 'Editar conta a pagar' : 'Nova conta a pagar');
    }
}
