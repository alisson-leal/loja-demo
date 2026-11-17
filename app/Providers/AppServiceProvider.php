<?php

namespace App\Providers;

use App\Listeners\RecordAuthActivity;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // Na demonstração os e-mails dos clientes fictícios nunca saem do servidor.
        if (config('loja.demo.enabled')) {
            config(['mail.default' => 'log']);
        }

        if ($proxies = config('loja.trusted_proxies')) {
            TrustProxies::at($proxies === '*' ? '*' : explode(',', $proxies));
        }

        if ($this->app->isProduction() && config('loja.force_https')) {
            URL::forceScheme('https');
        }

        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        // O perfil Administrador tem acesso total, sem depender da lista de permissões.
        Gate::before(fn (User $user) => $user->isAdministrator() ? true : null);

        Event::subscribe(RecordAuthActivity::class);

        ResetPassword::toMailUsing(function (User $user, string $token) {
            $url = route('password.reset', ['token' => $token, 'email' => $user->email]);

            return (new MailMessage)
                ->subject('Redefinição de senha - '.config('app.name'))
                ->greeting("Olá, {$user->name}!")
                ->line('Recebemos um pedido para redefinir a senha da sua conta.')
                ->action('Redefinir senha', $url)
                ->line('Este link expira em '.config('auth.passwords.users.expire').' minutos.')
                ->line('Se você não fez este pedido, ignore este e-mail.')
                ->salutation('Equipe '.config('app.name'));
        });
    }
}
