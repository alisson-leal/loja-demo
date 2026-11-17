<?php

namespace App\Models;

use App\Models\Concerns\Immutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Registro de auditoria. É somente inclusão: não pode ser alterado nem apagado.
 */
class ActivityLog extends Model
{
    use Immutable;

    public const UPDATED_AT = null;

    public const EVENT_LABELS = [
        'auth.login' => 'Login',
        'auth.logout' => 'Logout',
        'auth.failed' => 'Falha de login',
        'auth.lockout' => 'Excesso de tentativas',
        'auth.password_reset' => 'Senha redefinida por e-mail',
        'account.password_changed' => 'Senha alterada',
        'user.created' => 'Usuário cadastrado',
        'user.updated' => 'Usuário alterado',
        'user.blocked' => 'Usuário bloqueado',
        'user.unblocked' => 'Usuário desbloqueado',
        'role.created' => 'Perfil cadastrado',
        'role.updated' => 'Perfil alterado',
        'role.deleted' => 'Perfil excluído',
        'session.revoked' => 'Sessão encerrada',
        'catalog.saved' => 'Cadastro salvo',
        'catalog.deleted' => 'Cadastro excluído',
        'supplier.saved' => 'Fornecedor salvo',
        'product.created' => 'Produto cadastrado',
        'product.updated' => 'Produto alterado',
        'stock.entry' => 'Entrada de mercadorias',
        'inventory.opened' => 'Inventário aberto',
        'inventory.finished' => 'Inventário concluído',
        'inventory.cancelled' => 'Inventário cancelado',
        'cash.opened' => 'Caixa aberto',
        'cash.movement' => 'Movimentação de caixa',
        'cash.closed' => 'Caixa fechado',
        'sale.cancelled' => 'Venda cancelada',
        'sale.returned' => 'Devolução ou troca',
        'payment_method.saved' => 'Forma de pagamento salva',
        'supervisor.denied' => 'Autorização recusada',
        'customer.saved' => 'Cliente salvo',
        'customer.exported' => 'Clientes exportados',
        'customer.segment_saved' => 'Segmento salvo',
        'customer.segment_deleted' => 'Segmento excluído',
        'user.password_reset_cli' => 'Senha redefinida pelo servidor',
        'marketing.campaign_saved' => 'Campanha salva',
        'marketing.campaign_deleted' => 'Campanha excluída',
        'marketing.campaign_started' => 'Campanha iniciada',
        'marketing.campaign_pausada' => 'Campanha pausada',
        'marketing.campaign_retomada' => 'Campanha retomada',
        'marketing.campaign_cancelada' => 'Campanha cancelada',
        'marketing.automation_saved' => 'Mensagem automática alterada',
        'finance.payable_created' => 'Conta a pagar lançada',
        'finance.payable_updated' => 'Conta a pagar alterada',
        'finance.payable_paid' => 'Conta paga',
        'finance.payable_reopened' => 'Pagamento desfeito',
        'finance.payable_cancelled' => 'Conta cancelada',
        'finance.received' => 'Recebimento confirmado',
        'finance.category_saved' => 'Categoria de despesa salva',
    ];

    protected $fillable = [
        'user_id', 'event', 'description', 'subject_type', 'subject_id',
        'properties', 'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function eventLabel(): string
    {
        return self::EVENT_LABELS[$this->event] ?? $this->event;
    }
}
