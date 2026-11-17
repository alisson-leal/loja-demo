# Loja Demo (DevZero1) — guia para quem trabalha no código

Demonstração do ERP + PDV da DevZero1, com dados fictícios de uma loja de moda infantil (veja "Modo demonstração" no README).
Laravel 13, Livewire 4, Bootstrap 5.3, MySQL 8. Derivado do sistema da Lelé Kids: os dados de clientes reais nunca entram aqui.
Interface, mensagens e documentação em **português do Brasil**. Visão geral e instalação: [README.md](README.md).

## Ambiente local (Windows)

- Ferramentas portáteis em `D:\LeleKids-Tools` (PHP 8.3, Composer, Node, Git, MySQL). Antes de qualquer comando:
  `. D:\LeleKids-Tools\env.ps1`. Não alterar o Windows nem `C:\xampp` (pertence a outro sistema).
- `php artisan serve --port=8010` sobe a demonstração em http://127.0.0.1:8010 (o MySQL é o mesmo da Lelé Kids, porta 3307).
- Testes: `php artisan test` (usa o banco MySQL `lojademo_test`). Estilo: `vendor\bin\pint`. Front: `npm run build`.

## Produção

Publicada na mesma VPS da Lelé Kids (Ubuntu 24.04, 1 GB RAM), com usuário de sistema, pool PHP-FPM e banco próprios.
Como a VPS é compartilhada, evitar processos pesados; a restauração diária roda de madrugada.

## Regras de arquitetura

- **Regras de negócio ficam em `app/Services`.** Componentes Livewire são finos: validam entrada e chamam o serviço.
- Erros de regra usam `App\Exceptions\BusinessRuleException` (mensagem exibida ao usuário) via trait `HandlesBusinessRules::attempt()`.
- **Dinheiro em centavos (inteiros)**: `App\Support\Money::toCents/fromCents`. Totais da venda só via `App\Support\SaleCalculator`.
- **Estoque físico só muda por `StockService`**, dentro de transação com `lockForUpdate`, travando variantes em ordem crescente de id.
- Registros históricos (`ActivityLog`, `StockMovement`, `VariantCostHistory`, `CashMovement`) usam a trait `Immutable`: corrigir com novo lançamento, nunca editar.
- Ações relevantes são auditadas com `AuditLogger`.
- **Permissões**: catálogo em `config/loja.php` (`modules`, formato `modulo.operacao`), aplicadas com spatie/laravel-permission
  nas rotas (`can:`) e nos serviços. Permissão nova exige migração "grant" para os perfis existentes. Administrador tem acesso total via `Gate::before`.
- Ações sensíveis de Vendedor (cancelamento, devolução, desconto acima do limite) pedem autorização de gerente (`SupervisorAuthorization`).
- Imagens de produto são servidas por `MediaController` em `/media/{path}` (sem `storage:link`).
- LGPD: não exportar CPF; neutralizar fórmulas em CSV; dados de clientes só com permissão `clientes.*`.
- **Consentimento de marketing só muda por `ConsentService`**, que grava o histórico imutável (`customer_consents`) e descarta mensagens pendentes ao revogar.
  Campanhas e automações só alcançam clientes com consentimento no canal (`Customer::scopeReachableBy`).
- Financeiro: cada pagamento de venda vira `receivables` pelo `ReceivableService`, chamado dentro da transação da venda.
  A taxa e o prazo da forma de pagamento são copiados no momento da venda. Cancelar a venda cancela os recebíveis.
  Reembolso de devolução por qualquer forma (exceto vale) gera conta paga na categoria de sistema `devolucoes`.
  O resultado (`FinanceReports::result`) exclui as categorias `mercadorias` (o custo já entra pelo CMV) e `devolucoes`.
  A loja não vende fiado: as contas a receber são só das vendas.
- Marketing: as mensagens ficam em `marketing_messages`, com uma `dedupe_key` única. E-mails saem pelo agendador (`MarketingEmailSender`, limite por hora).
  WhatsApp **não usa API**: a loja envia manualmente pela fila (links wa.me). Não automatizar o envio de WhatsApp por meios não oficiais.

## Livewire 4 — armadilhas conhecidas

- Não usar `$attributes` como variável de view (reservada pelo Blade).
- Para enviar ao sair do campo: `wire:model.live.blur` (`.blur` sozinho não envia).

## Interface

Tema escuro com a identidade DevZero1 (`data-bs-theme="dark"`, Poppins, fundo azul-marinho, degradês azul #1677FF → ciano #00C8FF) em
`resources/scss/app.scss`. Telas novas usam `.card`, `.btn-primary` e os componentes Blade de `resources/views/components`.
Testar em 390 px e 820 px de largura: nada pode estourar horizontalmente.

## Forma de trabalho

- Desenvolvimento em fases; ao fim de cada fase, parar para revisão do dono do projeto.
- Toda mudança acompanha testes em `tests/Feature` e deve manter a suíte inteira passando.
