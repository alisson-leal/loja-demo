<?php

namespace App\Livewire\Stock;

use App\Enums\MovementType;
use App\Livewire\Concerns\HandlesBusinessRules;
use App\Models\ProductVariant;
use App\Services\StockService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Movimentar estoque')]
class Movement extends Component
{
    use HandlesBusinessRules;

    #[Locked]
    public ?int $variantId = null;

    public string $type = '';

    public string $quantity = '1';

    public string $reason = '';

    public bool $allowNegative = false;

    public function mount(): void
    {
        abort_unless(Gate::any(['estoque.movimentar', 'estoque.ajustar']), 403);

        $requested = request()->integer('variante');

        if ($requested && ProductVariant::operational()->whereKey($requested)->exists()) {
            $this->variantId = $requested;
        }
    }

    #[On('variant-picked')]
    public function selectVariant(int $id): void
    {
        $this->variantId = $id;
        $this->resetValidation();
    }

    public function clearVariant(): void
    {
        $this->variantId = null;
    }

    public function save(StockService $stock): void
    {
        $validated = $this->validate([
            'variantId' => ['required', 'integer'],
            'type' => ['required', Rule::enum(MovementType::class)->only(MovementType::manualTypes())],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ], [
            'variantId.required' => 'Selecione o produto.',
        ], ['type' => 'tipo', 'quantity' => 'quantidade', 'reason' => 'motivo']);

        $type = MovementType::from($validated['type']);
        Gate::authorize($type->permission());

        $variant = ProductVariant::operational()->findOrFail($this->variantId);

        $done = $this->attempt(fn () => $stock->register(
            $variant,
            $type,
            (int) $validated['quantity'],
            $validated['reason'],
            Auth::user(),
            allowNegative: $this->allowNegative,
        ), 'Movimentação registrada.');

        if ($done) {
            $this->reset('type', 'quantity', 'reason', 'allowNegative');
        }
    }

    public function render(): View
    {
        return view('livewire.stock.movement', [
            'variant' => $this->variantId ? ProductVariant::with(['product', 'values.attribute'])->find($this->variantId) : null,
            'types' => collect(MovementType::manualTypes())->filter(fn (MovementType $type) => Gate::allows($type->permission())),
            'canGoNegative' => Gate::allows('estoque.negativo'),
        ]);
    }
}
