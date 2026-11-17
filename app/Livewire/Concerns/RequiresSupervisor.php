<?php

namespace App\Livewire\Concerns;

use App\Models\User;
use App\Services\SupervisorAuthorization;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

trait RequiresSupervisor
{
    public string $supervisorEmail = '';

    public string $supervisorPassword = '';

    /** Retorna quem autorizou, ou null quando o próprio operador tem a permissão. */
    protected function supervisorFor(string $permission): ?User
    {
        if (Gate::allows($permission)) {
            return null;
        }

        $this->validate([
            'supervisorEmail' => ['required', 'email'],
            'supervisorPassword' => ['required', 'string'],
        ], [], ['supervisorEmail' => 'e-mail de quem autoriza', 'supervisorPassword' => 'senha de quem autoriza']);

        try {
            return app(SupervisorAuthorization::class)->verify(Auth::user(), $this->supervisorEmail, $this->supervisorPassword, $permission);
        } finally {
            $this->reset('supervisorPassword');
        }
    }
}
