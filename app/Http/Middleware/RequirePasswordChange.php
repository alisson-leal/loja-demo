<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enquanto a senha provisória não for trocada, só a tela de troca fica acessível.
 */
class RequirePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::user()?->must_change_password
            && ! $request->routeIs('account.password', 'logout')
            && ! Livewire::isLivewireRequest()) {
            return redirect()->route('account.password');
        }

        return $next($request);
    }
}
