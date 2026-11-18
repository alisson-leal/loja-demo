<!doctype html>
<html lang="pt-BR" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Painel' }} · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.png') }}" type="image/png">
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>
<body>
<div class="app-shell">
    <aside class="offcanvas-lg offcanvas-start app-sidebar" tabindex="-1" id="sidebar" aria-labelledby="sidebar-title">
        <div class="app-brand">
            <a href="{{ route('dashboard') }}" class="app-brand-link" id="sidebar-title">
                <img src="{{ asset('images/logo-compacta.png') }}" alt="DevZero1" width="600" height="103">
                <span class="app-brand-tag">{{ config('loja.store.name') }}</span>
            </a>
            <button type="button" class="btn-close d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="Fechar menu"></button>
        </div>

        <nav class="app-nav" aria-label="Menu principal">
            <x-nav-link route="dashboard" icon="grid-1x2">Painel</x-nav-link>

            @canany(['pdv.vender', 'caixa.operar', 'caixa.gerir', 'vendas.ver', 'condicional.criar', 'condicional.acertar', 'crediario.receber', 'crediario.gerir'])
                <p class="app-nav-title">Vendas</p>
            @endcanany
            @can('pdv.vender')
                <x-nav-link route="pos" icon="cart3">Frente de caixa</x-nav-link>
            @endcan
            @can('caixa.operar')
                <x-nav-link route="cash.current" icon="cash-stack">Meu caixa</x-nav-link>
            @endcan
            @canany(['caixa.operar', 'caixa.gerir'])
                <x-nav-link route="cash.history" icon="journal-check" active="cash.history">Histórico de caixas</x-nav-link>
            @endcanany
            @canany(['pdv.vender', 'vendas.ver'])
                <x-nav-link route="sales.index" icon="receipt" active="sales.*">Vendas realizadas</x-nav-link>
            @endcanany
            @canany(['condicional.criar', 'condicional.acertar'])
                @php($consignmentsOverdue = \App\Models\Consignment::where('status', 'open')->whereDate('due_date', '<', today())->count())
                <x-nav-link route="consignments.index" icon="bag-heart" active="consignments.*">Condicionais @if ($consignmentsOverdue > 0)<span class="badge rounded-pill text-bg-danger ms-1">{{ $consignmentsOverdue }}</span>@endif</x-nav-link>
            @endcanany
            @canany(['crediario.receber', 'crediario.gerir'])
                <x-nav-link route="crediario.index" icon="journal-text">Crediário</x-nav-link>
            @endcanany

            @can('clientes.ver')
                <p class="app-nav-title">Clientes</p>
                <x-nav-link route="customers.index" icon="person-hearts" active="customers.index">Clientes</x-nav-link>
                <x-nav-link route="customers.birthdays" icon="balloon">Aniversariantes</x-nav-link>
            @endcan

            @canany(['marketing.ver', 'marketing.whatsapp', 'marketing.automacoes'])
                <p class="app-nav-title">Marketing</p>
                @can('marketing.whatsapp')
                    @php($whatsappPending = \App\Models\MarketingMessage::whatsappQueue()->count())
                    <x-nav-link route="marketing.whatsapp" icon="whatsapp">WhatsApp a enviar @if ($whatsappPending > 0)<span class="badge rounded-pill text-bg-success ms-1">{{ $whatsappPending }}</span>@endif</x-nav-link>
                @endcan
                @can('marketing.ver')
                    <x-nav-link route="marketing.campaigns.index" icon="megaphone" active="marketing.campaigns.*">Campanhas</x-nav-link>
                @endcan
                @can('marketing.automacoes')
                    <x-nav-link route="marketing.automations" icon="stars">Mensagens automáticas</x-nav-link>
                @endcan
            @endcanany

            @can('financeiro.ver')
                <p class="app-nav-title">Financeiro</p>
                <x-nav-link route="finance.cash-flow" icon="graph-up">Fluxo de caixa</x-nav-link>
                <x-nav-link route="finance.payables" icon="receipt-cutoff" active="finance.payables*">Contas a pagar</x-nav-link>
                <x-nav-link route="finance.receivables" icon="credit-card-2-front">Recebimentos de cartão</x-nav-link>
            @endcan

            @canany(['relatorios.vendas', 'relatorios.produtos', 'relatorios.resultado', 'comissoes.ver', 'comissoes.ver_propria'])
                <p class="app-nav-title">Relatórios</p>
                @can('relatorios.vendas')
                    <x-nav-link route="reports.sales" icon="bar-chart">Vendas</x-nav-link>
                @endcan
                @can('relatorios.produtos')
                    <x-nav-link route="reports.products" icon="box2-heart">Produtos e estoque</x-nav-link>
                @endcan
                @can('relatorios.resultado')
                    <x-nav-link route="reports.result" icon="clipboard-data">Resultado</x-nav-link>
                @endcan
                @canany(['comissoes.ver', 'comissoes.ver_propria'])
                    <x-nav-link route="reports.commissions" icon="percent">Comissões</x-nav-link>
                @endcanany
            @endcanany

            @canany(['produtos.ver', 'cadastros.ver', 'fornecedores.ver'])
                <p class="app-nav-title">Catálogo</p>
            @endcanany
            @can('produtos.ver')
                <x-nav-link route="products.index" icon="tags" active="products.*">Produtos</x-nav-link>
            @endcan
            @can('cadastros.ver')
                <x-nav-link route="categories.index" icon="diagram-3">Categorias</x-nav-link>
                <x-nav-link route="brands.index" icon="award">Marcas</x-nav-link>
                <x-nav-link route="attributes.index" icon="palette">Variações</x-nav-link>
                <x-nav-link route="payment-methods.index" icon="credit-card">Formas de pagamento</x-nav-link>
            @endcan
            @can('fornecedores.ver')
                <x-nav-link route="suppliers.index" icon="truck" active="suppliers.*">Fornecedores</x-nav-link>
            @endcan

            @canany(['estoque.ver', 'estoque.entrada', 'estoque.movimentar', 'estoque.ajustar', 'estoque.inventario'])
                <p class="app-nav-title">Estoque</p>
            @endcanany
            @can('estoque.ver')
                <x-nav-link route="stock.index" icon="boxes" active="stock.index">Saldos</x-nav-link>
            @endcan
            @can('estoque.entrada')
                <x-nav-link route="stock.entry" icon="box-arrow-in-down">Entrada de mercadorias</x-nav-link>
            @endcan
            @can('produtos.ver')
                <x-nav-link route="stock.labels" icon="upc-scan">Etiquetas</x-nav-link>
            @endcan
            @canany(['estoque.movimentar', 'estoque.ajustar'])
                <x-nav-link route="stock.movement" icon="arrow-left-right">Movimentar</x-nav-link>
            @endcanany
            @can('estoque.ver')
                <x-nav-link route="stock.history" icon="clock-history">Histórico</x-nav-link>
            @endcan
            @can('estoque.inventario')
                <x-nav-link route="stock.inventories" icon="clipboard-check" active="stock.inventor*">Inventário</x-nav-link>
            @endcan

            @canany(['usuarios.ver', 'perfis.ver', 'auditoria.ver'])
                <p class="app-nav-title">Administração</p>
            @endcanany
            @can('usuarios.ver')
                <x-nav-link route="users.index" icon="people" active="users.*">Usuários</x-nav-link>
            @endcan
            @can('perfis.ver')
                <x-nav-link route="roles.index" icon="shield-lock" active="roles.*">Perfis de acesso</x-nav-link>
            @endcan
            @can('auditoria.ver')
                <x-nav-link route="audit.index" icon="journal-text">Auditoria</x-nav-link>
            @endcan

            <p class="app-nav-title">Minha conta</p>
            <x-nav-link route="sessions.index" icon="laptop">Sessões</x-nav-link>
            <x-nav-link route="account.password" icon="key">Alterar senha</x-nav-link>
        </nav>
    </aside>

    <div class="app-main">
        @if (config('loja.demo.enabled'))
            <div class="demo-banner no-print" role="note">
                <i class="bi bi-stars" aria-hidden="true"></i>
                Ambiente de demonstração com dados fictícios. Fique à vontade para testar: tudo volta ao original às {{ config('loja.demo.reset_at') }}.
            </div>
        @endif
        <header class="app-topbar">
            <button class="btn btn-light d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-controls="sidebar" aria-label="Abrir menu">
                <i class="bi bi-list" aria-hidden="true"></i>
            </button>
            <h1 class="app-page-title">{{ $title ?? 'Painel' }}</h1>

            <div class="dropdown ms-auto">
                <button class="btn btn-light dropdown-toggle app-user" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-person-circle" aria-hidden="true"></i>
                    <span class="d-none d-sm-inline">{{ auth()->user()->name }}</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><span class="dropdown-item-text small text-body-secondary">{{ auth()->user()->getRoleNames()->implode(', ') ?: 'Sem perfil' }}</span></li>
                    <li><a class="dropdown-item" href="{{ route('account.password') }}">Alterar senha</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item">Sair</button>
                        </form>
                    </li>
                </ul>
            </div>
        </header>

        <main class="app-content">
            {{ $slot }}
        </main>
    </div>
</div>
</body>
</html>
