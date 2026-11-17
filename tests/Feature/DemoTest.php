<?php

namespace Tests\Feature;

use App\Livewire\Account\ChangePassword;
use App\Models\CashSession;
use App\Models\Customer;
use App\Models\ProductVariant;
use App\Models\Receivable;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\WhatsApp;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class DemoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['loja.demo.enabled' => true]);
    }

    private function seedDemo(): void
    {
        app(DemoDataSeeder::class, ['months' => 1])->run();
    }

    public function test_demo_data_is_consistent_and_seeded_only_once(): void
    {
        $this->seedDemo();

        $sales = Sale::count();
        $this->assertGreaterThan(50, $sales);
        $this->assertGreaterThan(150, Customer::count());
        $this->assertSame(0, ProductVariant::where('stock_on_hand', '<', 0)->count());
        $this->assertSame(0, CashSession::where('status', CashSession::OPEN)->count());

        foreach (ProductVariant::inRandomOrder()->limit(20)->get() as $variant) {
            $this->assertSame((int) $variant->stock_on_hand, (int) StockMovement::where('product_variant_id', $variant->id)->sum('quantity'));
        }

        $payments = DB::table('sale_payments')->join('payment_methods', 'payment_methods.id', '=', 'sale_payments.payment_method_id')
            ->where('payment_methods.type', '!=', 'store_credit')->count();
        $this->assertSame($payments, (int) Receivable::selectRaw('COUNT(DISTINCT sale_payment_id) n')->value('n'));
        $this->assertTrue(Sale::where('created_at', '>=', today())->doesntExist(), 'Os dados vão só até ontem.');

        $this->seedDemo();
        $this->assertSame($sales, Sale::count());
    }

    public function test_login_page_lists_demo_accounts_and_they_work(): void
    {
        $this->seedDemo();

        $this->get('/entrar')->assertOk()->assertSee('Acesse a demonstração')->assertSee('gerente@lojademo.test');

        $this->post('/entrar', ['email' => 'gerente@lojademo.test', 'password' => config('loja.demo.password')])->assertRedirect('/');
        $this->get('/')->assertOk()->assertSee('Ambiente de demonstração');
    }

    public function test_demo_accounts_cannot_be_changed_and_whatsapp_has_no_recipient(): void
    {
        $this->seedDemo();
        $manager = User::where('email', 'gerente@lojademo.test')->sole();

        $this->actingAs($manager);
        Livewire::test(ChangePassword::class)
            ->set('current_password', config('loja.demo.password'))
            ->set('password', 'NovaSenha123')
            ->set('password_confirmation', 'NovaSenha123')
            ->call('save')
            ->assertHasErrors('password');
        $this->assertTrue(password_verify(config('loja.demo.password'), $manager->fresh()->password));

        $this->assertSame('https://wa.me/?text=Oi', WhatsApp::url('86999990000', 'Oi'));
    }
}
