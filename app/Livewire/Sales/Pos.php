<?php

namespace App\Livewire\Sales;

use App\Exceptions\BusinessRuleException;
use App\Models\Consignment;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\ProductVariant;
use App\Models\StoreCredit;
use App\Services\CashService;
use App\Services\CrediarioService;
use App\Services\CustomerService;
use App\Services\SaleService;
use App\Support\Money;
use App\Support\SaleCalculator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Session;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Frente de caixa')]
class Pos extends Component
{
    // O carrinho fica na sessão: recarregar a página não perde a venda em andamento.

    /** @var list<array{variant_id: int, quantity: string, discount: string}> */
    #[Session]
    public array $cart = [];

    #[Session]
    public ?int $customerId = null;

    #[Session]
    public string $orderDiscount = '';

    #[Session]
    public string $orderDiscountType = 'value';

    #[Session]
    public string $checkoutToken = '';

    /** Condicional sendo paga nesta venda (acerto feito na tela da condicional). */
    #[Session]
    public ?int $consignmentId = null;

    /** @var list<array{method_id: int, label: string, is_cash: bool, amount: string, credit_code: ?string}> */
    public array $payments = [];

    public string $notes = '';

    public string $customerTerm = '';

    public bool $showNewCustomer = false;

    public string $newCustomerName = '';

    public string $newCustomerPhone = '';

    public string $newCustomerEmail = '';

    public bool $newCustomerConsent = false;

    public string $creditCode = '';

    public function mount(): void
    {
        Gate::authorize('pdv.vender');

        if ($this->checkoutToken === '') {
            $this->checkoutToken = (string) Str::uuid();
        }

        // Vindo do acerto da condicional: o carrinho começa com as peças que o cliente ficou.
        if ($handoff = session()->pull('pos.consignment')) {
            $this->reset('payments', 'notes', 'orderDiscount', 'orderDiscountType');
            $this->cart = $handoff['cart'];
            $this->customerId = $handoff['customer_id'];
            $this->consignmentId = $handoff['consignment_id'];
            $this->checkoutToken = (string) Str::uuid();
        }
    }

    public function detachConsignment(): void
    {
        $this->consignmentId = null;
    }

    #[On('variant-picked')]
    public function addVariant(int $id): void
    {
        foreach ($this->cart as $index => $line) {
            if ($line['variant_id'] === $id) {
                $this->cart[$index]['quantity'] = (string) ((int) $line['quantity'] + 1);
                $this->dispatch('focus-picker');

                return;
            }
        }

        $this->cart[] = ['variant_id' => $id, 'quantity' => '1', 'discount' => ''];
        $this->dispatch('focus-picker');
    }

    public function changeQuantity(int $index, int $delta): void
    {
        if (isset($this->cart[$index])) {
            $this->cart[$index]['quantity'] = (string) max(1, (int) $this->cart[$index]['quantity'] + $delta);
        }
    }

    public function removeLine(int $index): void
    {
        unset($this->cart[$index]);
        $this->cart = array_values($this->cart);
    }

    public function clearSale(): void
    {
        $this->reset('cart', 'customerId', 'orderDiscount', 'orderDiscountType', 'payments', 'notes', 'creditCode', 'consignmentId');
        $this->dispatch('focus-picker');
    }

    public function selectCustomer(int $id): void
    {
        $this->customerId = Customer::findOrFail($id)->id;
        $this->reset('customerTerm', 'showNewCustomer');
    }

    public function clearCustomer(): void
    {
        $this->customerId = null;
    }

    public function createCustomer(): void
    {
        Gate::authorize('clientes.criar');

        $this->newCustomerPhone = preg_replace('/\D/', '', $this->newCustomerPhone);
        $this->newCustomerEmail = Str::lower(trim($this->newCustomerEmail));

        $this->validate([
            'newCustomerName' => ['required', 'string', 'min:2', 'max:150'],
            'newCustomerPhone' => ['nullable', 'digits_between:10,11'],
            'newCustomerEmail' => ['nullable', 'email', 'max:190'],
        ], [
            'newCustomerPhone.digits_between' => 'Informe o telefone com DDD.',
        ], ['newCustomerName' => 'nome', 'newCustomerPhone' => 'telefone', 'newCustomerEmail' => 'e-mail']);

        $this->customerId = app(CustomerService::class)
            ->quickCreate(trim($this->newCustomerName), $this->newCustomerPhone ?: null, $this->newCustomerEmail ?: null, $this->newCustomerConsent)
            ->id;
        $this->reset('showNewCustomer', 'newCustomerName', 'newCustomerPhone', 'newCustomerEmail', 'newCustomerConsent', 'customerTerm');
    }

    public function addPayment(int $methodId): void
    {
        $method = PaymentMethod::selectable()->findOrFail($methodId);
        $remaining = $this->computation()['remaining'];

        if ($method->isCash() && collect($this->payments)->contains('is_cash', true)) {
            return;
        }

        $this->payments[] = [
            'method_id' => $method->id,
            'label' => $method->name,
            'is_cash' => $method->isCash(),
            'amount' => Money::fromCents($remaining),
            'credit_code' => null,
            'installments' => 1,
            'max_installments' => $method->allowsInstallments() ? $method->max_installments : 1,
        ];
    }

    public function applyCredit(): void
    {
        $code = Str::upper(trim($this->creditCode));
        $credit = StoreCredit::where('code', $code)->first();
        $method = PaymentMethod::where('type', PaymentMethod::STORE_CREDIT)->where('is_active', true)->first();
        $balance = $credit ? Money::toCents($credit->balance) : 0;

        if (! $method || $balance === 0 || collect($this->payments)->contains('credit_code', $code)) {
            $this->addError('creditCode', 'Vale-troca inválido, sem saldo ou já aplicado.');

            return;
        }

        $this->payments[] = [
            'method_id' => $method->id,
            'label' => "Vale-troca {$code}",
            'is_cash' => false,
            'amount' => Money::fromCents(min($balance, $this->computation()['remaining'])),
            'credit_code' => $code,
        ];

        $this->reset('creditCode');
        $this->resetValidation('creditCode');
    }

    public function removePayment(int $index): void
    {
        unset($this->payments[$index]);
        $this->payments = array_values($this->payments);
    }

    /** @return array<string, mixed> Mesmos cálculos usados na finalização. */
    private function computation(): array
    {
        $variants = ProductVariant::with(['product', 'values.attribute', 'storeStocks'])
            ->whereIn('id', array_column($this->cart, 'variant_id'))
            ->get()
            ->keyBy('id');

        $input = array_map(fn (array $line) => [
            'unit_price' => Money::toCents($variants[$line['variant_id']]->sale_price ?? 0),
            'quantity' => max(1, (int) $line['quantity']),
            'discount' => max(0, Money::toCents($line['discount'])),
        ], $this->cart);

        $calculation = SaleCalculator::calculate($input, 0);
        $orderDiscount = max(0, $this->orderDiscountType === 'percent'
            ? (int) round($calculation['total'] * min(100, (float) (is_numeric($this->orderDiscount) ? $this->orderDiscount : 0)) / 100)
            : Money::toCents($this->orderDiscount));
        $calculation = SaleCalculator::calculate($input, $orderDiscount);

        $paid = array_sum(array_map(fn (array $payment) => max(0, Money::toCents($payment['amount'])), $this->payments));
        $cash = array_sum(array_map(fn (array $payment) => $payment['is_cash'] ? max(0, Money::toCents($payment['amount'])) : 0, $this->payments));

        return $calculation + [
            'variants' => $variants,
            'order_discount' => $orderDiscount,
            'paid' => $paid,
            'remaining' => max(0, $calculation['total'] - $paid),
            'change' => min($cash, max(0, $paid - $calculation['total'])),
        ];
    }

    public function finalize(SaleService $sales)
    {
        Gate::authorize('pdv.vender');

        $computation = $this->computation();

        try {
            $sale = $sales->finalize(
                Auth::user(),
                array_map(fn (array $line) => [
                    'variant_id' => $line['variant_id'],
                    'quantity' => max(1, (int) $line['quantity']),
                    'discount' => max(0, Money::toCents($line['discount'])),
                ], $this->cart),
                array_map(fn (array $payment) => [
                    'method_id' => $payment['method_id'],
                    'amount' => Money::toCents($payment['amount']),
                    'credit_code' => $payment['credit_code'],
                    'installments' => max(1, (int) ($payment['installments'] ?? 1)),
                ], $this->payments),
                $this->customerId,
                $computation['order_discount'],
                trim($this->notes) ?: null,
                $this->checkoutToken,
                $this->consignmentId,
            );
        } catch (BusinessRuleException $exception) {
            session()->now('error', $exception->getMessage());

            return null;
        }

        $this->reset('cart', 'customerId', 'orderDiscount', 'orderDiscountType', 'payments', 'notes', 'consignmentId');
        $this->checkoutToken = (string) Str::uuid();

        session()->flash('success', 'Venda finalizada.');

        return $this->redirectRoute('sales.show', ['sale' => $sale->id]);
    }

    public function render(CashService $cash): View
    {
        $user = Auth::user();
        $computation = $this->computation();
        $term = trim($this->customerTerm);

        return view('livewire.sales.pos', [
            'computation' => $computation,
            'session' => $cash->currentFor($user),
            'methods' => PaymentMethod::selectable()->get(),
            'customer' => $this->customerId ? Customer::find($this->customerId) : null,
            'customerResults' => mb_strlen($term) >= 2 ? Customer::search($term)->orderBy('name')->limit(6)->get() : collect(),
            'canDiscount' => $user->can('pdv.desconto'),
            'canCreateCustomer' => $user->can('clientes.criar'),
            'consignment' => $this->consignmentId ? Consignment::find($this->consignmentId) : null,
            'crediarioPosition' => $this->customerId && $user->can('crediario.vender')
                ? app(CrediarioService::class)->position(Customer::find($this->customerId))
                : null,
        ]);
    }
}
