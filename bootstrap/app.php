<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\RequirePasswordChange;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(SecurityHeaders::class);

        // No grupo web, para valer também nas requisições internas do Livewire.
        $middleware->web(append: [
            EnsureUserIsActive::class,
            RequirePasswordChange::class,
        ]);

        // Descadastro com um clique (RFC 8058): o provedor de e-mail envia o POST sem token.
        $middleware->validateCsrfTokens(except: ['descadastrar/*']);

        $middleware->redirectGuestsTo('/entrar');
        $middleware->redirectUsersTo('/');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
