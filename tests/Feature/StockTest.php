<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Exceptions\BusinessRuleException;
use App\Livewire\Stock\Balance;
use App\Livewire\Stock\Entry;
use App\Livewire\Stock\InventoryCount;
use App\Livewire\Stock\Movement;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\StockReservation;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VariantCostHistory;
use App\Services\InventoryService;
use App\Services\StockEntryService;
use App\Services\StockReservationService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

class StockTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function variant(int $onHand = 0, array $attributes = []): ProductVariant
    {
        static $sequence = 0;
        $sequence++;

        $category = Category::create(['name' => "Categoria {$sequence}"]);
        $product = Product::create(['name' => "Produto {$sequence}", 'category_id' => $category->id, 'sale_price' => 50]);

        $variant = new ProductVariant(array_merge([
            'product_id' => $product->id,
            'sku' => "SKU-{$sequence}",
            'cost_price' => 20,
            'sale_price' => 50,
        ], $attributes));
        $variant->stock_on_hand = $onHand;
        $variant->save();

        return $variant;
    }

    private function stock(): StockService
    {
        return app(StockService::class);
    }

    public function test_entry_increases_balance_and_records_who_when_and_why(): void
    {
        $variant = $this->variant(2);
        $user = User::factory()->create();

        $this->stock()->register($variant, MovementType::Entry, 3, 'Reposição', $user);

        $this->assertSame(5, $variant->fresh()->stock_on_hand);
        $this->assertDatabaseHas('stock_movements', [
            'product_variant_id' => $variant->id,
            'type' => 'entry',
            'quantity' => 3,
            'balance_after' => 5,
            'reason' => 'Reposição',
            'user_id' => $user->id,
        ]);
    }

    public function test_exit_beyond_available_is_refused_and_changes_nothing(): void
    {
        $variant = $this->variant(2);

        try {
            $this->stock()->register($variant, MovementType::Exit, 3, 'Saída', User::factory()->create());
            $this->fail('Deveria recusar.');
        } catch (BusinessRuleException) {
            $this->assertSame(2, $variant->fresh()->stock_on_hand);
            $this->assertSame(0, StockMovement::count());
        }
    }

    public function test_negative_balance_requires_both_permission_and_explicit_authorization(): void
    {
        $variant = $this->variant(1);
        $withPermission = $this->userWith(['estoque.negativo']);

        try {
            $this->stock()->register($variant, MovementType::Exit, 5, 'Sem autorizar', $withPermission);
            $this->fail('Sem marcar a autorização deveria recusar.');
        } catch (BusinessRuleException) {
            $this->assertSame(1, $variant->fresh()->stock_on_hand);
        }

        try {
            $this->stock()->register($variant, MovementType::Exit, 5, 'Sem permissão', User::factory()->create(), allowNegative: true);
            $this->fail('Sem permissão deveria recusar.');
        } catch (BusinessRuleException) {
            $this->assertSame(1, $variant->fresh()->stock_on_hand);
        }

        $this->stock()->register($variant, MovementType::Exit, 5, 'Autorizado', $withPermission, allowNegative: true);
        $this->assertSame(-4, $variant->fresh()->stock_on_hand);
    }

    public function test_movements_and_cost_history_are_immutable(): void
    {
        $movement = $this->stock()->register($this->variant(), MovementType::Entry, 1, 'Teste', null);

        try {
            $movement->update(['quantity' => 99]);
            $this->fail('Deveria impedir a alteração.');
        } catch (LogicException) {
            $this->assertSame(1, $movement->fresh()->quantity);
        }

        $this->expectException(LogicException::class);
        $movement->delete();
    }

    public function test_batch_entry_updates_balance_cost_history_and_is_all_or_nothing(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::create(['name' => 'Fornecedor']);
        $first = $this->variant();
        $second = $this->variant();

        app(StockEntryService::class)->register($user, $supplier->id, 'NF-10', null, [
            ['variant_id' => $first->id, 'quantity' => 4, 'unit_cost' => '25.00'],
            ['variant_id' => $second->id, 'quantity' => 2, 'unit_cost' => '20.00'],
        ]);

        $this->assertSame(4, $first->fresh()->stock_on_hand);
        $this->assertSame('25.00', $first->fresh()->cost_price);
        $this->assertDatabaseHas('variant_cost_history', [
            'product_variant_id' => $first->id, 'new_cost' => '25.00', 'source' => VariantCostHistory::SOURCE_ENTRY, 'supplier_id' => $supplier->id,
        ]);
        $this->assertDatabaseMissing('variant_cost_history', ['product_variant_id' => $second->id]);

        try {
            app(StockEntryService::class)->register($user, null, null, null, [
                ['variant_id' => $first->id, 'quantity' => 1, 'unit_cost' => null],
                ['variant_id' => $second->id, 'quantity' => 0, 'unit_cost' => null],
            ]);
            $this->fail('Deveria recusar.');
        } catch (BusinessRuleException) {
            $this->assertSame(4, $first->fresh()->stock_on_hand);
        }
    }

    public function test_reservation_reduces_available_and_blocks_overselling(): void
    {
        $variant = $this->variant(3);
        $reservations = app(StockReservationService::class);

        $reservations->reserve($variant, 2, 'pedido:1');

        $this->assertSame(1, $variant->fresh()->available());
        $this->assertSame(3, $variant->fresh()->stock_on_hand);

        $this->expectException(BusinessRuleException::class);
        $reservations->reserve($variant, 2, 'pedido:2');
    }

    public function test_exit_cannot_consume_reserved_units(): void
    {
        $variant = $this->variant(3);
        app(StockReservationService::class)->reserve($variant, 2, 'pedido:1');

        $this->expectException(BusinessRuleException::class);
        $this->stock()->register($variant, MovementType::Exit, 2, 'Saída', User::factory()->create());
    }

    public function test_repeated_reserve_and_consume_do_not_duplicate_effects(): void
    {
        $variant = $this->variant(5);
        $reservations = app(StockReservationService::class);

        $first = $reservations->reserve($variant, 2, 'pedido:7');
        $second = $reservations->reserve($variant, 2, 'pedido:7');
        $this->assertTrue($first->is($second));
        $this->assertSame(2, $variant->fresh()->stock_reserved);

        $reservations->consume($first, 'Pedido 7');
        $reservations->consume($first, 'Pedido 7');

        $variant->refresh();
        $this->assertSame(3, $variant->stock_on_hand);
        $this->assertSame(0, $variant->stock_reserved);
        $this->assertSame(1, StockMovement::where('type', 'sale')->count());
    }

    public function test_expired_reservations_are_released_by_the_scheduled_command(): void
    {
        $variant = $this->variant(2);
        $reservations = app(StockReservationService::class);
        $expired = $reservations->reserve($variant, 1, 'pedido:1', now()->subMinute());
        $valid = $reservations->reserve($variant, 1, 'pedido:2', now()->addHour());

        Artisan::call('estoque:liberar-reservas');

        $this->assertSame(StockReservation::EXPIRED, $expired->fresh()->status);
        $this->assertSame(StockReservation::ACTIVE, $valid->fresh()->status);
        $this->assertSame(1, $variant->fresh()->stock_reserved);
    }

    public function test_inventory_adjusts_only_counted_items_and_records_difference(): void
    {
        $user = User::factory()->create();
        $service = app(InventoryService::class);
        $counted = $this->variant(10);
        $matching = $this->variant(4);
        $uncounted = $this->variant(7);

        $inventory = $service->open($user, null, null);
        $service->setCount($inventory->items()->where('product_variant_id', $counted->id)->first(), 8);
        $service->setCount($inventory->items()->where('product_variant_id', $matching->id)->first(), 4);

        $this->assertSame(1, $service->finish($user, $inventory));

        $this->assertSame(8, $counted->fresh()->stock_on_hand);
        $this->assertSame(7, $uncounted->fresh()->stock_on_hand);
        $this->assertDatabaseHas('stock_movements', ['product_variant_id' => $counted->id, 'type' => 'inventory', 'quantity' => -2, 'balance_after' => 8]);
        $this->assertSame(Inventory::FINISHED, $inventory->fresh()->status);
        $this->assertSame(10, $inventory->items()->where('product_variant_id', $counted->id)->first()->expected_quantity);
    }

    public function test_only_one_inventory_can_be_open_and_finished_cannot_change(): void
    {
        $user = User::factory()->create();
        $service = app(InventoryService::class);
        $this->variant(1);
        $inventory = $service->open($user, null, null);

        try {
            $service->open($user, null, null);
            $this->fail('Deveria recusar o segundo inventário.');
        } catch (BusinessRuleException) {
            $service->cancel($user, $inventory);
        }

        $this->expectException(BusinessRuleException::class);
        $service->finish($user, $inventory->fresh());
    }

    public function test_barcode_scan_adds_one_unit_to_the_count(): void
    {
        $user = $this->userWith(['estoque.inventario']);
        $variant = $this->variant(0, ['barcode' => '7891234567895']);
        $inventory = app(InventoryService::class)->open($user, null, null);

        $component = Livewire::actingAs($user)->test(InventoryCount::class, ['inventory' => $inventory->id]);
        $component->set('code', '7891234567895')->call('scan');
        $component->set('code', '7891234567895')->call('scan');

        $this->assertSame(2, $inventory->items()->where('product_variant_id', $variant->id)->first()->counted_quantity);
    }

    public function test_movement_screen_enforces_permission_per_type(): void
    {
        $variant = $this->variant(5);
        $user = $this->userWith(['estoque.movimentar']);

        Livewire::actingAs($user)->test(Movement::class)
            ->dispatch('variant-picked', id: $variant->id)
            ->set('type', 'adjustment_out')
            ->set('quantity', '1')
            ->set('reason', 'Tentativa')
            ->call('save')
            ->assertForbidden();

        Livewire::actingAs($user)->test(Movement::class)
            ->dispatch('variant-picked', id: $variant->id)
            ->set('type', 'loss')
            ->set('quantity', '1')
            ->set('reason', 'Peça rasgada')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(4, $variant->fresh()->stock_on_hand);
    }

    public function test_movement_requires_reason_and_rejects_sale_type_from_screen(): void
    {
        $variant = $this->variant(5);

        Livewire::actingAs($this->admin())->test(Movement::class)
            ->dispatch('variant-picked', id: $variant->id)
            ->set('type', 'sale')
            ->set('quantity', '1')
            ->set('reason', '')
            ->call('save')
            ->assertHasErrors(['type', 'reason']);

        $this->assertSame(5, $variant->fresh()->stock_on_hand);
    }

    public function test_user_without_cost_permission_cannot_change_cost_through_entry(): void
    {
        $variant = $this->variant();
        $user = $this->userWith(['estoque.entrada']);

        Livewire::actingAs($user)->test(Entry::class)
            ->dispatch('variant-picked', id: $variant->id)
            ->set('items.0.unit_cost', '999')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, $variant->fresh()->stock_on_hand);
        $this->assertSame('20.00', $variant->fresh()->cost_price);
    }

    public function test_balance_filters_low_and_out_of_stock_and_counts_reservations(): void
    {
        $this->variant(5, ['min_stock' => 2, 'sku' => 'NORMAL']);
        $low = $this->variant(4, ['min_stock' => 3, 'sku' => 'BAIXO']);
        $this->variant(0, ['sku' => 'ZERADO']);
        app(StockReservationService::class)->reserve($low, 2, 'pedido:1');

        $user = $this->userWith(['estoque.ver']);

        Livewire::actingAs($user)->test(Balance::class)->set('situation', 'baixo')
            ->assertSee('BAIXO')->assertDontSee('NORMAL')->assertDontSee('ZERADO');
        Livewire::actingAs($user)->test(Balance::class)->set('situation', 'sem')
            ->assertSee('ZERADO')->assertDontSee('NORMAL');
    }

    public function test_inactive_products_are_left_out_of_stock_operations(): void
    {
        $variant = $this->variant(3);
        $variant->product->update(['is_active' => false]);

        $this->assertFalse(ProductVariant::operational()->whereKey($variant->id)->exists());
    }
}
