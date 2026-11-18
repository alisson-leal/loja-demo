<?php

namespace Tests\Feature;

use App\Exceptions\BusinessRuleException;
use App\Livewire\Users\Form;
use App\Livewire\Users\Index;
use App\Models\User;
use App\Services\PlanService;
use App\Services\StoreService;
use App\Services\UserService;
use App\Support\PermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PlanLimitsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        config(['loja.plan.users_per_store' => 2, 'loja.plan.max_stores' => 2]);
        $this->owner = $this->admin();
    }

    private function newUser(string $email, array $roles = ['Vendedor']): User
    {
        return app(UserService::class)->create($this->owner, [
            'name' => 'Pessoa '.$email, 'email' => $email, 'phone' => null,
            'password' => 'provisoria123', 'roles' => $roles, 'permissions' => [],
        ]);
    }

    public function test_quota_is_users_per_store_times_active_stores_and_admins_do_not_count(): void
    {
        $this->newUser('a@example.com');
        $this->newUser('b@example.com');
        $this->newUser('chefe@example.com', [PermissionCatalog::ADMIN_ROLE]);

        $this->assertSame(['per_store' => 2, 'max' => 2, 'used' => 2, 'available' => 0], app(PlanService::class)->users());

        try {
            $this->newUser('c@example.com');
            $this->fail('Deveria bloquear o terceiro usuário.');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString('Seu plano permite 2 usuários ativos', $e->getMessage());
        }

        // Uma filial nova amplia a cota.
        app(StoreService::class)->save(null, ['name' => 'Filial', 'code' => 'FIL', 'phone' => null, 'address' => null, 'city' => null, 'is_active' => true], $this->owner);
        $this->newUser('c@example.com');
        $this->assertSame(3, app(PlanService::class)->users()['used']);
    }

    public function test_blocked_users_free_a_slot_and_unblocking_respects_the_limit(): void
    {
        $first = $this->newUser('a@example.com');
        $this->newUser('b@example.com');

        app(UserService::class)->block($this->owner, $first, 'Saiu da empresa');
        $this->newUser('c@example.com');

        $this->expectException(BusinessRuleException::class);
        app(UserService::class)->unblock($this->owner, $first->fresh());
    }

    public function test_removing_admin_role_counts_against_the_quota(): void
    {
        $this->newUser('a@example.com');
        $this->newUser('b@example.com');
        $other = $this->newUser('chefe@example.com', [PermissionCatalog::ADMIN_ROLE]);

        $this->expectException(BusinessRuleException::class);
        app(UserService::class)->update($this->owner, $other, [
            'name' => $other->name, 'email' => $other->email, 'phone' => null, 'password' => null,
            'roles' => ['Gerente'], 'permissions' => [],
        ]);
    }

    public function test_screens_show_usage_and_form_shows_the_error(): void
    {
        $this->newUser('a@example.com');
        $this->newUser('b@example.com');

        Livewire::actingAs($this->owner)->test(Index::class)
            ->assertSeeHtml('<strong>2</strong> de <strong>2</strong> usuários do plano')
            ->assertSee('Limite do plano atingido');

        Livewire::actingAs($this->owner)->test(Form::class)
            ->set('name', 'Maria Teste')->set('email', 'maria@example.com')
            ->set('password', 'provisoria123')->set('password_confirmation', 'provisoria123')
            ->set('roles', ['Vendedor'])->call('save')
            ->assertSee('Seu plano permite 2 usuários ativos');
        $this->assertFalse(User::where('email', 'maria@example.com')->exists());
    }

    public function test_zero_means_no_user_limit(): void
    {
        config(['loja.plan.users_per_store' => 0]);

        foreach (range(1, 4) as $i) {
            $this->newUser("u{$i}@example.com");
        }

        $this->assertNull(app(PlanService::class)->users()['max']);
        Livewire::actingAs($this->owner)->test(Index::class)->assertDontSee('usuários do plano');
    }
}
