<?php

namespace App\Livewire\Customers;

use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerSegment;
use App\Services\AuditLogger;
use App\Support\CustomerFilter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Clientes')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'f')]
    public array $filters = CustomerFilter::DEFAULTS;

    public string $segmentName = '';

    public function mount(): void
    {
        Gate::authorize('clientes.ver');
        $this->filters = CustomerFilter::normalize($this->filters);
    }

    public function updatedFilters(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->filters = CustomerFilter::DEFAULTS;
        $this->resetPage();
    }

    public function applySegment(int $id): void
    {
        $this->filters = CustomerFilter::normalize(CustomerSegment::findOrFail($id)->filters);
        $this->resetPage();
    }

    public function saveSegment(AuditLogger $audit): void
    {
        Gate::authorize('clientes.editar');

        $this->segmentName = trim($this->segmentName);
        $this->validate([
            'segmentName' => ['required', 'string', 'min:2', 'max:80', Rule::unique('customer_segments', 'name')],
        ], [], ['segmentName' => 'nome do segmento']);

        $segment = CustomerSegment::create([
            'name' => $this->segmentName,
            'filters' => CustomerFilter::normalize($this->filters),
            'created_by' => Auth::id(),
        ]);

        $audit->record('customer.segment_saved', "Segmento {$segment->name} salvo.", $segment);
        session()->now('success', 'Segmento salvo.');
        $this->reset('segmentName');
    }

    public function deleteSegment(int $id, AuditLogger $audit): void
    {
        Gate::authorize('clientes.editar');

        $segment = CustomerSegment::findOrFail($id);
        $audit->record('customer.segment_deleted', "Segmento {$segment->name} excluído.");
        $segment->delete();
        session()->now('success', 'Segmento excluído.');
    }

    public function render(): View
    {
        $filters = CustomerFilter::normalize($this->filters);

        return view('livewire.customers.index', [
            'customers' => CustomerFilter::apply(Customer::query()->withStats()->with('children'), $filters)->orderBy('name')->paginate(20),
            'segments' => CustomerSegment::orderBy('name')->get(),
            'categories' => Category::ordered(),
            'exportQuery' => array_filter($filters, fn ($value) => $value !== ''),
            'inactiveDays' => (int) config('loja.customer_inactive_days'),
        ]);
    }
}
