<?php

namespace Tests\Feature;

use App\Exceptions\BusinessRuleException;
use App\Livewire\Customers\Birthdays;
use App\Livewire\Customers\Form;
use App\Livewire\Customers\Index;
use App\Livewire\Customers\Show as CustomerShow;
use App\Livewire\Dashboard;
use App\Livewire\Sales\ReturnForm;
use App\Livewire\Sales\Show as SaleShow;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerChild;
use App\Models\CustomerSegment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\User;
use App\Services\CashService;
use App\Services\SaleReturnService;
use App\Services\SaleService;
use App\Services\SupervisorAuthorization;
use Database\Seeders\PaymentMethodsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private User $operator;

    private PaymentMethod $pix;

    private ProductVariant $variant;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PaymentMethodsSeeder::class);
        $this->pix = PaymentMethod::where('type', 'pix')->sole();
        $this->operator = $this->userWith(['pdv.vender', 'caixa.operar']);
        app(CashService::class)->open($this->operator, 0);

        $this->category = Category::create(['name' => 'Roupas']);
        $product = Product::create(['name' => 'Camiseta', 'category_id' => $this->category->id, 'sale_price' => 100]);
        $this->variant = new ProductVariant(['product_id' => $product->id, 'sku' => 'CAM', 'sale_price' => 100, 'cost_price' => 40]);
        $this->variant->stock_on_hand = 100;
        $this->variant->save();
    }

    private function sell(?Customer $customer, int $quantity = 1): Sale
    {
        return app(SaleService::class)->finalize(
            $this->operator,
            [['variant_id' => $this->variant->id, 'quantity' => $quantity, 'discount' => 0]],
            [['method_id' => $this->pix->id, 'amount' => 10000 * $quantity]],
            $customer?->id,
            0,
            null,
            (string) Str::uuid(),
        );
    }

    private function viewer(): User
    {
        return $this->userWith(['clientes.ver', 'clientes.criar', 'clientes.editar', 'clientes.exportar']);
    }

    public function test_full_registration_with_children(): void
    {
        Livewire::actingAs($this->viewer())->test(Form::class)
            ->set('name', 'Maria Silva')
            ->set('cpf', '529.982.247-25')
            ->set('whatsapp', '(86) 99999-0000')
            ->set('birth_date', '1990-05-10')
            ->call('addChild')
            ->set('children.0.name', 'Ana')
            ->set('children.0.birth_date', now()->subYears(4)->toDateString())
            ->set('children.0.clothing_size', '4')
            ->call('save')
            ->assertHasNoErrors();

        $customer = Customer::sole();
        $this->assertSame('52998224725', $customer->cpf);
        $this->assertSame('86999990000', $customer->whatsapp);
        $this->assertSame('4 anos', $customer->children()->sole()->ageLabel());
    }

    public function test_invalid_or_duplicate_cpf_is_refused(): void
    {
        $viewer = $this->viewer();
        Customer::create(['name' => 'Existente', 'cpf' => '52998224725']);

        foreach (['111.111.111-11', '123.456.789-00', '529.982.247-25'] as $cpf) {
            Livewire::actingAs($viewer)->test(Form::class)
                ->set('name', 'Teste')
                ->set('cpf', $cpf)
                ->call('save')
                ->assertHasErrors('cpf');
        }
    }

    public function test_editing_keeps_updates_and_removes_children(): void
    {
        $customer = Customer::create(['name' => 'Maria']);
        $keep = $customer->children()->create(['name' => 'Ana']);
        $customer->children()->create(['name' => 'Remover']);

        Livewire::actingAs($this->viewer())->test(Form::class, ['customer' => $customer->id])
            ->call('removeChild', 1)
            ->set('children.0.name', 'Ana Clara')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Ana Clara', $customer->children()->sole()->name);
        $this->assertTrue($keep->is($customer->children()->sole()));
    }

    public function test_search_by_name_phone_cpf_and_email(): void
    {
        Customer::create(['name' => 'Joana Lima', 'phone' => '8633334444', 'cpf' => '52998224725', 'email' => 'joana@example.com']);
        Customer::create(['name' => 'Outra Pessoa']);

        foreach (['Joana', '(86) 3333-4444', '529.982', 'joana@'] as $term) {
            Livewire::actingAs($this->viewer())->test(Index::class)
                ->set('filters.search', $term)
                ->assertSee('Joana Lima')
                ->assertDontSee('Outra Pessoa');
        }
    }

    public function test_purchase_stats_exclude_cancelled_and_returned_values(): void
    {
        $customer = Customer::create(['name' => 'Maria']);
        $kept = $this->sell($customer, 2);
        $cancelled = $this->sell($customer);
        $this->operator->givePermissionTo('vendas.cancelar');
        app(SaleService::class)->cancel($this->operator, $cancelled, 'Teste');
        app(SaleReturnService::class)->register($this->operator, $kept, [
            ['sale_item_id' => $kept->items()->sole()->id, 'quantity' => 1, 'restock' => true],
        ], 'Teste', $this->pix->id);

        Livewire::actingAs($this->viewer())->test(CustomerShow::class, ['customer' => $customer->id])
            ->assertViewHas('customer', fn ($c) => $c->purchases_count === 1 && $c->total_spent === '100.00')
            ->assertViewHas('ticket', 10000)
            ->assertViewHas('products', fn ($products) => (int) $products->first()->quantity === 1);
    }

    public function test_segmentation_filters_and_status(): void
    {
        $active = Customer::create(['name' => 'Cliente Ativa', 'birth_date' => '1990-03-10', 'city' => 'Teresina']);
        $active->children()->create(['name' => 'Bia', 'birth_date' => now()->subYears(5)->toDateString(), 'clothing_size' => '6']);
        $this->sell($active, 3);

        $inactive = Customer::create(['name' => 'Cliente Inativa']);
        $old = $this->sell($inactive);
        Sale::whereKey($old->id)->update(['created_at' => now()->subDays(400)]);

        Customer::create(['name' => 'Cliente Nova']);

        $viewer = $this->viewer();
        $expectations = [
            ['status', 'ativos', 'Cliente Ativa'],
            ['status', 'inativos', 'Cliente Inativa'],
            ['status', 'sem_compras', 'Cliente Nova'],
            ['min_spent', '250', 'Cliente Ativa'],
            ['min_purchases', '1', null],
            ['idle_days', '300', 'Cliente Inativa'],
            ['birthday_month', '3', 'Cliente Ativa'],
            ['has_children', '1', 'Cliente Ativa'],
            ['child_size', '6', 'Cliente Ativa'],
            ['child_age_min', '5', 'Cliente Ativa'],
            ['category_id', (string) $this->category->id, null],
            ['city', 'Teresina', 'Cliente Ativa'],
        ];

        foreach ($expectations as [$field, $value, $only]) {
            $component = Livewire::actingAs($viewer)->test(Index::class)->set("filters.{$field}", $value);

            if ($only) {
                $component->assertViewHas('customers', fn ($page) => $page->total() === 1 && $page->first()->name === $only);
            } else {
                $component->assertViewHas('customers', fn ($page) => $page->total() === 2);
            }
        }

        Livewire::actingAs($viewer)->test(Index::class)->set('filters.child_age_max', '4')
            ->assertViewHas('customers', fn ($page) => $page->total() === 0);
    }

    public function test_segment_can_be_saved_applied_and_deleted(): void
    {
        $viewer = $this->viewer();
        Customer::create(['name' => 'Com Filho'])->children()->create(['name' => 'Bia']);
        Customer::create(['name' => 'Sem Filho']);

        Livewire::actingAs($viewer)->test(Index::class)
            ->set('filters.has_children', '1')
            ->set('segmentName', 'Famílias')
            ->call('saveSegment')
            ->assertHasNoErrors();

        $segment = CustomerSegment::sole();

        Livewire::actingAs($viewer)->test(Index::class)
            ->call('applySegment', $segment->id)
            ->assertViewHas('customers', fn ($page) => $page->total() === 1)
            ->call('deleteSegment', $segment->id);

        $this->assertSame(0, CustomerSegment::count());
    }

    public function test_export_requires_permission_omits_cpf_neutralizes_formulas_and_is_audited(): void
    {
        Customer::create(['name' => '=HYPERLINK("x")', 'cpf' => '52998224725', 'whatsapp' => '86999990000']);

        $this->actingAs($this->userWith(['clientes.ver']))->get('/clientes/exportar')->assertForbidden();

        $content = $this->actingAs($this->viewer())->get('/clientes/exportar')->assertOk()->streamedContent();

        $this->assertStringContainsString('86999990000', $content);
        $this->assertStringNotContainsString('52998224725', $content);
        $this->assertStringContainsString("'=HYPERLINK", $content);
        $this->assertDatabaseHas('activity_logs', ['event' => 'customer.exported']);
    }

    public function test_birthdays_list_customers_and_children_of_the_month(): void
    {
        $month = now()->addMonth()->month;
        $birth = now()->startOfMonth()->addMonth()->subYears(30);

        $parent = Customer::create(['name' => 'Mãe', 'birth_date' => $birth->toDateString()]);
        $parent->children()->create(['name' => 'Filha', 'birth_date' => $birth->copy()->addYears(25)->toDateString()]);
        Customer::create(['name' => 'Outro Mês', 'birth_date' => now()->startOfMonth()->addMonths(3)->subYears(30)->toDateString()]);

        Livewire::actingAs($this->viewer())->test(Birthdays::class)
            ->set('month', $month)
            ->assertSee('Mãe')->assertSee('Filha')->assertDontSee('Outro Mês')
            ->set('who', 'filhos')
            ->assertViewHas('rows', fn ($rows) => $rows->count() === 1 && $rows[0]['name'] === 'Filha');
    }

    public function test_customer_pages_require_permission(): void
    {
        $customer = Customer::create(['name' => 'Maria']);
        $seller = $this->userWith(['clientes.criar']);

        $this->actingAs($seller)->get('/clientes')->assertForbidden();
        $this->actingAs($seller)->get("/clientes/{$customer->id}")->assertForbidden();
        $this->actingAs($seller)->get("/clientes/{$customer->id}/editar")->assertForbidden();
        $this->actingAs($seller)->get('/clientes/novo')->assertOk();
    }

    public function test_dashboard_shows_customer_indicators(): void
    {
        Customer::create(['name' => 'Nova']);
        $old = Customer::create(['name' => 'Antiga']);
        Customer::whereKey($old->id)->update(['created_at' => now()->subYear()]);

        Livewire::actingAs($this->userWith(['dashboard.ver', 'clientes.ver']))->test(Dashboard::class)
            ->assertViewHas('customers', fn ($c) => $c['total'] === 2 && $c['new'] === 1);

        Livewire::actingAs($this->userWith(['dashboard.ver']))->test(Dashboard::class)->assertViewHas('customers', null);
    }

    public function test_manager_authorizes_cancellation_on_the_operators_screen(): void
    {
        $sale = $this->sell(null);
        $manager = $this->userWith(['vendas.cancelar'], ['email' => 'gerente@example.com']);

        Livewire::actingAs($this->operator)->test(SaleShow::class, ['sale' => $sale->id])
            ->set('cancelReason', 'Cliente desistiu')
            ->set('supervisorEmail', 'gerente@example.com')
            ->set('supervisorPassword', 'password')
            ->call('cancel')
            ->assertHasNoErrors()
            ->assertSet('supervisorPassword', '');

        $sale->refresh();
        $this->assertSame(Sale::CANCELLED, $sale->status);
        $this->assertSame($this->operator->id, $sale->cancelled_by);
        $this->assertSame($manager->id, $sale->cancel_authorized_by);
    }

    public function test_wrong_password_or_user_without_permission_cannot_authorize(): void
    {
        $sale = $this->sell(null);
        $this->userWith(['vendas.cancelar'], ['email' => 'gerente@example.com']);
        $this->userWith(['vendas.devolver'], ['email' => 'semcancelar@example.com']);

        foreach ([['gerente@example.com', 'senha-errada'], ['semcancelar@example.com', 'password']] as [$email, $password]) {
            Livewire::actingAs($this->operator)->test(SaleShow::class, ['sale' => $sale->id])
                ->set('cancelReason', 'Tentativa')
                ->set('supervisorEmail', $email)
                ->set('supervisorPassword', $password)
                ->call('cancel');

            $this->assertSame(Sale::COMPLETED, $sale->fresh()->status);
        }

        $this->assertDatabaseHas('activity_logs', ['event' => 'supervisor.denied']);
    }

    public function test_blocked_manager_cannot_authorize_and_attempts_are_limited(): void
    {
        $manager = $this->userWith(['vendas.cancelar'], ['email' => 'gerente@example.com']);
        $manager->forceFill(['is_blocked' => true])->save();
        $service = app(SupervisorAuthorization::class);

        foreach (range(1, 5) as $ignored) {
            try {
                $service->verify($this->operator, 'gerente@example.com', 'password', 'vendas.cancelar');
                $this->fail('Bloqueado não pode autorizar.');
            } catch (BusinessRuleException $exception) {
                $this->assertStringContainsString('recusada', $exception->getMessage());
            }
        }

        $manager->forceFill(['is_blocked' => false])->save();

        $this->expectExceptionMessage('Muitas tentativas');
        $service->verify($this->operator, 'gerente@example.com', 'password', 'vendas.cancelar');
    }

    public function test_manager_authorizes_return_and_operator_cannot_reach_others_sales(): void
    {
        $sale = $this->sell(null);
        $item = $sale->items()->sole();
        $manager = $this->userWith(['vendas.devolver'], ['email' => 'gerente@example.com']);

        Livewire::actingAs($this->operator)->test(ReturnForm::class, ['sale' => $sale->id])
            ->set("quantities.{$item->id}", '1')
            ->set('reason', 'Tamanho')
            ->call('save')
            ->assertHasErrors('supervisorEmail');

        Livewire::actingAs($this->operator)->test(ReturnForm::class, ['sale' => $sale->id])
            ->set("quantities.{$item->id}", '1')
            ->set('reason', 'Tamanho')
            ->set('supervisorEmail', 'gerente@example.com')
            ->set('supervisorPassword', 'password')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($manager->id, $sale->returns()->sole()->authorized_by);

        $other = $this->userWith(['pdv.vender']);
        $this->actingAs($other)->get(route('sales.return', $sale))->assertForbidden();
    }

    public function test_child_records_are_removed_with_the_customer_only(): void
    {
        $customer = Customer::create(['name' => 'Maria']);
        $customer->children()->create(['name' => 'Ana']);
        $customer->delete();

        $this->assertSame(0, CustomerChild::count());
    }
}
