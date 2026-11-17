<?php

namespace Tests\Feature;

use App\Exceptions\BusinessRuleException;
use App\Livewire\Audit\Index as AuditIndex;
use App\Livewire\Dashboard;
use App\Livewire\Roles\Form;
use App\Livewire\Roles\Index;
use App\Livewire\Sessions\Index as SessionIndex;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\RoleService;
use App\Support\PermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use LogicException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolesAndAuditTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_custom_role_grants_only_its_permissions(): void
    {
        Livewire::actingAs($this->admin())->test(Form::class)
            ->set('name', 'Caixa Chefe')
            ->set('permissions', ['usuarios.ver'])
            ->call('save')
            ->assertHasNoErrors();

        $user = User::factory()->create()->assignRole('Caixa Chefe');

        $this->assertTrue($user->can('usuarios.ver'));
        $this->assertFalse($user->can('usuarios.criar'));
    }

    public function test_unknown_permission_is_rejected(): void
    {
        Livewire::actingAs($this->admin())->test(Form::class)
            ->set('name', 'Perfil X')
            ->set('permissions', ['nao.existe'])
            ->call('save')
            ->assertHasErrors('permissions.0');
    }

    public function test_administrator_role_cannot_be_edited_or_deleted(): void
    {
        $role = Role::findByName(PermissionCatalog::ADMIN_ROLE);
        $admin = $this->admin();

        Livewire::actingAs($admin)->test(Form::class, ['role' => $role->id])->assertForbidden();

        $this->expectException(BusinessRuleException::class);
        app(RoleService::class)->delete($role);
    }

    public function test_role_in_use_cannot_be_deleted(): void
    {
        $role = Role::create(['name' => 'Temporario', 'guard_name' => 'web']);
        User::factory()->create()->assignRole($role);

        Livewire::actingAs($this->admin())->test(Index::class)->call('delete', $role->id);

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_non_admin_cannot_add_permission_they_lack_to_a_role(): void
    {
        $actor = $this->userWith(['perfis.editar']);
        $role = Role::findByName('Vendedor');

        $this->expectException(BusinessRuleException::class);
        app(RoleService::class)->update($actor, $role, 'Vendedor', ['usuarios.criar']);
    }

    public function test_audit_records_cannot_be_changed_or_deleted(): void
    {
        $log = app(AuditLogger::class)->record('auth.login', 'Teste');

        try {
            $log->update(['description' => 'Alterado']);
            $this->fail('A alteração deveria ser impedida.');
        } catch (LogicException) {
            $this->assertSame('Teste', $log->fresh()->description);
        }

        $this->expectException(LogicException::class);
        $log->delete();
    }

    public function test_audit_page_requires_permission_and_filters_by_event(): void
    {
        $this->actingAs(User::factory()->create())->get('/auditoria')->assertForbidden();

        app(AuditLogger::class)->record('user.created', 'Evento de cadastro');
        app(AuditLogger::class)->record('role.created', 'Evento de perfil');

        Livewire::actingAs($this->userWith(['auditoria.ver']))->test(AuditIndex::class)
            ->set('event', 'user.created')
            ->assertSee('Evento de cadastro')
            ->assertDontSee('Evento de perfil');
    }

    public function test_dashboard_shows_real_totals_only_with_permission(): void
    {
        $viewer = $this->userWith(['dashboard.ver']);
        app(AuditLogger::class)->record('auth.login', 'Login de teste', userId: $viewer->id);

        Livewire::actingAs($viewer)->test(Dashboard::class)
            ->assertViewHas('allowed', true)
            ->assertViewHas('totals', fn ($totals) => $totals['logins'] === 1)
            ->assertViewHas('chart', fn ($chart) => array_sum($chart['values']) === 1 && count($chart['values']) === 30)
            ->assertViewHas('recent', null);

        Livewire::actingAs(User::factory()->create())->test(Dashboard::class)
            ->assertViewHas('allowed', false);
    }

    public function test_dashboard_falls_back_on_invalid_range(): void
    {
        Livewire::actingAs($this->userWith(['dashboard.ver']))->test(Dashboard::class)
            ->set('from', '2026-12-31')
            ->set('to', '2026-01-01')
            ->assertSee('Exibindo os últimos 30 dias');
    }

    public function test_user_can_only_end_sessions_they_are_allowed_to(): void
    {
        $owner = User::factory()->create();
        DB::table('sessions')->insert([
            'id' => 'sessao-de-outro', 'user_id' => $owner->id, 'payload' => '', 'last_activity' => time(),
        ]);

        Livewire::actingAs(User::factory()->create())->test(SessionIndex::class)->call('revoke', 'sessao-de-outro');
        $this->assertDatabaseHas('sessions', ['id' => 'sessao-de-outro']);

        Livewire::actingAs($this->userWith(['sessoes.encerrar']))->test(SessionIndex::class)->call('revoke', 'sessao-de-outro');
        $this->assertDatabaseMissing('sessions', ['id' => 'sessao-de-outro']);
        $this->assertSame(1, ActivityLog::where('event', 'session.revoked')->count());
    }
}
