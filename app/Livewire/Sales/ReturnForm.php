<?php

namespace App\Livewire\Sales;

use App\Exceptions\BusinessRuleException;
use App\Livewire\Concerns\RequiresSupervisor;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Services\SaleReturnService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Devolução ou troca')]
class ReturnForm extends Component
{
    use RequiresSupervisor;

    #[Locked]
    public int $saleId;

    /** @var array<int, string> */
    public array $quantities = [];

    /** @var array<int, bool> */
    public array $restock = [];

    public string $reason = '';

    /** "credit" gera vale-troca; um número é a forma de pagamento do reembolso. */
    public string $settlement = 'credit';

    public function mount($sale): void
    {
        $model = Sale::with('items')->findOrFail($sale);

        abort_unless(Gate::allows('vendas.devolver') || (Gate::allows('pdv.vender') && $model->isVisibleTo(Auth::user())), 403);
        abort_unless($model->isCompleted(), 404);

        $this->saleId = $model->id;

        foreach ($model->items as $item) {
            $this->quantities[$item->id] = '0';
            $this->restock[$item->id] = true;
        }
    }

    public function save(SaleReturnService $returns): void
    {
        abort_unless(Gate::any(['vendas.devolver', 'pdv.vender']), 403);

        $this->validate([
            'quantities.*' => ['required', 'integer', 'min:0', 'max:1000000'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
            'settlement' => ['required', 'string'],
        ], [], ['quantities.*' => 'quantidade', 'reason' => 'motivo']);

        $items = [];

        foreach ($this->quantities as $itemId => $quantity) {
            $items[] = [
                'sale_item_id' => (int) $itemId,
                'quantity' => (int) $quantity,
                'restock' => (bool) ($this->restock[$itemId] ?? false),
            ];
        }

        try {
            $return = $returns->register(
                Auth::user(),
                Sale::findOrFail($this->saleId),
                $items,
                $this->reason,
                $this->settlement === 'credit' ? null : (int) $this->settlement,
                $this->supervisorFor('vendas.devolver'),
            );
        } catch (BusinessRuleException $exception) {
            session()->now('error', $exception->getMessage());

            return;
        }

        $message = $return->store_credit_id
            ? "Devolução registrada. Vale-troca gerado: {$return->storeCredit->code}."
            : 'Devolução registrada e reembolso lançado.';

        session()->flash('success', $message);
        $this->redirectRoute('sales.show', ['sale' => $this->saleId]);
    }

    public function render(): View
    {
        return view('livewire.sales.return-form', [
            'sale' => Sale::with('items')->findOrFail($this->saleId),
            'methods' => PaymentMethod::selectable()->where('type', '!=', PaymentMethod::CREDIARIO)->get(),
            'needsSupervisor' => ! Gate::allows('vendas.devolver'),
        ]);
    }
}
