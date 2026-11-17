<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Confere as credenciais de quem autoriza uma operação no caixa de outro operador.
 */
class SupervisorAuthorization
{
    private const MAX_ATTEMPTS = 5;

    public function __construct(private readonly AuditLogger $audit) {}

    public function verify(User $actor, string $email, string $password, string $permission): User
    {
        $key = "supervisor-auth:{$actor->id}";

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw new BusinessRuleException('Muitas tentativas de autorização. Tente novamente em '.RateLimiter::availableIn($key).' segundos.');
        }

        $supervisor = User::active()->where('email', Str::lower(trim($email)))->first();

        if (! $supervisor || ! Hash::check($password, $supervisor->password) || ! $supervisor->can($permission)) {
            RateLimiter::hit($key, 60);

            $this->audit->record('supervisor.denied', "Autorização recusada para {$actor->name} ({$permission}).", null, [
                'email_informado' => $email,
            ]);

            throw new BusinessRuleException('Autorização recusada. Confira o e-mail e a senha de quem tem esta permissão.');
        }

        RateLimiter::clear($key);

        return $supervisor;
    }
}
