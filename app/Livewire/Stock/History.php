<?php

namespace App\Livewire\Stock;

use App\Enums\MovementType;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Histórico de movimentações')]
class History extends Component
{
    use WithPagination;

    #[Url(as: 'busca')]
    public string $search = '';

    #[Url(as: 'tipo')]
    public string $type = '';

    #[Url(as: 'usuario')]
    public string $userId = '';

    #[Url(as: 'de')]
    public string $from = '';

    #[Url(as: 'ate')]
    public string $to = '';

    public function mount(): void
    {
        Gate::authorize('estoque.ver');
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $term = trim($this->search);
        $isDate = fn (string $value) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;

        $movements = StockMovement::with(['user:id,name', 'variant.product', 'variant.values.attribute'])
            ->when($term !== '', fn (Builder $query) => $query->whereHas(
                'variant',
                fn (Builder $variant) => $variant->search($term)
            ))
            ->when(MovementType::tryFrom($this->type), fn (Builder $query, MovementType $type) => $query->where('type', $type))
            ->when(ctype_digit($this->userId), fn (Builder $query) => $query->where('user_id', (int) $this->userId))
            ->when($isDate($this->from), fn (Builder $query) => $query->where('created_at', '>=', $this->from.' 00:00:00'))
            ->when($isDate($this->to), fn (Builder $query) => $query->where('created_at', '<=', $this->to.' 23:59:59'))
            ->latest('id')
            ->paginate(25);

        return view('livewire.stock.history', [
            'movements' => $movements,
            'types' => MovementType::cases(),
            'users' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
