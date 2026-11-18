<?php

namespace App\Livewire\Sales;

use App\Livewire\Concerns\HandlesBusinessRules;
use App\Models\Consignment;
use App\Services\ConsignmentService;
use App\Support\Money;
use App\Support\WhatsApp;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ConsignmentShow extends Component
{
    use HandlesBusinessRules;

    #[Locked]
    public int $consignmentId;

    /** @var array<int, string> id do item => peças devolvidas */
    public array $returned = [];

    public function mount($consignment): void
    {
        abort_unless(Auth::user()->canAny(['condicional.criar', 'condicional.acertar']), 403);
        $model = Consignment::with('items')->findOrFail($consignment);
        $this->consignmentId = $model->id;

        foreach ($model->items as $item) {
            $this->returned[$item->id] = (string) $item->quantity;
        }
    }

    private function consignment(): Consignment
    {
        return Consignment::with('items.variant', 'customer', 'user', 'sale')->findOrFail($this->consignmentId);
    }

    public function settle(ConsignmentService $service): void
    {
        Gate::authorize('condicional.acertar');
        $kept = 0;
        $values = array_map(fn ($v) => ctype_digit((string) $v) ? (int) $v : -1, $this->returned);

        if ($this->attempt(function () use ($service, $values, &$kept) {
            $kept = $service->settle($this->consignment(), $values, Auth::user());
        }, 'Acerto registrado.')) {
            if ($kept > 0) {
                $this->toCashier($service);
            } else {
                session()->now('success', 'Todas as peças voltaram. Condicional encerrada.');
            }
        }
    }

    /** Leva as peças que o cliente ficou para o caixa. */
    public function toCashier(ConsignmentService $service)
    {
        Gate::authorize('pdv.vender');
        $consignment = $this->consignment();

        if ($consignment->status !== Consignment::AWAITING_SALE) {
            return null;
        }

        session()->put('pos.consignment', [
            'consignment_id' => $consignment->id,
            'customer_id' => $consignment->customer_id,
            'cart' => $service->cartFor($consignment),
        ]);

        return $this->redirectRoute('pos');
    }

    public function closeWithoutSale(ConsignmentService $service): void
    {
        Gate::authorize('condicional.acertar');
        $this->attempt(fn () => $service->closeWithoutSale($this->consignment(), Auth::user()), 'Condicional encerrada sem venda. As peças voltaram a ficar disponíveis.');
    }

    public function render(): View
    {
        $consignment = $this->consignment();
        $total = $consignment->items->sum(fn ($i) => Money::toCents($i->unit_price) * $i->quantity);
        $reminder = "Olá, {$consignment->customer->name}! As peças da condicional da ".config('loja.store.name')
            ." estavam com devolução prevista para {$consignment->due_date->format('d/m')}. Pode passar na loja para o acerto?";

        return view('livewire.sales.consignment-show', [
            'consignment' => $consignment,
            'total' => $total,
            'whatsapp' => $consignment->isOverdue() ? WhatsApp::url($consignment->customer->whatsappNumber(), $reminder) : null,
        ])->title('Condicional #'.$consignment->number());
    }
}
