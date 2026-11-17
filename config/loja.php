<?php

return [

    /*
    | Redireciona todo o tráfego para HTTPS em produção. Só desative se o
    | servidor já fizer esse redirecionamento e entrar em laço com a aplicação.
    */
    'force_https' => env('APP_FORCE_HTTPS', true),

    /*
    | Preencha só se o servidor ficar atrás de proxy reverso: lista de IPs
    | separados por vírgula, ou "*". Sem isso, IP e HTTPS vêm da conexão direta.
    */
    'trusted_proxies' => env('TRUSTED_PROXIES'),

    /*
    | Limite de desconto, em percentual sobre a venda, para quem tem a permissão
    | "pdv.desconto". Acima dele é preciso a permissão "pdv.desconto_livre".
    */
    'max_discount_percent' => (float) env('PDV_MAX_DISCOUNT_PERCENT', 10),

    /* Dias sem comprar a partir dos quais o cliente passa a ser considerado inativo. */
    'customer_inactive_days' => (int) env('CUSTOMER_INACTIVE_DAYS', 180),

    /*
    | Envio de e-mails de marketing pelo SMTP. O limite por hora deve ficar
    | abaixo do limite do plano de e-mail; o envio é distribuído minuto a minuto.
    | Mensagens de WhatsApp pendentes vencem após "whatsapp_expires_days".
    */
    'marketing' => [
        'email_per_hour' => (int) env('MARKETING_EMAIL_PER_HOUR', 100),
        'whatsapp_expires_days' => (int) env('MARKETING_WHATSAPP_EXPIRES_DAYS', 3),
    ],

    /* Dados impressos no comprovante. */
    'store' => [
        'name' => env('STORE_NAME', 'Loja Demo'),
        'tagline' => env('STORE_TAGLINE', 'Moda infantil · demonstração DevZero1'),
        'instagram' => env('STORE_INSTAGRAM', '@lojademo'),
        'whatsapp' => env('STORE_WHATSAPP', '(86) 3000-0000'),
    ],

    /*
    | Modo demonstração: mostra a faixa de aviso e os acessos de teste no login,
    | nunca envia e-mails de verdade, abre o WhatsApp sem destinatário (os
    | telefones são fictícios) e restaura os dados fictícios todo dia.
    */
    'demo' => [
        'enabled' => (bool) env('DEMO_MODE', false),
        'password' => env('DEMO_PASSWORD', 'demo1234'),
        'reset_at' => env('DEMO_RESET_AT', '03:00'),
    ],

    /*
    | Dados do primeiro administrador, lidos apenas pelo seeder. Se não houver
    | senha definida, o seeder gera uma senha aleatória e a exibe uma única vez.
    */
    'admin' => [
        'name' => env('ADMIN_NAME', 'Administrador'),
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],

    /*
    | Catálogo de permissões: cada módulo lista suas operações. A permissão
    | gravada no banco se chama "modulo.operacao". Novos módulos entram aqui,
    | na fase em que forem de fato implementados.
    */
    'modules' => [
        'dashboard' => [
            'label' => 'Painel',
            'operations' => ['ver' => 'Ver indicadores'],
        ],
        'usuarios' => [
            'label' => 'Usuários',
            'operations' => [
                'ver' => 'Visualizar',
                'criar' => 'Cadastrar',
                'editar' => 'Editar',
                'bloquear' => 'Bloquear e desbloquear',
            ],
        ],
        'perfis' => [
            'label' => 'Perfis de acesso',
            'operations' => [
                'ver' => 'Visualizar',
                'criar' => 'Cadastrar',
                'editar' => 'Editar',
                'excluir' => 'Excluir',
            ],
        ],
        'sessoes' => [
            'label' => 'Sessões',
            'operations' => [
                'ver' => 'Ver sessões de todos',
                'encerrar' => 'Encerrar sessões de outros usuários',
            ],
        ],
        'auditoria' => [
            'label' => 'Auditoria',
            'operations' => ['ver' => 'Consultar histórico'],
        ],
        'produtos' => [
            'label' => 'Produtos',
            'operations' => [
                'ver' => 'Visualizar',
                'criar' => 'Cadastrar',
                'editar' => 'Editar',
                'ver_custo' => 'Ver e alterar custos',
            ],
        ],
        'cadastros' => [
            'label' => 'Categorias, marcas e variações',
            'operations' => [
                'ver' => 'Visualizar',
                'gerir' => 'Cadastrar, editar e excluir',
            ],
        ],
        'fornecedores' => [
            'label' => 'Fornecedores',
            'operations' => [
                'ver' => 'Visualizar',
                'criar' => 'Cadastrar',
                'editar' => 'Editar',
            ],
        ],
        'estoque' => [
            'label' => 'Estoque',
            'operations' => [
                'ver' => 'Consultar saldos e histórico',
                'entrada' => 'Registrar entrada de mercadorias',
                'movimentar' => 'Registrar saídas, perdas, avarias e devoluções',
                'ajustar' => 'Fazer ajustes manuais',
                'inventario' => 'Realizar inventário',
                'negativo' => 'Autorizar saldo negativo',
            ],
        ],
        'pdv' => [
            'label' => 'Frente de caixa',
            'operations' => [
                'vender' => 'Realizar vendas',
                'desconto' => 'Dar desconto até o limite da loja',
                'desconto_livre' => 'Dar desconto sem limite',
            ],
        ],
        'caixa' => [
            'label' => 'Controle de caixa',
            'operations' => [
                'operar' => 'Abrir, movimentar e fechar o próprio caixa',
                'gerir' => 'Ver e fechar caixas de outros operadores',
            ],
        ],
        'vendas' => [
            'label' => 'Vendas',
            'operations' => [
                'ver' => 'Consultar vendas de todos',
                'cancelar' => 'Cancelar vendas',
                'devolver' => 'Registrar devoluções e trocas',
            ],
        ],
        'clientes' => [
            'label' => 'Clientes',
            'operations' => [
                'ver' => 'Consultar clientes e histórico de compras',
                'criar' => 'Cadastrar',
                'editar' => 'Editar e gerenciar segmentos',
                'exportar' => 'Exportar lista de clientes',
            ],
        ],
        'marketing' => [
            'label' => 'Marketing',
            'operations' => [
                'ver' => 'Ver campanhas e resultados',
                'campanhas' => 'Criar e editar campanhas',
                'disparar' => 'Iniciar, pausar e cancelar envios',
                'whatsapp' => 'Enviar mensagens da fila do WhatsApp',
                'automacoes' => 'Configurar mensagens automáticas',
            ],
        ],
        'financeiro' => [
            'label' => 'Financeiro',
            'operations' => [
                'ver' => 'Consultar fluxo de caixa, contas e recebimentos',
                'pagar' => 'Lançar, pagar e cancelar contas a pagar',
                'receber' => 'Confirmar recebimentos de cartão',
            ],
        ],
        'relatorios' => [
            'label' => 'Relatórios',
            'operations' => [
                'vendas' => 'Relatório de vendas',
                'produtos' => 'Relatório de produtos e estoque',
                'resultado' => 'Resultado do mês e margens (mostra custos)',
            ],
        ],
    ],

    /*
    | Perfis criados na instalação. O Administrador tem acesso total por regra
    | e não é editável. Os demais recebem estas permissões apenas ao serem
    | criados; ajustes feitos depois pelo painel não são sobrescritos.
    */
    'default_roles' => [
        'Gerente' => [
            'dashboard.ver', 'usuarios.ver', 'perfis.ver', 'sessoes.ver', 'auditoria.ver',
            'produtos.ver', 'produtos.criar', 'produtos.editar', 'produtos.ver_custo',
            'cadastros.ver', 'cadastros.gerir',
            'fornecedores.ver', 'fornecedores.criar', 'fornecedores.editar',
            'estoque.ver', 'estoque.entrada', 'estoque.movimentar', 'estoque.ajustar', 'estoque.inventario',
            'pdv.vender', 'pdv.desconto', 'pdv.desconto_livre', 'caixa.operar', 'caixa.gerir',
            'vendas.ver', 'vendas.cancelar', 'vendas.devolver',
            'clientes.ver', 'clientes.criar', 'clientes.editar', 'clientes.exportar',
            'marketing.ver', 'marketing.campanhas', 'marketing.disparar', 'marketing.whatsapp', 'marketing.automacoes',
            'financeiro.ver', 'financeiro.pagar', 'financeiro.receber',
            'relatorios.vendas', 'relatorios.produtos', 'relatorios.resultado',
        ],
        'Vendedor' => ['produtos.ver', 'estoque.ver', 'pdv.vender', 'pdv.desconto', 'caixa.operar', 'clientes.criar', 'marketing.whatsapp'],
        'Estoquista' => [
            'produtos.ver', 'produtos.ver_custo', 'cadastros.ver', 'fornecedores.ver',
            'estoque.ver', 'estoque.entrada', 'estoque.movimentar', 'estoque.ajustar', 'estoque.inventario',
        ],
    ],

];
