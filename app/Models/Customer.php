<?php

namespace App\Models;

use App\Support\CustomerFilter;
use App\Support\WhatsApp;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Customer extends Model
{
    public const GENDER_LABELS = [
        'feminino' => 'Feminino',
        'masculino' => 'Masculino',
        'outro' => 'Outro',
    ];

    /** Sem consentimento até o cliente autorizar. */
    protected $attributes = [
        'email_opt_in' => false,
        'whatsapp_opt_in' => false,
    ];

    protected $fillable = [
        'name', 'cpf', 'birth_date', 'gender', 'phone', 'whatsapp', 'email',
        'address', 'neighborhood', 'city', 'zip_code', 'notes', 'credit_limit',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'email_opt_in' => 'boolean',
            'email_opt_in_at' => 'datetime',
            'whatsapp_opt_in' => 'boolean',
            'whatsapp_opt_in_at' => 'datetime',
            'purchases_count' => 'integer',
            'total_spent' => 'decimal:2',
            'last_purchase_at' => 'datetime',
            'credit_limit' => 'decimal:2',
        ];
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function children(): HasMany
    {
        return $this->hasMany(CustomerChild::class)->orderBy('id');
    }

    public function consents(): HasMany
    {
        return $this->hasMany(CustomerConsent::class)->latest('id');
    }

    /** Número usado no WhatsApp: o campo próprio ou, na falta dele, o telefone. */
    public function whatsappNumber(): ?string
    {
        return $this->whatsapp ?: $this->phone ?: null;
    }

    /** Destino do canal, se o cliente consentiu e tem o contato. */
    public function marketingDestination(string $channel): ?string
    {
        return match ($channel) {
            'email' => $this->email_opt_in && filled($this->email) ? $this->email : null,
            'whatsapp' => $this->whatsapp_opt_in && WhatsApp::normalize($this->whatsappNumber()) ? WhatsApp::normalize($this->whatsappNumber()) : null,
            default => null,
        };
    }

    /** Clientes que consentiram no canal e têm o contato preenchido. */
    public function scopeReachableBy(Builder $query, string $channel): Builder
    {
        return match ($channel) {
            'email' => $query->where('email_opt_in', true)->whereNotNull('email')->where('email', '<>', ''),
            'whatsapp' => $query->where('whatsapp_opt_in', true)->where(fn (Builder $q) => $q
                ->where(fn (Builder $w) => $w->whereNotNull('whatsapp')->where('whatsapp', '<>', ''))
                ->orWhere(fn (Builder $p) => $p->whereNotNull('phone')->where('phone', '<>', ''))),
            default => $query->whereRaw('1 = 0'),
        };
    }

    public function firstName(): string
    {
        return Str::of($this->name)->trim()->before(' ')->toString();
    }

    /** Número de compras, total líquido de devoluções e data da última compra. */
    public function scopeWithStats(Builder $query): Builder
    {
        return $query->select('customers.*')
            ->selectRaw(CustomerFilter::COUNT_SQL.' as purchases_count')
            ->selectRaw(CustomerFilter::SPENT_SQL.' as total_spent')
            ->selectRaw(CustomerFilter::LAST_SQL.' as last_purchase_at');
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $digits = preg_replace('/\D/', '', $term);

        return $query->where(fn (Builder $inner) => $inner
            ->where('name', 'like', "%{$term}%")
            ->orWhere('email', 'like', "%{$term}%")
            ->when($digits !== '', fn (Builder $byDigits) => $byDigits
                ->orWhere('phone', 'like', "%{$digits}%")
                ->orWhere('whatsapp', 'like', "%{$digits}%")
                ->orWhere('cpf', 'like', "%{$digits}%")));
    }

    /** Requer scopeWithStats. */
    public function statusLabel(): string
    {
        if ($this->last_purchase_at === null) {
            return 'Sem compras';
        }

        return $this->last_purchase_at->gte(now()->subDays((int) config('loja.customer_inactive_days'))) ? 'Ativo' : 'Inativo';
    }
}
