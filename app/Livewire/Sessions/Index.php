<?php

namespace App\Livewire\Sessions;

use App\Exceptions\BusinessRuleException;
use App\Services\UserSessionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Sessões')]
class Index extends Component
{
    public function revoke(string $sessionId, UserSessionService $sessions): void
    {
        try {
            $sessions->revoke(Auth::user(), $sessionId, session()->getId());
            session()->now('success', 'Sessão encerrada.');
        } catch (BusinessRuleException $exception) {
            session()->now('error', $exception->getMessage());
        }
    }

    public function render(UserSessionService $sessions): View
    {
        $user = Auth::user();
        $seesAll = $user->can('sessoes.ver');

        return view('livewire.sessions.index', [
            'seesAll' => $seesAll,
            'canRevokeOthers' => $user->can('sessoes.encerrar'),
            'items' => $sessions->listActive($seesAll ? null : $user->id),
            'currentId' => session()->getId(),
        ]);
    }
}
