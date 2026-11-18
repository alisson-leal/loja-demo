<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\CashSession;
use App\Models\StockTransfer;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cadastro das lojas. O plano contratado com a DevZero1 define quantas lojas
 * podem estar ativas ao mesmo tempo (config loja.plan.max_stores).
 */
class StoreService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @return array{name: string, max: int, active: int, available: int} */
    public function plan(): array
    {
        $max = (int) config('loja.plan.max_stores');
        $active = Store::active()->count();

        return [
            'name' => (string) config('loja.plan.name'),
            'max' => $max,
            'active' => $active,
            'available' => max(0, $max - $active),
        ];
    }

    /** @param array{name: string, code: string, phone: ?string, address: ?string, city: ?string, is_active: bool} $data */
    public function save(?Store $store, array $data, User $actor): Store
    {
        $data['code'] = Str::upper(trim($data['code']));

        return DB::transaction(function () use ($store, $data) {
            $activating = $data['is_active'] && (! $store || ! $store->is_active);

            if ($activating) {
                // Trava a contagem para duas pessoas não passarem do limite ao mesmo tempo.
                $active = Store::active()->lockForUpdate()->count();
                $max = (int) config('loja.plan.max_stores');

                if ($active >= $max) {
                    throw new BusinessRuleException($max === 1
                        ? 'Seu plano inclui uma loja. Para cadastrar filiais, contrate o plano Multi Lojas com a DevZero1.'
                        : "Seu plano permite {$max} lojas ativas. Para abrir mais, fale com a DevZero1 sobre ampliar o plano.");
                }
            }

            if ($store && $store->is_main && ! $data['is_active']) {
                throw new BusinessRuleException('A loja principal não pode ser desativada.');
            }

            if ($store && $store->is_active && ! $data['is_active']) {
                $this->assertCanDeactivate($store);
            }

            $store ??= new Store;
            $store->fill($data)->save();

            $this->audit->record('store.saved', "Loja {$store->name} salva".($store->is_active ? '' : ' (inativa)').'.', $store);

            return $store;
        });
    }

    /** @param list<int> $userIds */
    public function syncUsers(Store $store, array $userIds, User $actor): void
    {
        $store->users()->sync(User::whereIn('id', $userIds)->pluck('id'));
        $this->audit->record('store.users', "Equipe da loja {$store->name} alterada.", $store, ['usuarios' => count($userIds)]);
    }

    private function assertCanDeactivate(Store $store): void
    {
        if (StoreStock::where('store_id', $store->id)->where('on_hand', '!=', 0)->exists()) {
            throw new BusinessRuleException('Esta loja ainda tem estoque. Transfira as peças para outra loja antes de desativá-la.');
        }

        if (CashSession::where('store_id', $store->id)->where('status', CashSession::OPEN)->exists()) {
            throw new BusinessRuleException('Há caixa aberto nesta loja. Feche os caixas antes de desativá-la.');
        }

        if (StockTransfer::where('status', StockTransfer::IN_TRANSIT)->where(fn ($q) => $q->where('from_store_id', $store->id)->orWhere('to_store_id', $store->id))->exists()) {
            throw new BusinessRuleException('Há transferências a caminho envolvendo esta loja.');
        }
    }
}
