<?php

namespace App\Livewire\Concerns;

use App\Exceptions\BusinessRuleException;

trait HandlesBusinessRules
{
    /** Executa a ação e mostra o resultado na própria tela. Retorna se deu certo. */
    protected function attempt(callable $action, string $successMessage): bool
    {
        try {
            $action();
            session()->now('success', $successMessage);

            return true;
        } catch (BusinessRuleException $exception) {
            session()->now('error', $exception->getMessage());

            return false;
        }
    }
}
