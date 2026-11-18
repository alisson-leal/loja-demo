<?php

namespace App\Livewire\Customers;

use App\Models\Customer;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\CrediarioService;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Cliente')]
class Show extends Component
{
    use WithPagination;

    #[Locked]
    public int $customerId;

    public function mount($customer): void
    {
        Gate::authorize('clientes.ver');
        $this->customerId = Customer::findOrFail($customer)->id;
    }

    public function render(): View
    {
        $customer = Customer::withStats()->with('children')->findOrFail($this->customerId);
        $count = $customer->purchases_count;

        $first = Sale::where('customer_id', $customer->id)->where('status', Sale::COMPLETED)->min('created_at');
        $frequency = $count > 1
            ? (int) round($customer->last_purchase_at->diffInDays(Carbon::parse($first), true) / ($count - 1))
            : null;

        return view('livewire.customers.show', [
            'customer' => $customer,
            'crediario' => auth()->user()->canAny(['crediario.vender', 'crediario.receber', 'crediario.gerir'])
                ? app(CrediarioService::class)->position($customer)
                : null,
            'consents' => $customer->consents()->with('user:id,name')->limit(50)->get(),
            'ticket' => $count > 0 ? (int) round(Money::toCents($customer->total_spent) / $count) : 0,
            'frequency' => $frequency,
            'products' => SaleItem::query()
                ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                ->where('sales.customer_id', $customer->id)
                ->where('sales.status', Sale::COMPLETED)
                ->groupBy('sale_items.product_variant_id', 'sale_items.description')
                ->orderByDesc(DB::raw('MAX(sales.created_at)'))
                ->limit(30)
                ->selectRaw('sale_items.description as description, SUM(sale_items.quantity - sale_items.returned_quantity) as quantity, MAX(sales.created_at) as last_at')
                ->toBase()
                ->get(),
            'sales' => Sale::where('customer_id', $customer->id)->with('user:id,name')->latest('id')->paginate(10),
        ])->title($customer->name);
    }
}
