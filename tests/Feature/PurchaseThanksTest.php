<?php

namespace Tests\Feature;

use App\Livewire\Marketing\Automations;
use App\Livewire\Marketing\WhatsappQueue;
use App\Models\Category;
use App\Models\Customer;
use App\Models\MarketingAutomation;
use App\Models\MarketingMessage;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\User;
use App\Services\CashService;
use App\Services\ConsentService;
use App\Services\SaleService;
use App\Support\MessageTemplate;
use Database\Seeders\PaymentMethodsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class PurchaseThanksTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private User $operator;

    private ProductVariant $variant;

    private PaymentMethod $pix;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PaymentMethodsSeeder::class);
        $this->pix = PaymentMethod::where('type', 'pix')->sole();
        $this->operator = $this->userWith(['pdv.vender', 'caixa.operar', 'vendas.cancelar', 'marketing.whatsapp', 'marketing.automacoes']);
        app(CashService::class)->open($this->operator, 0);

        $category = Category::create(['name' => 'Conjuntos']);
        $product = Product::create(['name' => 'Conjunto', 'category_id' => $category->id, 'sale_price' => '89.90']);
        $this->variant = new ProductVariant(['product_id' => $product->id, 'sku' => 'CJ', 'sale_price' => '89.90', 'cost_price' => '30.00']);
        $this->variant->stock_on_hand = 20;
        $this->variant->save();
    }

    private function customer(array $consents): Customer
    {
        $customer = Customer::create(['name' => 'Karla Leal', 'whatsapp' => '86988277095', 'email' => 'karla@example.com']);
        app(ConsentService::class)->setMany($customer, $consents, 'cadastro');

        return $customer;
    }

    private function sell(?Customer $customer): Sale
    {
        return app(SaleService::class)->finalize(
            $this->operator,
            [['variant_id' => $this->variant->id, 'quantity' => 1, 'discount' => 0]],
            [['method_id' => $this->pix->id, 'amount' => 8990]],
            $customer?->id,
            0,
            null,
            (string) Str::uuid(),
        );
    }

    public function test_sale_with_consenting_customer_queues_whatsapp_thanks_once(): void
    {
        MarketingAutomation::where('key', MarketingAutomation::PURCHASE_THANKS)
            ->update(['whatsapp_body' => 'Oi {primeiro_nome}, obrigado pela compra {numero_venda} de {valor_compra}!']);
        $customer = $this->customer(['whatsapp' => true, 'email' => true]);

        $sale = $this->sell($customer);

        // Por padrão só o WhatsApp está ligado, mesmo com consentimento de e-mail.
        $message = MarketingMessage::sole();
        $this->assertSame('whatsapp', $message->channel);
        $this->assertSame(MarketingAutomation::PURCHASE_THANKS, $message->automation);
        $this->assertSame('5586988277095', $message->destination);
        $this->assertStringStartsWith("Oi Karla, obrigado pela compra {$sale->number()} de R$ 89,90!", $message->body);
        $this->assertStringEndsWith(MessageTemplate::WHATSAPP_OPT_OUT, $message->body);
        $this->assertNotNull($message->expires_at);

        $this->actingAs($this->operator);
        Livewire::test(WhatsappQueue::class)->assertSee('Agradecimento pela compra')->assertSee('Karla Leal');
    }

    public function test_no_thanks_without_customer_consent_or_when_disabled(): void
    {
        $this->sell(null);
        $this->sell($this->customer([]));
        $this->assertSame(0, MarketingMessage::count());

        MarketingAutomation::where('key', MarketingAutomation::PURCHASE_THANKS)->update(['enabled' => false]);
        $this->sell($this->customer(['whatsapp' => true]));
        $this->assertSame(0, MarketingMessage::count());
    }

    public function test_email_channel_can_be_enabled_and_cancelled_sale_discards_pending_thanks(): void
    {
        MarketingAutomation::where('key', MarketingAutomation::PURCHASE_THANKS)->update(['send_email' => true]);
        $customer = $this->customer(['whatsapp' => true, 'email' => true]);

        $sale = $this->sell($customer);
        $this->assertSame(['email', 'whatsapp'], MarketingMessage::orderBy('channel')->pluck('channel')->all());
        $this->assertSame('Obrigado pela sua compra, Karla!', MarketingMessage::where('channel', 'email')->value('subject'));

        app(SaleService::class)->cancel($this->operator, $sale, 'Desistiu');
        $this->assertSame(2, MarketingMessage::where('status', MarketingMessage::CANCELLED)->count());
    }

    public function test_settings_require_a_channel_and_birthdays_ignore_thanks(): void
    {
        $this->actingAs($this->operator);

        Livewire::test(Automations::class)
            ->assertSeeInOrder(['Agradecimento pela compra', 'Aniversário do cliente'])
            ->set('forms.agradecimento_compra.send_whatsapp', false)
            ->call('save', 'agradecimento_compra')
            ->assertHasErrors('forms.agradecimento_compra.send_whatsapp')
            ->set('forms.agradecimento_compra.send_email', true)
            ->call('save', 'agradecimento_compra')
            ->assertHasNoErrors();

        $automation = MarketingAutomation::where('key', MarketingAutomation::PURCHASE_THANKS)->sole();
        $this->assertFalse($automation->send_whatsapp);
        $this->assertSame(['email'], $automation->channels());
    }
}
