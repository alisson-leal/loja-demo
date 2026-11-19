<?php

namespace App\Livewire\Sales;

use App\Exceptions\BusinessRuleException;
use App\Livewire\Concerns\RequiresSupervisor;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Services\SaleReturnService;
use App\Services\SerialService;
use App\Support\Quantity;
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

    /** @var array<int, list<string>> id do item => números de série marcados para devolver */
    public array $returnSerials = [];

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

            if ($item->serials) {
                $this->returnSerials[$item->id] = [];
            }
        }
    }

    public function updatedReturnSerials(): void
    {
        foreach ($this->returnSerials as $itemId => $serials) {
            $this->quantities[$itemId] = (string) count((array) $serials);
        }
    }

    public function save(SaleReturnService $returns): void
    {
        abort_unless(Gate::any(['vendas.devolver', 'pdv.vender']), 403);

        $this->updatedReturnSerials();

        // Por metro ou quilo, a quantidade devolvida aceita decimais (ex.: 0,5).
        $units = Sale::with('items')->findOrFail($this->saleId)->items->pluck('unit', 'id');
        $rules = [
            'reason' => ['required', 'string', 'min:3', 'max:255'],
            'settlement' => ['required', 'string'],
        ];

        foreach (array_keys($this->quantities) as $itemId) {
            $unit = $units[$itemId] ?? null;
            $rules["quantities.{$itemId}"] = Quantity::isFractional($unit)
                ? ['nullable', fn ($attribute, $value, $fail) => trim((string) $value) === '' || trim((string) $value) === '0' || Quantity::parse($value, $unit) > 0
                    ? null
                    : $fail('Informe a quantidade com até 3 casas (ex.: 0,5).')]
                : ['required', 'integer', 'min:0', 'max:1000000'];
        }

        $this->validate($rules, [], ['quantities.*' => 'quantidade', 'reason' => 'motivo']);

        $items = [];

        foreach ($this->quantities as $itemId => $quantity) {
            $items[] = [
                'sale_item_id' => (int) $itemId,
                'quantity' => Quantity::isFractional($units[$itemId] ?? null) ? Quantity::parse($quantity, $units[$itemId]) : (int) $quantity,
                'restock' => (bool) ($this->restock[$itemId] ?? false),
                'serials' => array_values(array_map('strval', (array) ($this->returnSerials[$itemId] ?? []))),
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

    public function render(SerialService $serials): View
    {
        $sale = Sale::with('items')->findOrFail($this->saleId);

        return view('livewire.sales.return-form', [
            'sale' => $sale,
            'outstanding' => $sale->items->filter(fn ($item) => $item->serials)->mapWithKeys(fn ($item) => [$item->id => $serials->stillSold($item)]),
            'methods' => PaymentMethod::selectable()->where('type', '!=', PaymentMethod::CREDIARIO)->get(),
            'needsSupervisor' => ! Gate::allows('vendas.devolver'),
        ]);
    }
}
