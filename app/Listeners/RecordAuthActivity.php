<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Events\Dispatcher;

class RecordAuthActivity
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function onLogin(Login $event): void
    {
        /** @var User $user */
        $user = $event->user;

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
        ])->saveQuietly();

        $this->audit->record('auth.login', "{$user->name} entrou no sistema.", $user, userId: $user->id);
    }

    public function onLogout(Logout $event): void
    {
        if ($event->user) {
            $this->audit->record('auth.logout', "{$event->user->name} saiu do sistema.", $event->user, userId: $event->user->id);
        }
    }

    public function onFailed(Failed $event): void
    {
        $email = (string) ($event->credentials['email'] ?? '');

        $this->audit->record('auth.failed', "Tentativa de login recusada para {$email}.", $event->user, [
            'email' => $email,
        ], $event->user?->id);
    }

    public function onLockout(Lockout $event): void
    {
        $email = (string) $event->request->input('email');

        $this->audit->record('auth.lockout', "Login temporariamente bloqueado por excesso de tentativas: {$email}.", properties: [
            'email' => $email,
        ]);
    }

    public function onPasswordReset(PasswordReset $event): void
    {
        $this->audit->record('auth.password_reset', "{$event->user->name} redefiniu a senha por e-mail.", $event->user, userId: $event->user->id);
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'onLogin',
            Logout::class => 'onLogout',
            Failed::class => 'onFailed',
            Lockout::class => 'onLockout',
            PasswordReset::class => 'onPasswordReset',
        ];
    }
}
