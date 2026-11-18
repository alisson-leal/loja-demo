<?php

namespace App\Models;

use App\Support\PermissionCatalog;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

// Campos de segurança (bloqueio, troca obrigatória de senha) ficam fora do
// $fillable de propósito: só mudam pelos serviços, com forceFill.
#[Fillable(['name', 'email', 'phone', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_blocked' => 'boolean',
            'blocked_at' => 'datetime',
            'must_change_password' => 'boolean',
            'password_changed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'commission_percent' => 'decimal:2',
        ];
    }

    public const DEMO_DOMAIN = 'lojademo.test';

    public function isAdministrator(): bool
    {
        return $this->hasRole(PermissionCatalog::ADMIN_ROLE);
    }

    /** Usuários de teste da demonstração: compartilhados por todos os visitantes, não podem ser alterados. */
    public function isDemoAccount(): bool
    {
        return config('loja.demo.enabled') && str_ends_with((string) $this->email, '@'.self::DEMO_DOMAIN);
    }

    /** Administradores só podem ser gerenciados por outros administradores. */
    public function canBeManagedBy(self $actor): bool
    {
        return $actor->isAdministrator() || ! $this->isAdministrator();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_blocked', false);
    }
}
