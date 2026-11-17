<?php

namespace Tests\Feature;

use App\Exceptions\BusinessRuleException;
use App\Livewire\Account\ChangePassword;
use App\Livewire\Users\Form;
use App\Livewire\Users\Index;
use App\Models\User;
use App\Services\UserService;
use App\Support\PermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Maria Teste',
            'email' => 'maria@example.com',
            'phone' => null,
            'password' => 'provisoria123',
            'roles' => ['Vendedor'],
            'permissions' => [],
        ], $overrides);
    }

    public function test_server_command_resets_password_and_forces_change(): void
    {
        $user = User::factory()->create(['email' => 'dona@example.com', 'password' => 'antiga12345']);

        $this->artisan('usuarios:redefinir-senha', ['email' => 'nao-existe@example.com'])
            ->expectsOutputToContain('dona@example.com')
            ->assertExitCode(1);

        $this->artisan('usuarios:redefinir-senha', ['email' => ' DONA@example.com '])->assertExitCode(0);

        $user->refresh();
        $this->assertFalse(Hash::check('antiga12345', $user->password));
        $this->assertTrue($user->must_change_password);
        $this->assertDatabaseHas('activity_logs', ['event' => 'user.password_reset_cli', 'subject_id' => $user->id]);
    }

    public function test_user_list_requires_permission(): void
    {
        $this->actingAs(User::factory()->create())->get('/usuarios')->assertForbidden();
        $this->actingAs($this->userWith(['usuarios.ver']))->get('/usuarios')->assertOk();
    }

    public function test_admin_creates_user_with_provisional_password_through_the_form(): void
    {
        Livewire::actingAs($this->admin())->test(Form::class)
            ->set('name', 'Maria Teste')
            ->set('email', 'MARIA@example.com')
            ->set('password', 'provisoria123')
            ->set('password_confirmation', 'provisoria123')
            ->set('roles', ['Vendedor'])
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('users.index'));

        $user = User::where('email', 'maria@example.com')->sole();
        $this->assertTrue($user->must_change_password);
        $this->assertTrue($user->hasRole('Vendedor'));
        $this->assertTrue(Hash::check('provisoria123', $user->password));
        $this->assertDatabaseHas('activity_logs', ['event' => 'user.created', 'subject_id' => $user->id]);
    }

    public function test_form_validates_required_fields_and_unique_email(): void
    {
        $existing = User::factory()->create();

        Livewire::actingAs($this->admin())->test(Form::class)
            ->set('name', '')
            ->set('email', $existing->email)
            ->set('password', 'curta')
            ->call('save')
            ->assertHasErrors(['name', 'email', 'password', 'roles']);
    }

    public function test_non_admin_cannot_grant_permissions_they_do_not_have(): void
    {
        $actor = $this->userWith(['usuarios.criar']);

        $this->expectException(BusinessRuleException::class);
        app(UserService::class)->create($actor, $this->payload([
            'roles' => ['Gerente'],
        ]));
    }

    public function test_non_admin_cannot_grant_administrator_role(): void
    {
        $actor = $this->userWith(['usuarios.criar']);

        $this->expectException(BusinessRuleException::class);
        app(UserService::class)->create($actor, $this->payload(['roles' => [PermissionCatalog::ADMIN_ROLE]]));
    }

    public function test_non_admin_cannot_edit_an_administrator(): void
    {
        $actor = $this->userWith(['usuarios.editar']);
        $admin = $this->admin();

        Livewire::actingAs($actor)->test(Form::class, ['user' => $admin->id])->assertForbidden();
    }

    public function test_user_cannot_change_own_access(): void
    {
        $admin = $this->admin();
        $this->admin();

        $this->expectException(BusinessRuleException::class);
        app(UserService::class)->update($admin, $admin, $this->payload([
            'name' => $admin->name, 'email' => $admin->email, 'password' => null, 'roles' => ['Vendedor'],
        ]));
    }

    public function test_last_active_administrator_cannot_be_demoted(): void
    {
        $actor = $this->admin();
        $target = $this->admin();
        $actor->forceFill(['is_blocked' => true])->save();

        $this->expectException(BusinessRuleException::class);
        app(UserService::class)->update($actor, $target, $this->payload([
            'name' => $target->name, 'email' => $target->email, 'password' => null, 'roles' => ['Vendedor'],
        ]));
    }

    public function test_blocking_records_reason_and_ends_sessions(): void
    {
        $actor = $this->admin();
        $target = User::factory()->create();
        DB::table('sessions')->insert([
            'id' => 'sessao-alvo', 'user_id' => $target->id, 'payload' => '', 'last_activity' => time(),
        ]);

        Livewire::actingAs($actor)->test(Index::class)
            ->call('askBlock', $target->id)
            ->set('blockReason', 'Desligamento')
            ->call('confirmBlock')
            ->assertHasNoErrors();

        $target->refresh();
        $this->assertTrue($target->is_blocked);
        $this->assertSame('Desligamento', $target->blocked_reason);
        $this->assertSame($actor->id, $target->blocked_by);
        $this->assertDatabaseMissing('sessions', ['id' => 'sessao-alvo']);
        $this->assertDatabaseHas('activity_logs', ['event' => 'user.blocked', 'subject_id' => $target->id]);
    }

    public function test_block_requires_reason_and_permission(): void
    {
        $target = User::factory()->create();

        Livewire::actingAs($this->admin())->test(Index::class)
            ->call('askBlock', $target->id)
            ->call('confirmBlock')
            ->assertHasErrors('blockReason');

        Livewire::actingAs($this->userWith(['usuarios.ver']))->test(Index::class)
            ->call('askBlock', $target->id)
            ->assertForbidden();
    }

    public function test_user_cannot_block_self(): void
    {
        $admin = $this->admin();
        $this->admin();

        $this->expectException(BusinessRuleException::class);
        app(UserService::class)->block($admin, $admin, 'Motivo');
    }

    public function test_changing_password_clears_the_flag_and_ends_other_sessions(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['must_change_password' => true])->save();
        DB::table('sessions')->insert([
            'id' => 'outra-sessao', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time(),
        ]);

        Livewire::actingAs($user)->test(ChangePassword::class)
            ->set('current_password', 'password')
            ->set('password', 'novaSenha123')
            ->set('password_confirmation', 'novaSenha123')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('novaSenha123', $user->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'outra-sessao']);
    }

    public function test_changing_password_requires_correct_current_password(): void
    {
        Livewire::actingAs(User::factory()->create())->test(ChangePassword::class)
            ->set('current_password', 'errada')
            ->set('password', 'novaSenha123')
            ->set('password_confirmation', 'novaSenha123')
            ->call('save')
            ->assertHasErrors('current_password');
    }

    public function test_default_roles_exist(): void
    {
        foreach ([PermissionCatalog::ADMIN_ROLE, 'Gerente', 'Vendedor', 'Estoquista'] as $name) {
            $this->assertTrue(Role::where('name', $name)->exists(), $name);
        }
    }
}
