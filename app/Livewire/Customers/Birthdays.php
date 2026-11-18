<?php

namespace App\Livewire\Customers;

use App\Models\Customer;
use App\Models\CustomerChild;
use App\Support\StoreSegment;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Aniversariantes')]
class Birthdays extends Component
{
    public const MONTHS = [
        1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril', 5 => 'Maio', 6 => 'Junho',
        7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
    ];

    #[Url(as: 'mes')]
    public int $month = 0;

    #[Url(as: 'quem')]
    public string $who = 'todos';

    public function mount(): void
    {
        Gate::authorize('clientes.ver');

        if ($this->month < 1 || $this->month > 12) {
            $this->month = now()->month;
        }
    }

    public function render(): View
    {
        $month = max(1, min(12, $this->month));
        $rows = collect();
        $withChildren = StoreSegment::childrenEnabled();
        $who = $withChildren ? $this->who : 'clientes';

        if ($who !== 'filhos') {
            $rows = $rows->concat(Customer::whereMonth('birth_date', $month)->get()->map(fn (Customer $customer) => [
                'key' => "c{$customer->id}",
                'date' => $customer->birth_date,
                'name' => $customer->name,
                'kind' => 'Cliente',
                'customer' => $customer,
            ]));
        }

        if ($who !== 'clientes') {
            $rows = $rows->concat(CustomerChild::with('customer')->whereMonth('birth_date', $month)->get()->map(fn (CustomerChild $child) => [
                'key' => "f{$child->id}",
                'date' => $child->birth_date,
                'name' => $child->name,
                'kind' => 'Filho(a)',
                'customer' => $child->customer,
            ]));
        }

        return view('livewire.customers.birthdays', [
            'rows' => $rows->sortBy(fn (array $row) => [$row['date']->day, $row['name']])->values(),
            'months' => self::MONTHS,
            'year' => now()->year,
            'withChildren' => $withChildren,
        ]);
    }
}
