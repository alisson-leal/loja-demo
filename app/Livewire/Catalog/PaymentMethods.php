<?php

namespace App\Livewire\Catalog;

use App\Models\PaymentMethod;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Formas de pagamento')]
class PaymentMethods extends Component
{
    private const LOCKED_TYPES = [PaymentMethod::CASH, PaymentMethod::STORE_CREDIT, PaymentMethod::CREDIARIO];

    #[Locked]
    public ?int $editingId = null;

    public string $name = '';

    public string $type = PaymentMethod::OTHER;

    public bool $isActive = true;

    public string $feePercent = '0';

    public string $installmentFeePercent = '';

    public string $settlementDays = '0';

    public string $maxInstallments = '1';

    public string $lateFeePercent = '0';

    public string $interestPercent = '0';

    public function mount(): void
    {
        Gate::authorize('cadastros.ver');
    }

    public function edit(int $id): void
    {
        Gate::authorize('cadastros.gerir');

        $method = PaymentMethod::findOrFail($id);
        $this->editingId = $method->id;
        $this->name = $method->name;
        $this->type = $method->type;
        $this->isActive = $method->is_active;
        $this->feePercent = (string) (float) $method->fee_percent;
        $this->installmentFeePercent = $method->installment_fee_percent === null ? '' : (string) (float) $method->installment_fee_percent;
        $this->settlementDays = (string) $method->settlement_days;
        $this->maxInstallments = (string) $method->max_installments;
        $this->lateFeePercent = (string) (float) $method->late_fee_percent;
        $this->interestPercent = (string) (float) $method->interest_percent;
        $this->resetValidation();
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->resetValidation();
    }

    private function resetForm(): void
    {
        $this->reset('editingId', 'name', 'type', 'isActive', 'feePercent', 'installmentFeePercent', 'settlementDays', 'maxInstallments', 'lateFeePercent', 'interestPercent');
    }

    public function save(AuditLogger $audit): void
    {
        Gate::authorize('cadastros.gerir');

        $this->name = trim($this->name);

        $existing = $this->editingId ? PaymentMethod::findOrFail($this->editingId) : null;

        // O tipo dos meios que têm regra própria (dinheiro, vale-troca e crediário) não muda.
        $lockedType = $existing && in_array($existing->type, self::LOCKED_TYPES, true);

        $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:60', Rule::unique('payment_methods', 'name')->ignore($this->editingId)],
            'type' => $lockedType ? ['string'] : ['required', Rule::in([PaymentMethod::PIX, PaymentMethod::DEBIT, PaymentMethod::CREDIT, PaymentMethod::OTHER])],
            'feePercent' => ['required', 'numeric', 'min:0', 'max:30'],
            'installmentFeePercent' => ['nullable', 'numeric', 'min:0', 'max:30'],
            'settlementDays' => ['required', 'integer', 'min:0', 'max:120'],
            'maxInstallments' => ['required', 'integer', 'min:1', 'max:24'],
            'lateFeePercent' => ['required', 'numeric', 'min:0', 'max:2'],
            'interestPercent' => ['required', 'numeric', 'min:0', 'max:10'],
        ], [], [
            'name' => 'nome', 'type' => 'tipo', 'feePercent' => 'taxa', 'installmentFeePercent' => 'taxa parcelada',
            'settlementDays' => 'prazo', 'maxInstallments' => 'parcelas', 'lateFeePercent' => 'multa', 'interestPercent' => 'juros',
        ]);

        $type = $lockedType ? $existing->type : $this->type;
        // Dinheiro e vale-troca não passam pela maquininha; só crédito parcela.
        $noTerms = in_array($type, [PaymentMethod::CASH, PaymentMethod::STORE_CREDIT], true);
        $isCredit = $type === PaymentMethod::CREDIT;
        $isCrediario = $type === PaymentMethod::CREDIARIO;

        $method = $existing ?? new PaymentMethod(['position' => (int) PaymentMethod::max('position') + 1]);
        $method->fill([
            'name' => $this->name,
            'type' => $type,
            'is_active' => $this->isActive,
            'fee_percent' => $noTerms || $isCrediario ? 0 : $this->feePercent,
            'installment_fee_percent' => $isCredit && $this->installmentFeePercent !== '' ? $this->installmentFeePercent : null,
            'settlement_days' => $noTerms ? 0 : (int) $this->settlementDays,
            'max_installments' => $isCredit || $isCrediario ? (int) $this->maxInstallments : 1,
            'late_fee_percent' => $isCrediario ? $this->lateFeePercent : 0,
            'interest_percent' => $isCrediario ? $this->interestPercent : 0,
        ])->save();

        $audit->record('payment_method.saved', "Forma de pagamento {$method->name} salva.", $method);

        session()->now('success', 'Forma de pagamento salva. As novas condições valem para as próximas vendas.');
        $this->resetForm();
    }

    public function render(): View
    {
        $editing = $this->editingId ? PaymentMethod::find($this->editingId) : null;

        return view('livewire.catalog.payment-methods', [
            'methods' => PaymentMethod::orderBy('position')->orderBy('id')->get(),
            'typeLabels' => PaymentMethod::TYPE_LABELS + [PaymentMethod::STORE_CREDIT => 'Vale-troca'],
            'selectableTypes' => [PaymentMethod::PIX, PaymentMethod::DEBIT, PaymentMethod::CREDIT, PaymentMethod::OTHER],
            'typeLocked' => $editing && in_array($editing->type, self::LOCKED_TYPES, true),
            'currentType' => $editing && in_array($editing->type, self::LOCKED_TYPES, true) ? $editing->type : $this->type,
        ]);
    }
}
