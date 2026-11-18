<?php

namespace Tests\Feature;

use App\Support\Help;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class HelpTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_help_lists_only_topics_the_profile_can_use(): void
    {
        $seller = $this->userWith(['pdv.vender', 'caixa.operar']);

        $this->actingAs($seller)->get(route('help.index'))->assertOk()
            ->assertSee('Frente de caixa (PDV)')
            ->assertSee('Meu caixa e histórico de caixas')
            ->assertSee('Alterar senha')
            ->assertDontSee('Contas a pagar')
            ->assertDontSee('Perfis de acesso')
            ->assertDontSee('Resultado (lucro ou prejuízo)');

        $this->actingAs($seller)->get(route('help.show', 'frente-de-caixa'))->assertOk()->assertSee('Finalizar venda');
        $this->actingAs($seller)->get(route('help.show', 'contas-a-pagar'))->assertNotFound();
        $this->actingAs($seller)->get(route('help.show', 'nao-existe'))->assertNotFound();
    }

    public function test_steps_follow_the_permissions_of_the_user(): void
    {
        $viewer = $this->userWith(['financeiro.ver']);
        $payer = $this->userWith(['financeiro.ver', 'financeiro.pagar']);

        $this->actingAs($viewer)->get(route('help.show', 'contas-a-pagar'))->assertOk()->assertDontSee('Nova conta');
        $this->actingAs($payer)->get(route('help.show', 'contas-a-pagar'))->assertOk()->assertSee('Nova conta');
    }

    public function test_every_screen_brings_the_help_of_that_screen_for_f1(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('finance.payables'))->assertOk()
            ->assertSee('id="help-panel"', false)
            ->assertSee('Contas a pagar')
            ->assertSee('Pagar com data de');

        $this->actingAs($this->userWith([]))->get(route('dashboard'))->assertOk()
            ->assertSee('id="help-panel"', false)
            ->assertSee('Resumo do movimento da loja');
    }

    public function test_store_transfers_help_only_with_more_than_one_store(): void
    {
        $user = $this->userWith(['estoque.ver', 'estoque.transferir']);

        $this->actingAs($user)->get(route('help.index'))->assertDontSee('Transferências entre lojas');
        $this->assertNull(Help::find('transferencias', $user));
    }

    public function test_help_topics_point_to_existing_routes_and_permissions(): void
    {
        $known = collect(config('loja.modules'))->flatMap(fn ($module, $key) => collect(array_keys($module['operations']))->map(fn ($op) => "{$key}.{$op}"));

        foreach (Help::topics() as $slug => $topic) {
            $perms = collect($topic['can'])->merge(collect($topic['steps'])->filter(fn ($step) => is_array($step))->flatMap(fn ($step) => $step['can']));
            foreach ($perms as $permission) {
                $this->assertTrue($known->contains($permission), "{$slug}: permissão {$permission} não existe.");
            }

            foreach ($topic['routes'] as $route) {
                $this->assertTrue(Str::contains($route, '*') || Route::has($route), "{$slug}: rota {$route} não existe.");
            }

            $this->assertContains($topic['section'], config('ajuda.sections'), "{$slug}: seção desconhecida.");
        }
    }

    public function test_help_text_is_escaped_and_bold_markup_works(): void
    {
        $this->assertSame('<strong>Salvar</strong> &lt;b&gt;', (string) Help::format('**Salvar** <b>'));
    }
}
