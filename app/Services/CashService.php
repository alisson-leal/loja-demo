<?php

namespace App\Services;

use App\Enums\CashMovementType;
use App\Exceptions\BusinessRuleException;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\SalePayment;
use App\Models\User;
use App\Support\CurrentStore;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Controle do caixa de cada operador. O saldo esperado é sempre a soma das
 * movimentações em dinheiro, que não podem ser alteradas.
 */
class CashService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function currentFor(User $user): ?CashSession
    {
        return CashSession::where('user_id', $user->id)->where('status', CashSession::OPEN)->first();
    }

    public function open(User $actor, int $openingCents): CashSession
    {
        if ($openingCents < 0) {
            throw new BusinessRuleException('O saldo inicial não pode ser negativo.');
        }

        return DB::transaction(function () use ($actor, $openingCents) {
            // Bloquear o usuário impede abrir dois caixas ao mesmo tempo.
            User::whereKey($actor->id)->lockForUpdate()->firstOrFail();

            if ($this->currentFor($actor)) {
                throw new BusinessRuleException('Você já tem um caixa aberto.');
            }

            $session = CashSession::create([
                'store_id' => CurrentStore::id(),
                'user_id' => $actor->id,
                'status' => CashSession::OPEN,
                'opening_amount' => Money::fromCents($openingCents),
                'opened_at' => now(),
            ]);

            $this->write($session, CashMovementType::Opening, $openingCents, 'Abertura de caixa', $actor);
            $this->audit->record('cash.opened', "Caixa #{$session->id} aberto com ".Money::format($openingCents / 100).'.', $session);

            return $session;
        });
    }

    /** Sangria, suprimento, entrada ou retirada no caixa do próprio operador. */
    public function registerManual(User $actor, CashMovementType $type, int $cents, string $reason): void
    {
        if (! in_array($type, CashMovementType::manualTypes(), true)) {
            throw new BusinessRuleException('Tipo de movimentação inválido.');
        }

        if ($cents <= 0) {
            throw new BusinessRuleException('O valor deve ser maior que zero.');
        }

        DB::transaction(function () use ($actor, $type, $cents, $reason) {
            $session = $this->lockOpenSession($actor);
            $this->writeChecked($session, $type, $cents, $reason, $actor);

            $this->audit->record('cash.movement', "{$type->label()} de ".Money::format($cents / 100)." no caixa #{$session->id}: {$reason}.", $session);
        });
    }

    /** Devolve dinheiro ao cliente, a partir do caixa aberto de quem está atendendo. */
    public function refund(User $actor, int $cents, string $reason, Model $reference): void
    {
        $session = $this->lockOpenSession($actor, 'Abra seu caixa para devolver dinheiro ao cliente.');
        $this->writeChecked($session, CashMovementType::Refund, $cents, $reason, $actor, $reference);
    }

    public function recordSale(CashSession $session, int $cents, User $actor, Model $sale, string $reason): void
    {
        $this->write($session, CashMovementType::Sale, $cents, $reason, $actor, $sale);
    }

    public function lockOpenSession(User $actor, string $message = 'Abra seu caixa antes de continuar.'): CashSession
    {
        $session = CashSession::where('user_id', $actor->id)
            ->where('status', CashSession::OPEN)
            ->lockForUpdate()
            ->first();

        if (! $session) {
            throw new BusinessRuleException($message);
        }

        return $session;
    }

    public function close(User $actor, CashSession $session, int $countedCents, ?string $notes): CashSession
    {
        if ($countedCents < 0) {
            throw new BusinessRuleException('O valor contado não pode ser negativo.');
        }

        if ($session->user_id !== $actor->id && ! $actor->can('caixa.gerir')) {
            throw new BusinessRuleException('Você só pode fechar o próprio caixa.');
        }

        return DB::transaction(function () use ($actor, $session, $countedCents, $notes) {
            $locked = CashSession::whereKey($session->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isOpen()) {
                throw new BusinessRuleException('Este caixa já foi fechado.');
            }

            $expected = $this->expectedCents($locked);
            $difference = $countedCents - $expected;

            if ($difference !== 0 && blank($notes)) {
                throw new BusinessRuleException('Há diferença entre o valor contado e o esperado. Informe uma observação.');
            }

            $locked->update([
                'status' => CashSession::CLOSED,
                'expected_amount' => Money::fromCents($expected),
                'counted_amount' => Money::fromCents($countedCents),
                'difference' => Money::fromCents($difference),
                'closing_notes' => $notes,
                'closed_by' => $actor->id,
                'closed_at' => now(),
            ]);

            $this->audit->record('cash.closed', "Caixa #{$locked->id} fechado com diferença de ".Money::format($difference / 100).'.', $locked);

            return $locked;
        });
    }

    public function expectedCents(CashSession $session): int
    {
        return Money::toCents($session->movements()->sum('amount'));
    }

    /** @return Collection<int, object{name: string, total: string}> Totais por forma de pagamento. */
    public function totalsByMethod(CashSession $session): Collection
    {
        return SalePayment::query()
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->join('payment_methods', 'payment_methods.id', '=', 'sale_payments.payment_method_id')
            ->where('sales.cash_session_id', $session->id)
            ->where('sales.status', 'completed')
            ->groupBy('payment_methods.id', 'payment_methods.name')
            ->orderBy('payment_methods.name')
            ->selectRaw('payment_methods.name as name, SUM(sale_payments.amount) as total')
            ->toBase()
            ->get();
    }

    private function writeChecked(CashSession $session, CashMovementType $type, int $cents, string $reason, User $actor, ?Model $reference = null): void
    {
        if (! $type->isInbound() && $cents > $this->expectedCents($session)) {
            throw new BusinessRuleException('Não há dinheiro suficiente no caixa. Saldo atual: '.Money::format($this->expectedCents($session) / 100).'.');
        }

        $this->write($session, $type, $cents, $reason, $actor, $reference);
    }

    private function write(CashSession $session, CashMovementType $type, int $cents, string $reason, User $actor, ?Model $reference = null): void
    {
        CashMovement::create([
            'cash_session_id' => $session->id,
            'type' => $type,
            'amount' => Money::fromCents($type->isInbound() ? $cents : -$cents),
            'reason' => $reason,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'user_id' => $actor->id,
        ]);
    }
}
