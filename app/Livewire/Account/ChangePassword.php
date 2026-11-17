<?php

namespace App\Livewire\Account;

use App\Services\AuditLogger;
use App\Services\UserSessionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Alterar senha')]
class ChangePassword extends Component
{
    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function save(AuditLogger $audit, UserSessionService $sessions): void
    {
        if (Auth::user()->isDemoAccount()) {
            $this->addError('password', 'Na demonstração, a senha dos usuários de teste não pode ser alterada.');

            return;
        }

        $this->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::defaults()],
        ]);

        $user = Auth::user();

        $user->forceFill([
            'password' => $this->password,
            'must_change_password' => false,
            'password_changed_at' => now(),
        ])->save();

        // Quem tinha a senha antiga deixa de ter acesso em outros dispositivos.
        $sessions->revokeAllFor($user->id, session()->getId());
        $audit->record('account.password_changed', "{$user->name} alterou a própria senha.", $user);

        session()->flash('success', 'Senha alterada com sucesso.');
        $this->redirectRoute('dashboard');
    }

    public function render(): View
    {
        return view('livewire.account.change-password', [
            'forced' => Auth::user()->must_change_password,
        ]);
    }
}
