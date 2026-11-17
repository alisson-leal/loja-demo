<?php

namespace App\Livewire\Sales;

use App\Exceptions\BusinessRuleException;
use App\Livewire\Concerns\RequiresSupervisor;
use App\Mail\SaleReceipt;
use App\Models\Sale;
use App\Services\SaleReceiptService;
use App\Services\SaleService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

#[Title('Venda')]
class Show extends Component
{
    use RequiresSupervisor;

    #[Locked]
    public int $saleId;

    public string $phone = '';

    public string $email = '';

    public bool $confirmingCancel = false;

    public string $cancelReason = '';

    public function mount($sale): void
    {
        $model = Sale::with('customer')->findOrFail($sale);
        abort_unless($model->isVisibleTo(Auth::user()), 403);

        $this->saleId = $model->id;
        $this->phone = (string) ($model->customer?->whatsapp ?: $model->customer?->phone);
        $this->email = (string) $model->customer?->email;
    }

    private function sale(): Sale
    {
        return Sale::with([
            'items', 'payments.method', 'payments.storeCredit', 'customer', 'user:id,name',
            'canceller:id,name', 'cancelAuthorizer:id,name',
            'returns.user:id,name', 'returns.authorizer:id,name', 'returns.method', 'returns.storeCredit', 'returns.items.saleItem',
        ])->findOrFail($this->saleId);
    }

    public function sendEmail(): void
    {
        $this->validate(['email' => ['required', 'email', 'max:190']], [], ['email' => 'e-mail']);

        try {
            Mail::to($this->email)->send(new SaleReceipt($this->sale()));
            session()->now('success', 'Comprovante enviado por e-mail.');
        } catch (Throwable $exception) {
            report($exception);
            session()->now('error', 'Não foi possível enviar o e-mail. Confira o endereço e tente novamente.');
        }
    }

    public function cancel(SaleService $sales): void
    {
        abort_unless(Gate::any(['vendas.cancelar', 'pdv.vender']), 403);

        $this->validate(['cancelReason' => ['required', 'string', 'min:3', 'max:255']], [], ['cancelReason' => 'motivo']);

        try {
            $sales->cancel(Auth::user(), $this->sale(), $this->cancelReason, $this->supervisorFor('vendas.cancelar'));
            session()->now('success', 'Venda cancelada. Estoque e valores foram estornados.');
            $this->reset('confirmingCancel', 'cancelReason', 'supervisorEmail');
        } catch (BusinessRuleException $exception) {
            session()->now('error', $exception->getMessage());
        }
    }

    public function render(SaleReceiptService $receipts): View
    {
        $sale = $this->sale();
        $canAuthorizeOnSite = Gate::allows('pdv.vender');

        return view('livewire.sales.show', [
            'sale' => $sale,
            'whatsappUrl' => $receipts->whatsappUrl($sale, $this->phone),
            'canCancel' => $sale->isCompleted() && $sale->returns->isEmpty() && (Gate::allows('vendas.cancelar') || $canAuthorizeOnSite),
            'canReturn' => $sale->isCompleted() && $sale->items->sum(fn ($item) => $item->returnableQuantity()) > 0 && (Gate::allows('vendas.devolver') || $canAuthorizeOnSite),
            'needsSupervisor' => ! Gate::allows('vendas.cancelar'),
        ])->title("Venda #{$sale->number()}");
    }
}
