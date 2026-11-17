<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/entrar');
    }

    public function test_user_logs_in_and_access_is_recorded(): void
    {
        $user = User::factory()->create();

        $this->post('/entrar', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('activity_logs', ['event' => 'auth.login', 'user_id' => $user->id]);
    }

    public function test_wrong_password_is_rejected_and_recorded_without_the_password(): void
    {
        $user = User::factory()->create();

        $this->post('/entrar', ['email' => $user->email, 'password' => 'senha-errada-123'])->assertSessionHasErrors('email');

        $this->assertGuest();
        $log = ActivityLog::where('event', 'auth.failed')->sole();
        $this->assertStringNotContainsString('senha-errada-123', json_encode($log->getAttributes()));
    }

    public function test_login_is_locked_after_too_many_attempts(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 5) as $ignored) {
            $this->post('/entrar', ['email' => $user->email, 'password' => 'errada']);
        }

        $this->post('/entrar', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseHas('activity_logs', ['event' => 'auth.lockout']);
    }

    public function test_blocked_user_cannot_log_in(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['is_blocked' => true])->save();

        $this->post('/entrar', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => __('auth.blocked')]);

        $this->assertGuest();
    }

    public function test_user_blocked_during_session_is_logged_out_on_next_request(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/')->assertOk();

        $user->forceFill(['is_blocked' => true])->save();

        $this->get('/')->assertRedirect('/entrar');
        $this->assertGuest();
    }

    public function test_provisional_password_forces_change_before_anything_else(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['must_change_password' => true])->save();

        $this->actingAs($user)->get('/')->assertRedirect('/minha-senha');
        $this->get('/minha-senha')->assertOk();
    }

    public function test_reset_link_response_does_not_reveal_whether_email_exists(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $known = $this->post('/esqueci-a-senha', ['email' => $user->email]);
        $unknown = $this->post('/esqueci-a-senha', ['email' => 'ninguem@example.com']);

        $this->assertSame($known->getSession()->get('success'), $unknown->getSession()->get('success'));
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->post('/redefinir-senha', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'novaSenha123',
            'password_confirmation' => 'novaSenha123',
        ])->assertRedirect('/entrar');

        $this->post('/entrar', ['email' => $user->email, 'password' => 'novaSenha123']);
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('activity_logs', ['event' => 'auth.password_reset', 'user_id' => $user->id]);
    }

    public function test_invalid_reset_token_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->post('/redefinir-senha', [
            'token' => 'token-invalido',
            'email' => $user->email,
            'password' => 'novaSenha123',
            'password_confirmation' => 'novaSenha123',
        ])->assertSessionHasErrors('email');

        $this->assertFalse(password_verify('novaSenha123', $user->fresh()->password));
    }

    public function test_security_headers_are_sent(): void
    {
        $this->get('/entrar')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }
}
