<?php

namespace Tests\Feature;

use App\Livewire\Customers\Form as CustomerForm;
use App\Livewire\Marketing\Automations;
use App\Livewire\Marketing\CampaignForm;
use App\Livewire\Marketing\CampaignShow;
use App\Livewire\Marketing\WhatsappQueue;
use App\Mail\MarketingMail;
use App\Models\Customer;
use App\Models\CustomerConsent;
use App\Models\CustomerSegment;
use App\Models\MarketingAutomation;
use App\Models\MarketingCampaign;
use App\Models\MarketingMessage;
use App\Models\User;
use App\Services\BirthdayAutomation;
use App\Services\CampaignService;
use App\Services\ConsentService;
use App\Services\CustomerService;
use App\Services\MarketingEmailSender;
use App\Support\MessageTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

class MarketingTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = $this->userWith(['marketing.ver', 'marketing.campanhas', 'marketing.disparar', 'marketing.whatsapp', 'marketing.automacoes', 'clientes.ver', 'clientes.criar', 'clientes.editar']);
    }

    private function customer(array $attributes = [], array $consents = []): Customer
    {
        $customer = Customer::create($attributes + ['name' => 'Ana Souza', 'whatsapp' => '86999990000', 'email' => 'ana@example.com']);
        app(ConsentService::class)->setMany($customer, $consents, 'cadastro');

        return $customer->fresh();
    }

    public function test_consent_is_recorded_with_history_and_history_is_immutable(): void
    {
        $this->actingAs($this->manager);

        Livewire::test(CustomerForm::class)
            ->set('name', 'Beatriz Lima')
            ->set('whatsapp', '(86) 99999-1111')
            ->set('whatsapp_opt_in', true)
            ->set('email_opt_in', true)
            ->call('save')
            ->assertHasErrors(['email']);

        Livewire::test(CustomerForm::class)
            ->set('name', 'Beatriz Lima')
            ->set('whatsapp', '(86) 99999-1111')
            ->set('whatsapp_opt_in', true)
            ->call('save')
            ->assertHasNoErrors();

        $customer = Customer::where('name', 'Beatriz Lima')->sole();
        $this->assertTrue($customer->whatsapp_opt_in);
        $this->assertFalse($customer->email_opt_in);
        $this->assertDatabaseHas('customer_consents', ['customer_id' => $customer->id, 'channel' => 'whatsapp', 'granted' => true, 'source' => 'cadastro', 'user_id' => $this->manager->id]);

        // Salvar sem mudar o consentimento não cria nova linha no histórico.
        Livewire::test(CustomerForm::class, ['customer' => $customer->id])->set('notes', 'Prefere tons pastel')->call('save');
        $this->assertSame(1, CustomerConsent::where('customer_id', $customer->id)->count());

        Livewire::test(CustomerForm::class, ['customer' => $customer->id])->set('whatsapp_opt_in', false)->call('save');
        $this->assertDatabaseHas('customer_consents', ['customer_id' => $customer->id, 'channel' => 'whatsapp', 'granted' => false]);

        $this->expectException(LogicException::class);
        CustomerConsent::first()->update(['granted' => false]);
    }

    public function test_pos_quick_create_grants_consent_only_for_informed_contacts(): void
    {
        $customer = app(CustomerService::class)->quickCreate('Carla Dias', '86988887777', null, true);

        $this->assertTrue($customer->whatsapp_opt_in);
        $this->assertFalse($customer->email_opt_in);
        $this->assertDatabaseHas('customer_consents', ['customer_id' => $customer->id, 'channel' => 'whatsapp', 'source' => 'pdv']);

        $without = app(CustomerService::class)->quickCreate('Duda Reis', '86988886666', 'duda@example.com');
        $this->assertFalse($without->whatsapp_opt_in);
        $this->assertSame(0, CustomerConsent::where('customer_id', $without->id)->count());
    }

    public function test_campaign_reaches_only_consenting_customers_of_the_segment(): void
    {
        $this->actingAs($this->manager);
        $teresina = $this->customer(['name' => 'Ana Souza', 'city' => 'Teresina'], ['whatsapp' => true, 'email' => true]);
        $this->customer(['name' => 'Bia Sem Consentimento', 'city' => 'Teresina']);
        $this->customer(['name' => 'Cris Sem Telefone', 'city' => 'Teresina', 'whatsapp' => null, 'phone' => null], ['email' => true]);
        $this->customer(['name' => 'Dani de Parnaíba', 'city' => 'Parnaíba'], ['whatsapp' => true]);
        $segment = CustomerSegment::create(['name' => 'Teresina', 'filters' => ['city' => 'Teresina'], 'created_by' => $this->manager->id]);

        Livewire::test(CampaignForm::class)
            ->set('name', 'Liquida')
            ->set('channel', 'whatsapp')
            ->set('segment', (string) $segment->id)
            ->set('body', 'Oi {primeiro_nome}, a liquida da {loja} começou!')
            ->assertSee('Oi Ana, a liquida da Loja Demo começou!')
            ->assertSeeHtml('<strong id="audience-count">1</strong>')
            ->call('save')
            ->assertRedirect();

        $campaign = MarketingCampaign::sole();
        $this->assertSame('Teresina', $campaign->segment_name);

        Livewire::test(CampaignShow::class, ['campaign' => $campaign->id])->call('launch');

        $message = MarketingMessage::sole();
        $this->assertSame($teresina->id, $message->customer_id);
        $this->assertSame('5586999990000', $message->destination);
        $this->assertStringStartsWith('Oi Ana, a liquida da Loja Demo começou!', $message->body);
        $this->assertStringEndsWith(MessageTemplate::WHATSAPP_OPT_OUT, $message->body);
        $this->assertSame(MarketingCampaign::SENDING, $campaign->fresh()->status);

        // Uma campanha iniciada não pode ser editada nem iniciada de novo.
        $this->get(route('marketing.campaigns.edit', $campaign))->assertForbidden();
        Livewire::test(CampaignShow::class, ['campaign' => $campaign->id])->call('launch');
        $this->assertSame(1, MarketingMessage::count());
    }

    public function test_launch_without_audience_is_refused(): void
    {
        $this->actingAs($this->manager);
        $this->customer();
        $campaign = app(CampaignService::class)->save(null, [
            'name' => 'Vazia', 'channel' => 'email', 'segment_name' => null, 'filters' => [], 'subject' => 'Oi', 'body' => 'Olá!',
        ], $this->manager);

        Livewire::test(CampaignShow::class, ['campaign' => $campaign->id])->call('launch')->assertSee('Nenhum cliente deste público autorizou');
        $this->assertTrue($campaign->fresh()->isDraft());
    }

    public function test_emails_respect_hourly_limit_and_finish_the_campaign(): void
    {
        Mail::fake();
        config(['loja.marketing.email_per_hour' => 120]);
        $this->actingAs($this->manager);

        foreach (range(1, 3) as $i) {
            $this->customer(['name' => "Cliente {$i}", 'email' => "c{$i}@example.com"], ['email' => true]);
        }

        $campaign = app(CampaignService::class)->save(null, [
            'name' => 'Novidades', 'channel' => 'email', 'segment_name' => null, 'filters' => [], 'subject' => 'Novidades, {primeiro_nome}', 'body' => 'Chegou coleção nova.',
        ], $this->manager);
        app(CampaignService::class)->launch($campaign, $this->manager);

        // 120 por hora = 2 por minuto.
        $this->assertSame(2, app(MarketingEmailSender::class)->sendBatch()['sent']);
        Mail::assertSent(MarketingMail::class, fn (MarketingMail $mail) => $mail->hasTo('c1@example.com')
            && $mail->mailSubject === 'Novidades, Cliente'
            && str_contains($mail->unsubscribeUrl, '/descadastrar/'));

        $this->assertSame(1, app(MarketingEmailSender::class)->sendBatch()['sent']);
        $this->assertSame(MarketingCampaign::FINISHED, $campaign->fresh()->status);

        // Limite da hora esgotado: nada sai até a janela andar.
        config(['loja.marketing.email_per_hour' => 3]);
        $this->customer(['name' => 'Cliente 4', 'email' => 'c4@example.com'], ['email' => true]);
        MarketingMessage::create(['customer_id' => Customer::where('email', 'c4@example.com')->value('id'), 'channel' => 'email', 'destination' => 'c4@example.com', 'subject' => 'Oi', 'body' => 'Oi', 'dedupe_key' => 'teste:4']);
        $this->assertSame(0, app(MarketingEmailSender::class)->sendBatch()['sent']);
    }

    public function test_paused_campaign_does_not_send_and_revoked_consent_is_skipped(): void
    {
        Mail::fake();
        $this->actingAs($this->manager);
        $ana = $this->customer([], ['email' => true]);
        $bia = $this->customer(['name' => 'Bia', 'email' => 'bia@example.com'], ['email' => true]);
        $campaign = app(CampaignService::class)->save(null, [
            'name' => 'Teste', 'channel' => 'email', 'segment_name' => null, 'filters' => [], 'subject' => 'Oi', 'body' => 'Olá!',
        ], $this->manager);
        app(CampaignService::class)->launch($campaign, $this->manager);

        app(CampaignService::class)->pause($campaign);
        $this->assertSame(0, app(MarketingEmailSender::class)->sendBatch()['sent']);

        app(ConsentService::class)->set($bia, 'email', false, 'cadastro');
        $this->assertSame(MarketingMessage::SKIPPED, MarketingMessage::where('customer_id', $bia->id)->value('status'));

        app(CampaignService::class)->resume($campaign);
        app(MarketingEmailSender::class)->sendBatch();
        Mail::assertSent(MarketingMail::class, 1);
        Mail::assertSent(MarketingMail::class, fn ($mail) => $mail->hasTo($ana->email));
    }

    public function test_unsubscribe_link_requires_signature_and_revokes_on_post(): void
    {
        $customer = $this->customer([], ['email' => true]);
        $url = URL::signedRoute('marketing.unsubscribe', ['customer' => $customer->id]);

        $this->get(route('marketing.unsubscribe', ['customer' => $customer->id]))->assertForbidden();

        $this->get($url)->assertOk()->assertSee('Deixar de receber e-mails?');
        $this->assertTrue($customer->fresh()->email_opt_in, 'Abrir o link não pode descadastrar.');

        // POST sem token CSRF, como no descadastro com um clique dos provedores.
        $this->post($url)->assertOk()->assertSee('não receberá mais e-mails');
        $this->assertFalse($customer->fresh()->email_opt_in);
        $this->assertDatabaseHas('customer_consents', ['customer_id' => $customer->id, 'channel' => 'email', 'granted' => false, 'source' => 'descadastro']);
    }

    public function test_birthday_automation_creates_messages_once_for_customers_and_children(): void
    {
        Carbon::setTestNow('2026-03-10 09:00:00');
        MarketingAutomation::query()->update(['enabled' => true]);

        $mother = $this->customer(['name' => 'Maria Alves', 'birth_date' => '1990-03-10'], ['whatsapp' => true, 'email' => true]);
        $mother->children()->create(['name' => 'Lucas Alves', 'birth_date' => '2020-03-17']);
        $this->customer(['name' => 'Sem Consentimento', 'birth_date' => '1985-03-10']);

        $this->assertSame(4, app(BirthdayAutomation::class)->run());
        $this->assertSame(0, app(BirthdayAutomation::class)->run(), 'Rodar de novo no mesmo dia não duplica.');

        $childMessage = MarketingMessage::where('automation', 'aniversario_filho')->where('channel', 'whatsapp')->sole();
        $this->assertStringContainsString('O aniversário de Lucas está chegando (6 anos em 17/03)', $childMessage->body);
        $this->assertSame('2026-03-13', $childMessage->expires_at->toDateString());
        $this->assertSame('Feliz aniversário, Maria!', MarketingMessage::where('automation', 'aniversario_cliente')->where('channel', 'email')->value('subject'));

        Carbon::setTestNow('2026-03-14 09:00:00');
        $this->assertSame(2, app(BirthdayAutomation::class)->expireStale());
        Carbon::setTestNow();
    }

    public function test_leap_day_birthday_is_celebrated_on_feb_28(): void
    {
        Carbon::setTestNow('2027-02-28 09:00:00');
        MarketingAutomation::where('key', 'aniversario_cliente')->update(['enabled' => true]);
        $this->customer(['name' => 'Leo', 'birth_date' => '2000-02-29'], ['whatsapp' => true]);

        $this->assertSame(1, app(BirthdayAutomation::class)->run());
        Carbon::setTestNow();
    }

    public function test_whatsapp_queue_marks_sent_and_registers_opt_out(): void
    {
        $seller = $this->userWith(['marketing.whatsapp']);
        $this->actingAs($this->manager);
        $ana = $this->customer([], ['whatsapp' => true]);
        $bia = $this->customer(['name' => 'Bia', 'whatsapp' => '86988880000'], ['whatsapp' => true]);
        $campaign = app(CampaignService::class)->save(null, [
            'name' => 'Dia das crianças', 'channel' => 'whatsapp', 'segment_name' => null, 'filters' => [], 'subject' => null, 'body' => 'Oi {primeiro_nome}!',
        ], $this->manager);
        app(CampaignService::class)->launch($campaign, $this->manager);

        $this->actingAs($seller);
        $this->get(route('marketing.campaigns.index'))->assertForbidden();

        $component = Livewire::test(WhatsappQueue::class)
            ->assertSee('2 mensagens pendentes')
            ->assertSeeHtml('https://wa.me/5586999990000?text=');

        $component->call('markSent', MarketingMessage::where('customer_id', $ana->id)->value('id'));
        $component->call('optOut', MarketingMessage::where('customer_id', $bia->id)->value('id'))->assertSee('Nenhuma mensagem para enviar agora.');

        $this->assertDatabaseHas('marketing_messages', ['customer_id' => $ana->id, 'status' => MarketingMessage::SENT, 'handled_by' => $seller->id]);
        $this->assertFalse($bia->fresh()->whatsapp_opt_in);
        $this->assertDatabaseHas('customer_consents', ['customer_id' => $bia->id, 'granted' => false, 'source' => 'pedido_cliente']);
        $this->assertSame(MarketingCampaign::FINISHED, $campaign->fresh()->status);

        // Mensagem já tratada não é tratada de novo.
        $component->call('markSent', MarketingMessage::where('customer_id', $ana->id)->value('id'))->assertSee('já foi tratada');
    }

    public function test_automation_settings_are_validated_and_saved(): void
    {
        $this->actingAs($this->manager);

        Livewire::test(Automations::class)
            ->set('forms.aniversario_filho.days_before', 40)
            ->call('save', 'aniversario_filho')
            ->assertHasErrors(['forms.aniversario_filho.days_before'])
            ->set('forms.aniversario_filho.days_before', 10)
            ->set('forms.aniversario_filho.enabled', true)
            ->call('save', 'aniversario_filho')
            ->assertHasNoErrors();

        $this->assertTrue(MarketingAutomation::where('key', 'aniversario_filho')->value('enabled'));
        $this->assertSame(10, MarketingAutomation::where('key', 'aniversario_filho')->value('days_before'));
        $this->get(route('marketing.automations'))->assertOk();
    }

    public function test_marketing_pages_require_permissions(): void
    {
        $this->actingAs(User::factory()->create());

        foreach (['marketing.campaigns.index', 'marketing.campaigns.create', 'marketing.whatsapp', 'marketing.automations'] as $route) {
            $this->get(route($route))->assertForbidden();
        }

        $this->actingAs($this->manager);

        foreach (['marketing.campaigns.index', 'marketing.campaigns.create', 'marketing.whatsapp', 'marketing.automations'] as $route) {
            $this->get(route($route))->assertOk();
        }
    }
}
