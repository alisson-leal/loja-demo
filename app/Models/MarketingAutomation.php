<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketingAutomation extends Model
{
    public const CUSTOMER_BIRTHDAY = 'aniversario_cliente';

    public const CHILD_BIRTHDAY = 'aniversario_filho';

    public const PURCHASE_THANKS = 'agradecimento_compra';

    public const LABELS = [
        self::PURCHASE_THANKS => 'Agradecimento pela compra',
        self::CUSTOMER_BIRTHDAY => 'Aniversário do cliente',
        self::CHILD_BIRTHDAY => 'Aniversário do filho',
    ];

    protected $fillable = [
        'enabled', 'days_before', 'send_whatsapp', 'send_email',
        'email_subject', 'email_body', 'whatsapp_body', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'days_before' => 'integer',
            'send_whatsapp' => 'boolean',
            'send_email' => 'boolean',
        ];
    }

    public function label(): string
    {
        return self::LABELS[$this->key] ?? $this->key;
    }

    public function isForChildren(): bool
    {
        return $this->key === self::CHILD_BIRTHDAY;
    }

    public function isBirthday(): bool
    {
        return in_array($this->key, [self::CUSTOMER_BIRTHDAY, self::CHILD_BIRTHDAY], true);
    }

    /** @return list<string> */
    public function channels(): array
    {
        return array_values(array_filter([
            $this->send_whatsapp ? 'whatsapp' : null,
            $this->send_email ? 'email' : null,
        ]));
    }
}
