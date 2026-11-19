<?php

namespace App\Livewire\Stock;

use App\Enums\MovementType;
use App\Livewire\Concerns\HandlesBusinessRules;
use App\Models\ProductVariant;
use App\Models\SerialNumber;
use App\Services\SerialService;
use App\Services\StockService;
use App\Support\CurrentStore;
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

    /** Números de série das unidades movimentadas (produtos com controle de série). */
    public string $serials = '';

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
        $this->serials = '';
        $this->resetValidation();
    }

    #[On('serial-picked')]
    public function selectSerial(int $id, string $serial): void
    {
        $this->variantId = $id;
        $this->serials = $serial;
        $this->resetValidation();
    }

    public function clearVariant(): void
    {
        $this->variantId = null;
        $this->serials = '';
    }

    public function save(StockService $stock): void
    {
        $tracked = $this->variantId && ProductVariant::with('product')->find($this->variantId)?->product->tracks_serial;

        if ($tracked) {
            $this->quantity = (string) SerialService::count($this->serials);
        }

        $validated = $this->validate([
            'variantId' => ['required', 'integer'],
            'type' => ['required', Rule::enum(MovementType::class)->only(MovementType::manualTypes())],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ], [
            'variantId.required' => 'Selecione o produto.',
            'quantity.min' => $tracked ? 'Informe os números de série das unidades.' : 'A quantidade deve ser maior que zero.',
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
            serials: $tracked ? SerialService::parse($this->serials) : [],
        ), 'Movimentação registrada.');

        if ($done) {
            $this->reset('type', 'quantity', 'reason', 'allowNegative', 'serials');
        }
    }

    public function render(): View
    {
        $variant = $this->variantId ? ProductVariant::with(['product', 'values.attribute'])->find($this->variantId) : null;
        $tracked = (bool) $variant?->product->tracks_serial;

        return view('livewire.stock.movement', [
            'variant' => $variant,
            'tracked' => $tracked,
            'serialCount' => SerialService::count($this->serials),
            'inStock' => $tracked
                ? SerialNumber::where('product_variant_id', $variant->id)->where('status', SerialNumber::IN_STOCK)
                    ->where('store_id', CurrentStore::id())->orderBy('serial')->limit(50)->pluck('serial')
                : collect(),
            'types' => collect(MovementType::manualTypes())->filter(fn (MovementType $type) => Gate::allows($type->permission())),
            'canGoNegative' => Gate::allows('estoque.negativo') && ! $tracked,
        ]);
    }
}
