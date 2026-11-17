<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;

class CustomerService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ConsentService $consents,
    ) {}

    /** Cadastro rápido do PDV; o consentimento vale para os canais informados. */
    public function quickCreate(string $name, ?string $phone, ?string $email, bool $marketingConsent = false): Customer
    {
        return $this->save(null, ['name' => $name, 'phone' => $phone, 'whatsapp' => $phone, 'email' => $email], [], [
            'email' => $marketingConsent && filled($email),
            'whatsapp' => $marketingConsent && filled($phone),
        ], 'pdv');
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array{id: ?int, name: string, birth_date: ?string, clothing_size: ?string}>  $children
     * @param  array<string, bool>  $consents  canal => consentiu; canais ausentes não mudam
     */
    public function save(?Customer $customer, array $data, array $children, array $consents = [], string $consentSource = 'cadastro'): Customer
    {
        return DB::transaction(function () use ($customer, $data, $children, $consents, $consentSource) {
            $customer ??= new Customer;
            $customer->fill($data)->save();

            $keptIds = [];

            foreach ($children as $child) {
                $attributes = [
                    'name' => $child['name'],
                    'birth_date' => $child['birth_date'],
                    'clothing_size' => $child['clothing_size'],
                ];

                $model = filled($child['id'] ?? null) ? $customer->children()->find($child['id']) : null;

                if ($model) {
                    $model->update($attributes);
                } else {
                    $model = $customer->children()->create($attributes);
                }

                $keptIds[] = $model->id;
            }

            $customer->children()->whereNotIn('id', $keptIds)->delete();

            $this->consents->setMany($customer, $consents, $consentSource);

            // Registra apenas o nome: CPF e dados dos filhos não vão para a auditoria.
            $this->audit->record('customer.saved', "Cliente {$customer->name} salvo.", $customer);

            return $customer;
        });
    }
}
