<?php

/*
|--------------------------------------------------------------------------
| Ajuda do sistema
|--------------------------------------------------------------------------
| Um tópico por tela (ou grupo de telas). O usuário só vê os tópicos cujas
| permissões possui ("can": basta uma; vazio = todos). "routes" liga o tópico
| às telas para a tecla F1 (aceita curinga, ex.: "sales.*"). Cada passo pode
| ser um texto ou ['text' => ..., 'can' => [...]] para aparecer só a quem tem
| a permissão. Use **texto** para destacar.
*/

return [
    'sections' => ['Primeiros passos', 'Vendas', 'Clientes', 'Marketing', 'Financeiro', 'Relatórios', 'Catálogo', 'Estoque', 'Administração', 'Minha conta'],

    'topics' => [
        'como-usar' => [
            'title' => 'Como usar o sistema e esta ajuda',
            'section' => 'Primeiros passos',
            'icon' => 'question-circle',
            'can' => [],
            'routes' => ['help.*'],
            'intro' => 'O menu da esquerda mostra só o que o seu perfil pode usar. Em qualquer tela, aperte **F1** (ou o botão **?** no topo) para ver a ajuda daquela tela.',
            'steps' => [
                'No celular, toque no ícone de menu (três linhas) no canto superior esquerdo para abrir o menu.',
                'O título da tela aparece no topo. À direita ficam o seu nome (com **Alterar senha** e **Sair**) e, quando há mais de uma loja, o seletor da loja em que você está trabalhando.',
                'Em **Ajuda**, no fim do menu, você encontra todos os assuntos liberados para o seu perfil, com busca.',
            ],
            'tips' => [
                'Se precisar de uma tela que não aparece no seu menu, peça ao administrador para ajustar o seu perfil de acesso.',
                'Ao terminar o expediente, use **Sair**, principalmente em computadores compartilhados.',
            ],
        ],

        'painel' => [
            'title' => 'Painel',
            'section' => 'Primeiros passos',
            'icon' => 'grid-1x2',
            'can' => [],
            'routes' => ['dashboard'],
            'intro' => 'Resumo do movimento da loja: vendas, formas de pagamento, produtos mais vendidos, estoque e atividades recentes.',
            'steps' => [
                'Escolha o período em **De** e **Até**, ou use os atalhos **Hoje**, **7 dias** e **30 dias**.',
                'Filtre por **Categoria** e por **Usuário** para ver o desempenho de um grupo de produtos ou de um vendedor.',
                ['text' => 'Com mais de uma loja, o filtro **Loja** mostra uma loja ou a rede inteira.', 'can' => ['lojas.ver_todas']],
                'Os cartões e gráficos mudam conforme os filtros escolhidos.',
            ],
            'tips' => ['As informações que aparecem dependem das permissões do seu perfil.'],
        ],

        'frente-de-caixa' => [
            'title' => 'Frente de caixa (PDV)',
            'section' => 'Vendas',
            'icon' => 'cart3',
            'can' => ['pdv.vender'],
            'routes' => ['pos'],
            'intro' => 'Tela de venda no balcão. Para vender é preciso estar com o caixa aberto.',
            'steps' => [
                'Se aparecer **Seu caixa está fechado**, clique em **Abrir caixa** e informe o troco inicial.',
                'Busque a peça pelo nome, SKU ou código de barras (o leitor de código de barras funciona direto no campo de busca) e clique nela para incluir.',
                'Ajuste a quantidade com **−** e **+**. Para dar desconto, use a coluna de desconto do item ou o **Desconto geral**.',
                'Em **Cliente**, busque o cliente ou clique em **Cadastrar novo cliente**. A venda também pode ser feita sem cliente.',
                'Em **Pagamento**, escolha a forma e o valor. Dá para dividir em várias formas (por exemplo, parte no Pix e parte no dinheiro). No dinheiro, o sistema calcula o troco.',
                ['text' => 'No **Crediário**, o cliente precisa estar cadastrado e ter limite disponível; escolha o número de parcelas.', 'can' => ['crediario.vender']],
                'Confira o total e clique em **Finalizar venda**. Em seguida você pode imprimir o comprovante, enviar pelo WhatsApp ou por e-mail.',
            ],
            'tips' => [
                'Desconto acima do seu limite pede a autorização de um gerente, que digita e-mail e senha na sua tela.',
                'Para acertar uma condicional, abra a condicional e use **Cobrar no caixa**: as peças já chegam ao carrinho.',
            ],
        ],

        'caixa' => [
            'title' => 'Meu caixa e histórico de caixas',
            'section' => 'Vendas',
            'icon' => 'cash-stack',
            'can' => ['caixa.operar', 'caixa.gerir'],
            'routes' => ['cash.current', 'cash.history', 'cash.report'],
            'intro' => 'Controle do dinheiro da gaveta: abertura, movimentações e fechamento com conferência.',
            'steps' => [
                ['text' => 'Em **Meu caixa**, clique em **Abrir caixa** e informe o valor de troco que está na gaveta.', 'can' => ['caixa.operar']],
                ['text' => 'Use **Sangria, suprimento, entrada ou retirada** para registrar dinheiro que sai ou entra na gaveta fora das vendas (por exemplo, depósito no banco ou troco extra) e clique em **Registrar**.', 'can' => ['caixa.operar']],
                ['text' => 'No fim do dia, conte o dinheiro, informe o valor em **Fechar caixa** e confirme. A diferença entre o esperado e o contado fica registrada.', 'can' => ['caixa.operar']],
                'Em **Histórico de caixas** ficam os caixas anteriores, com esperado, contado e diferença. Clique em **Ver** para o relatório completo.',
                ['text' => 'Como gerente, você vê os caixas de todos e pode fechar o caixa de um operador pelo relatório do caixa.', 'can' => ['caixa.gerir']],
            ],
            'tips' => ['Pix, cartão e crediário não entram na contagem da gaveta: aparecem separados em "Vendas neste caixa por forma de pagamento".'],
        ],

        'vendas' => [
            'title' => 'Vendas realizadas, cancelamento e devolução',
            'section' => 'Vendas',
            'icon' => 'receipt',
            'can' => ['pdv.vender', 'vendas.ver'],
            'routes' => ['sales.*'],
            'intro' => 'Consulta das vendas, reenvio do comprovante, cancelamento e trocas.',
            'steps' => [
                'Filtre por número, cliente, situação, forma de pagamento e período.',
                ['text' => 'Filtre também por **Operador** para ver as vendas de um vendedor.', 'can' => ['vendas.ver']],
                'Clique em **Abrir** para ver os itens e os pagamentos. Ali você pode **Abrir no WhatsApp** ou **Enviar por e-mail** o comprovante.',
                'Para **trocar ou devolver**, abra a venda, informe as peças e quantidades devolvidas, se voltam ao estoque e como o cliente será reembolsado (por exemplo, vale-troca ou Pix), e clique em **Registrar devolução**.',
                'Para **cancelar** uma venda lançada errada, abra a venda e use **Cancelar venda** informando o motivo. As peças voltam ao estoque.',
            ],
            'tips' => [
                'Se o seu perfil não pode cancelar ou devolver sozinho, o sistema pede a autorização de um gerente.',
                'O vale-troca fica no cadastro do cliente e pode ser usado como forma de pagamento numa próxima compra.',
            ],
        ],

        'condicionais' => [
            'title' => 'Venda condicional',
            'section' => 'Vendas',
            'icon' => 'bag-heart',
            'can' => ['condicional.criar', 'condicional.acertar'],
            'routes' => ['consignments.*'],
            'intro' => 'O cliente leva peças para provar em casa e depois volta para acertar o que ficou.',
            'steps' => [
                ['text' => 'Clique em **Nova condicional**, escolha o cliente, inclua as peças e o prazo de devolução e clique em **Abrir condicional**. As peças ficam reservadas e não podem ser vendidas para outra pessoa.', 'can' => ['condicional.criar']],
                'Imprima o comprovante da condicional para o cliente assinar.',
                'Perto do prazo, use **Lembrar o cliente** para mandar um lembrete pelo WhatsApp.',
                ['text' => 'Na volta do cliente, clique em **Fazer o acerto** e informe o que voltou. O que voltou fica disponível de novo no estoque.', 'can' => ['condicional.acertar']],
                ['text' => 'Se o cliente ficou com peças, use **Cobrar no caixa**: as peças vão para a frente de caixa para receber o pagamento. Se não ficou com nada, use **Encerrar sem venda**.', 'can' => ['condicional.acertar']],
            ],
            'tips' => ['Condicionais com prazo vencido aparecem com um número vermelho no menu.'],
        ],

        'crediario' => [
            'title' => 'Crediário',
            'section' => 'Vendas',
            'icon' => 'journal-text',
            'can' => ['crediario.receber', 'crediario.gerir'],
            'routes' => ['crediario.*'],
            'intro' => 'Parcelas das vendas no crediário (carnê da loja), com multa e juros por atraso.',
            'steps' => [
                'Filtre para ver as parcelas a vencer, vencidas ou pagas, e busque pelo cliente.',
                'Clique em **Receber** na parcela. O sistema calcula multa e juros se houver atraso. Escolha dinheiro ou Pix e clique em **Confirmar recebimento**.',
                'Use o ícone do WhatsApp para lembrar o cliente de uma parcela.',
                'O link do **carnê** imprime todas as parcelas da venda.',
                ['text' => 'O limite de crédito de cada cliente é definido no cadastro do cliente. Multa e juros ficam em **Formas de pagamento**, no crediário.', 'can' => ['crediario.gerir']],
            ],
            'tips' => [
                'O recebimento em dinheiro entra no seu caixa aberto.',
                'Dispensar multa e juros pede autorização de gerente.',
                'Cliente com parcela muito atrasada fica impedido de comprar no crediário até regularizar.',
            ],
        ],

        'clientes' => [
            'title' => 'Clientes',
            'section' => 'Clientes',
            'icon' => 'person-hearts',
            'can' => ['clientes.ver'],
            'routes' => ['customers.index', 'customers.show', 'customers.create', 'customers.edit'],
            'intro' => 'Cadastro de clientes, histórico de compras e segmentação para campanhas.',
            'steps' => [
                'Busque por nome, telefone ou e-mail. Use os filtros para encontrar grupos de clientes (por exemplo, quem não compra há 90 dias).',
                ['text' => 'Para guardar um filtro e usar depois em campanhas, clique em **Salvar** na área de **Segmentação**.', 'can' => ['marketing.campanhas']],
                ['text' => 'Clique em **Novo** para cadastrar. Marque em **Comunicação de ofertas e novidades** só os canais que o cliente autorizou (e-mail e/ou WhatsApp).', 'can' => ['clientes.criar']],
                'Clique no cliente para ver a ficha: dados, histórico de compras e produtos adquiridos.',
                ['text' => '**Exportar** baixa a lista em planilha, sem CPF, para proteger os dados do cliente (LGPD).', 'can' => ['clientes.exportar']],
            ],
            'tips' => ['Só envie ofertas para quem autorizou. O sistema respeita essa autorização em todas as campanhas e mensagens automáticas.'],
        ],

        'aniversariantes' => [
            'title' => 'Aniversariantes',
            'section' => 'Clientes',
            'icon' => 'balloon',
            'can' => ['clientes.ver'],
            'routes' => ['customers.birthdays'],
            'intro' => 'Lista de quem faz aniversário no período, para parabenizar e oferecer um mimo.',
            'steps' => [
                'Escolha o mês ou o período.',
                'Use o link do WhatsApp para mandar os parabéns.',
            ],
            'tips' => ['A mensagem automática de aniversário pode ser ligada em **Mensagens automáticas**.'],
        ],

        'whatsapp' => [
            'title' => 'WhatsApp a enviar',
            'section' => 'Marketing',
            'icon' => 'whatsapp',
            'can' => ['marketing.whatsapp'],
            'routes' => ['marketing.whatsapp'],
            'intro' => 'Fila de mensagens de WhatsApp prontas (agradecimentos, aniversários, campanhas). O envio é feito por você, pelo WhatsApp da loja.',
            'steps' => [
                'Leia a mensagem no cartão do cliente.',
                'Clique em **Abrir no WhatsApp**: a conversa abre com o texto pronto. Envie no WhatsApp e a mensagem sai da fila.',
                'Use **Pular** se não for enviar essa mensagem.',
                'Se o cliente pedir para não receber mais mensagens, clique em **Cliente pediu para sair**.',
            ],
            'tips' => [
                'O número verde no menu mostra quantas mensagens estão esperando.',
                'Mensagens têm prazo ("Enviar até"). Depois disso saem da fila automaticamente.',
            ],
        ],

        'campanhas' => [
            'title' => 'Campanhas',
            'section' => 'Marketing',
            'icon' => 'megaphone',
            'can' => ['marketing.ver'],
            'routes' => ['marketing.campaigns.*'],
            'intro' => 'Envio de ofertas por e-mail ou WhatsApp para um grupo de clientes.',
            'steps' => [
                ['text' => 'Clique em **Nova campanha**, dê um nome e escolha o canal (e-mail ou WhatsApp).', 'can' => ['marketing.campanhas']],
                ['text' => 'Em **Quem vai receber**, escolha um segmento salvo ou monte os filtros. A quantidade de clientes aparece na hora.', 'can' => ['marketing.campanhas']],
                ['text' => 'Escreva o texto. Use campos como {primeiro_nome} e {loja} para personalizar. Confira a **Pré-visualização** e clique em **Salvar rascunho**.', 'can' => ['marketing.campanhas']],
                ['text' => 'Na campanha, use **Enviar teste para mim** (e-mail) e depois **Iniciar envio**. É possível **Pausar**, **Retomar** ou **Cancelar campanha**.', 'can' => ['marketing.campanhas']],
                'Acompanhe o **Resultado**: enviados, pendentes e descadastros.',
            ],
            'tips' => [
                'Só recebem a campanha os clientes que autorizaram aquele canal.',
                'E-mails saem aos poucos, por hora, para não cair no spam. As campanhas de WhatsApp vão para a fila **WhatsApp a enviar**.',
            ],
        ],

        'automacoes' => [
            'title' => 'Mensagens automáticas',
            'section' => 'Marketing',
            'icon' => 'stars',
            'can' => ['marketing.automacoes'],
            'routes' => ['marketing.automations'],
            'intro' => 'Mensagens que o sistema prepara sozinho, como parabéns no aniversário e agradecimento pela compra.',
            'steps' => [
                'Ligue ou desligue cada mensagem e escolha o canal.',
                'Ajuste o texto usando os **Campos disponíveis** e clique em **Salvar**.',
            ],
            'tips' => ['As mensagens de WhatsApp entram na fila **WhatsApp a enviar** para alguém da loja enviar.'],
        ],

        'fluxo-de-caixa' => [
            'title' => 'Fluxo de caixa',
            'section' => 'Financeiro',
            'icon' => 'graph-up',
            'can' => ['financeiro.ver'],
            'routes' => ['finance.cash-flow'],
            'intro' => 'Entradas e saídas de dinheiro dia a dia, com o que já aconteceu e o que está previsto.',
            'steps' => [
                'Escolha o período.',
                'As entradas são os recebimentos das vendas (dinheiro, Pix, cartões pela data em que caem na conta e parcelas do crediário). As saídas são as contas a pagar.',
                'Use o saldo do período para planejar pagamentos e compras.',
            ],
            'tips' => ['Para os cartões caírem na data certa, mantenha a taxa e o prazo de cada forma em **Formas de pagamento**.'],
        ],

        'contas-a-pagar' => [
            'title' => 'Contas a pagar',
            'section' => 'Financeiro',
            'icon' => 'receipt-cutoff',
            'can' => ['financeiro.ver'],
            'routes' => ['finance.payables', 'finance.payables.*', 'finance.categories'],
            'intro' => 'Despesas da loja: aluguel, fornecedores, salários, impostos e outras.',
            'steps' => [
                'Filtre por situação (em aberto, vencidas, pagas), categoria e período.',
                ['text' => 'Clique em **Nova conta**. Escolha se é conta única, compra parcelada (divide o valor) ou conta fixa mensal (repete o valor), e clique em **Salvar**.', 'can' => ['financeiro.pagar']],
                ['text' => 'Com mais de uma loja, informe a **Loja** da despesa ou deixe como geral da rede.', 'can' => ['financeiro.pagar']],
                ['text' => 'Para registrar o pagamento, escolha a data em **Pagar com data de** e clique em **Pagar** na conta. Se errar, use **Desfazer**.', 'can' => ['financeiro.pagar']],
                ['text' => 'Organize as despesas em **Categorias**.', 'can' => ['financeiro.pagar']],
            ],
            'tips' => ['Compras de mercadoria aparecem no fluxo de caixa, mas o custo só entra no resultado quando as peças são vendidas.'],
        ],

        'recebimentos' => [
            'title' => 'Recebimentos de cartão',
            'section' => 'Financeiro',
            'icon' => 'credit-card-2-front',
            'can' => ['financeiro.ver'],
            'routes' => ['finance.receivables'],
            'intro' => 'Valores de cartão (e outras formas a prazo) que a maquininha vai depositar, já com a taxa descontada.',
            'steps' => [
                'Veja o que cai em cada data e o valor líquido.',
                'Confira no extrato do banco o que entrou. Marque os recebimentos (ou use **Selecionar todos até hoje**) e clique em **Confirmar recebimento**.',
            ],
            'tips' => ['Diferenças entre o previsto e o extrato podem indicar taxa cadastrada errada.'],
        ],

        'relatorio-vendas' => [
            'title' => 'Relatório de vendas',
            'section' => 'Relatórios',
            'icon' => 'bar-chart',
            'can' => ['relatorios.vendas'],
            'routes' => ['reports.sales'],
            'intro' => 'Vendas do período por forma de pagamento, por vendedor e por categoria.',
            'steps' => [
                'Escolha o período e, se houver várias lojas, a loja.',
                'Use **Imprimir** para guardar ou levar a reunião.',
            ],
            'tips' => ['Vendas líquidas já descontam as devoluções.'],
        ],

        'relatorio-produtos' => [
            'title' => 'Produtos e estoque',
            'section' => 'Relatórios',
            'icon' => 'box2-heart',
            'can' => ['relatorios.produtos'],
            'routes' => ['reports.products'],
            'intro' => 'Produtos mais vendidos e estoque parado, para decidir o que repor e o que liquidar.',
            'steps' => [
                'Escolha o período para ver os **Mais vendidos**.',
                'Em **Estoque parado**, veja as peças sem venda há muitos dias: boas candidatas a promoção.',
            ],
            'tips' => [],
        ],

        'relatorio-resultado' => [
            'title' => 'Resultado (lucro ou prejuízo)',
            'section' => 'Relatórios',
            'icon' => 'clipboard-data',
            'can' => ['relatorios.resultado'],
            'routes' => ['reports.result'],
            'intro' => 'Demonstrativo do período: vendas, custo das peças, taxas, despesas e lucro.',
            'steps' => [
                'Escolha o período (por exemplo, **Mês anterior**).',
                'Leia de cima para baixo: vendas líquidas, menos custo e taxas = lucro bruto; menos despesas = resultado.',
                'Com várias lojas, escolha a loja para ver o resultado dela. Nesse caso entram só as despesas lançadas para aquela loja.',
            ],
            'tips' => ['É um resultado gerencial, sem impostos sobre o lucro. Para a contabilidade oficial, consulte o contador.'],
        ],

        'comissoes' => [
            'title' => 'Comissões',
            'section' => 'Relatórios',
            'icon' => 'percent',
            'can' => ['comissoes.ver', 'comissoes.ver_propria'],
            'routes' => ['reports.commissions'],
            'intro' => 'Comissão de cada vendedor sobre as vendas líquidas do mês.',
            'steps' => [
                'Escolha o mês.',
                ['text' => 'Você vê a sua comissão: vendas, devoluções e o valor a receber.', 'can' => ['comissoes.ver_propria']],
                ['text' => 'Para encerrar o mês, use **Fechar mês**: a comissão de cada vendedor vira uma conta a pagar. Se precisar corrigir, use **Reabrir mês**.', 'can' => ['comissoes.fechar']],
            ],
            'tips' => ['O percentual de cada vendedor fica no cadastro do usuário.'],
        ],

        'produtos' => [
            'title' => 'Produtos',
            'section' => 'Catálogo',
            'icon' => 'tags',
            'can' => ['produtos.ver'],
            'routes' => ['products.index', 'products.create', 'products.edit'],
            'intro' => 'Cadastro dos produtos, com fotos, variações (tamanho, cor, voltagem, capacidade...) e preços.',
            'steps' => [
                'Busque por nome, código ou SKU e filtre por categoria.',
                ['text' => 'Clique em **Novo**. Preencha os **Dados do produto** (nome, categoria, marca, preço de venda e custo).', 'can' => ['produtos.criar']],
                ['text' => 'Em **Imagens**, envie as fotos e escolha a principal.', 'can' => ['produtos.criar', 'produtos.editar']],
                ['text' => 'Em **Variações**, marque os valores de cada variação (por exemplo, tamanhos e cores, ou voltagens) e clique em **Gerar combinações**. Use **Aplicar preço base a todas** e, num produto novo, **Estoque inicial em todas**. Clique em **Salvar**.', 'can' => ['produtos.criar', 'produtos.editar']],
            ],
            'tips' => [
                'Cada combinação (por exemplo, M / Azul ou 220 V / Preto) é um SKU com código de barras próprio.',
                'Produto sem variação (um cabo, um kit de agulhas) é cadastrado sem marcar nenhuma variação.',
                'Para cadastrar muitos produtos de uma vez, use **Importar**.',
            ],
        ],

        'importar-produtos' => [
            'title' => 'Importar produtos por planilha',
            'section' => 'Catálogo',
            'icon' => 'file-earmark-spreadsheet',
            'can' => ['produtos.criar'],
            'routes' => ['products.import'],
            'intro' => 'Cadastro em lote: cada linha da planilha é uma variação (grade) do produto.',
            'steps' => [
                'Clique em **Baixar planilha modelo** e preencha no Excel ou Google Planilhas, mantendo os títulos das colunas.',
                'Salve como CSV e envie o arquivo. Clique em **Conferir planilha**: o sistema aponta os erros linha a linha.',
                'Corrija o que for preciso e clique em **Importar**.',
            ],
            'tips' => ['Linhas com o mesmo nome de produto viram variações do mesmo produto.'],
        ],

        'cadastros' => [
            'title' => 'Categorias, marcas e variações',
            'section' => 'Catálogo',
            'icon' => 'diagram-3',
            'can' => ['cadastros.ver'],
            'routes' => ['categories.index', 'brands.index', 'attributes.index'],
            'intro' => 'Listas usadas no cadastro de produtos e nos filtros dos relatórios.',
            'steps' => [
                '**Categorias**: organize em categorias e subcategorias (por exemplo, Feminino > Vestidos, Informática > Cabos ou Fitas > Cetim).',
                '**Marcas**: as marcas que a loja trabalha.',
                '**Variações**: atributos como Tamanho, Cor, Voltagem, Capacidade ou Largura, com seus valores. Crie as que a sua loja usar e use **Adicionar** para incluir um valor novo.',
            ],
            'tips' => ['Itens já usados em produtos não podem ser excluídos; desative-os quando deixarem de ser usados.'],
        ],

        'formas-pagamento' => [
            'title' => 'Formas de pagamento',
            'section' => 'Catálogo',
            'icon' => 'credit-card',
            'can' => ['cadastros.ver'],
            'routes' => ['payment-methods.index'],
            'intro' => 'Formas aceitas no caixa e as condições de cada uma.',
            'steps' => [
                'Clique em **Editar** na forma de pagamento.',
                'Informe a taxa da maquininha, o prazo em dias para o dinheiro cair na conta e, no crédito, o número máximo de parcelas.',
                'No crediário, defina a multa e os juros por atraso.',
            ],
            'tips' => ['Essas informações alimentam o fluxo de caixa, os recebimentos de cartão e o resultado.'],
        ],

        'fornecedores' => [
            'title' => 'Fornecedores',
            'section' => 'Catálogo',
            'icon' => 'truck',
            'can' => ['fornecedores.ver'],
            'routes' => ['suppliers.*'],
            'intro' => 'Cadastro de quem vende mercadoria para a loja.',
            'steps' => [
                ['text' => 'Clique em **Novo**, preencha nome, contato, telefone/WhatsApp e e-mail e clique em **Salvar**.', 'can' => ['fornecedores.criar']],
                'O fornecedor é escolhido no cadastro do produto e na entrada de mercadorias.',
            ],
            'tips' => [],
        ],

        'saldos' => [
            'title' => 'Saldos de estoque',
            'section' => 'Estoque',
            'icon' => 'boxes',
            'can' => ['estoque.ver'],
            'routes' => ['stock.index', 'stock.variant'],
            'intro' => 'Quanto há de cada peça (SKU), o que está reservado e o que está disponível para venda.',
            'steps' => [
                'Busque por nome, SKU ou código de barras e filtre por categoria.',
                'Em **Situação**, veja só o estoque baixo ou as peças sem estoque.',
                'Com várias lojas, escolha a loja. Quem vê todas as lojas pode escolher a **rede** inteira.',
                'Clique no SKU para ver o saldo por loja, todas as movimentações e o histórico de custo.',
            ],
            'tips' => ['Reservado é o que está em condicional ou em uma venda em andamento: não pode ser vendido para outra pessoa.'],
        ],

        'entrada' => [
            'title' => 'Entrada de mercadorias',
            'section' => 'Estoque',
            'icon' => 'box-arrow-in-down',
            'can' => ['estoque.entrada'],
            'routes' => ['stock.entry'],
            'intro' => 'Registro da mercadoria que chegou do fornecedor.',
            'steps' => [
                'Escolha o fornecedor e informe o número da nota.',
                'Inclua cada peça com a quantidade e o custo unitário.',
                'Clique em **Registrar entrada**. O estoque e o custo das peças são atualizados.',
            ],
            'tips' => [
                'A entrada é feita na loja em que você está trabalhando.',
                'Depois da entrada, imprima as etiquetas em **Etiquetas > Carregar de uma entrada de mercadorias**.',
                'A conta a pagar da nota é lançada à parte, em **Contas a pagar**.',
            ],
        ],

        'etiquetas' => [
            'title' => 'Etiquetas',
            'section' => 'Estoque',
            'icon' => 'upc-scan',
            'can' => ['produtos.ver'],
            'routes' => ['stock.labels'],
            'intro' => 'Impressão de etiquetas com preço e código de barras.',
            'steps' => [
                'Busque e inclua as peças, ou use **Carregar de uma entrada de mercadorias** para trazer tudo o que chegou.',
                'Ajuste a quantidade de etiquetas de cada peça.',
                'Clique em **Imprimir etiquetas**. Use **Limpar** para começar de novo.',
            ],
            'tips' => ['Na janela de impressão, deixe a escala em 100% para o código de barras sair no tamanho certo.'],
        ],

        'movimentar' => [
            'title' => 'Movimentar estoque',
            'section' => 'Estoque',
            'icon' => 'arrow-left-right',
            'can' => ['estoque.movimentar', 'estoque.ajustar'],
            'routes' => ['stock.movement'],
            'intro' => 'Saídas e ajustes que não são venda: peça com defeito, perda, uso interno, correção de saldo.',
            'steps' => [
                'Escolha a peça (SKU).',
                'Escolha o tipo de movimentação, a quantidade e escreva o motivo.',
                'Clique em **Registrar**. A movimentação fica no histórico e não pode ser apagada; para corrigir, faça outra movimentação.',
            ],
            'tips' => ['Para acertar o estoque de muitas peças de uma vez, faça um **Inventário**.'],
        ],

        'historico-estoque' => [
            'title' => 'Histórico de estoque',
            'section' => 'Estoque',
            'icon' => 'clock-history',
            'can' => ['estoque.ver'],
            'routes' => ['stock.history'],
            'intro' => 'Todas as entradas e saídas de estoque: vendas, devoluções, entradas, ajustes, inventários e transferências.',
            'steps' => [
                'Filtre por peça, tipo de movimentação, usuário e período.',
                'Cada linha mostra quem fez, quando, o motivo e o saldo depois da movimentação.',
            ],
            'tips' => [],
        ],

        'inventario' => [
            'title' => 'Inventário',
            'section' => 'Estoque',
            'icon' => 'clipboard-check',
            'can' => ['estoque.inventario'],
            'routes' => ['stock.inventories', 'stock.inventory'],
            'intro' => 'Contagem física do estoque para corrigir diferenças.',
            'steps' => [
                'Escolha a **Abrangência** (a loja toda ou uma categoria) e clique em **Iniciar contagem**.',
                'Conte as peças e informe as quantidades. O leitor de código de barras ajuda a contar mais rápido.',
                'Ao terminar, clique em **Concluir**. O sistema ajusta o estoque pelas quantidades contadas e registra as diferenças.',
            ],
            'tips' => [
                'Evite vender peças da área que está sendo contada até concluir o inventário.',
                'Cada loja tem o seu inventário; ele vale para a loja em que você está trabalhando.',
            ],
        ],

        'transferencias' => [
            'title' => 'Transferências entre lojas',
            'section' => 'Estoque',
            'icon' => 'truck',
            'can' => ['estoque.transferir', 'estoque.ver'],
            'routes' => ['stock.transfers', 'stock.transfers.create'],
            'when' => 'multiple_stores',
            'intro' => 'Envio de peças de uma loja para outra.',
            'steps' => [
                ['text' => 'Clique em **Nova**, escolha a loja de destino e inclua as peças. Clique em **Enviar transferência**: as peças saem do estoque da sua loja e ficam "a caminho".', 'can' => ['estoque.transferir']],
                ['text' => 'Quando as peças chegarem, alguém da loja de destino abre a transferência e clica em **Receber**. Só então elas entram no estoque daquela loja.', 'can' => ['estoque.transferir']],
                ['text' => 'Se o envio não aconteceu, a loja de origem pode **Cancelar**: as peças voltam para ela.', 'can' => ['estoque.transferir']],
                'Use **Ver peças** para conferir o que foi enviado.',
            ],
            'tips' => ['O número amarelo no menu mostra quantas transferências estão a caminho da sua loja.'],
        ],

        'lojas' => [
            'title' => 'Lojas',
            'section' => 'Administração',
            'icon' => 'shop-window',
            'can' => ['lojas.gerir'],
            'routes' => ['stores.index'],
            'intro' => 'Cadastro das lojas (filiais) e de quem trabalha em cada uma.',
            'steps' => [
                'O quadro do topo mostra o seu plano: quantas lojas e usuários estão em uso e quantos o plano permite.',
                'Clique em **Nova loja** (ou **Editar**), preencha nome, código, telefone, endereço e cidade.',
                'Marque a equipe da loja: essas pessoas trabalham nela e veem o estoque, o caixa e as vendas de lá.',
                'Clique em **Salvar**.',
            ],
            'tips' => [
                'Gerentes e administradores veem todas as lojas e escolhem a loja no seletor do topo.',
                'Para ter mais lojas, fale com a DevZero1 sobre ampliar o plano.',
            ],
        ],

        'usuarios' => [
            'title' => 'Usuários',
            'section' => 'Administração',
            'icon' => 'people',
            'can' => ['usuarios.ver'],
            'routes' => ['users.*'],
            'intro' => 'Pessoas que acessam o sistema e o que cada uma pode fazer.',
            'steps' => [
                ['text' => 'Clique em **Novo**, preencha nome e e-mail e crie uma senha provisória: a pessoa troca a senha no primeiro acesso.', 'can' => ['usuarios.criar']],
                ['text' => 'Escolha os **Perfis** (por exemplo, Vendedor). Se precisar, marque **Permissões adicionais** só para essa pessoa. Clique em **Salvar**.', 'can' => ['usuarios.criar', 'usuarios.editar']],
                ['text' => 'Quando alguém sair da equipe, use **Bloquear** e informe o motivo: as sessões abertas são encerradas na hora. **Desbloquear** devolve o acesso.', 'can' => ['usuarios.bloquear']],
            ],
            'tips' => [
                'Não compartilhe usuários: cada pessoa com o seu, para a auditoria mostrar quem fez cada coisa.',
                'O plano pode limitar a quantidade de usuários ativos (administradores não contam). Usuários bloqueados liberam a vaga.',
            ],
        ],

        'perfis' => [
            'title' => 'Perfis de acesso',
            'section' => 'Administração',
            'icon' => 'shield-lock',
            'can' => ['perfis.ver'],
            'routes' => ['roles.*'],
            'intro' => 'Conjuntos de permissões (Gerente, Vendedor, Estoquista...) aplicados aos usuários.',
            'steps' => [
                ['text' => 'Clique em **Novo perfil** ou abra um perfil existente.', 'can' => ['perfis.criar', 'perfis.editar']],
                ['text' => 'Em **Permissões por módulo**, marque o que o perfil pode fazer e clique em **Salvar**. A mudança vale para todos os usuários com esse perfil.', 'can' => ['perfis.criar', 'perfis.editar']],
            ],
            'tips' => ['Dê a cada perfil só o necessário para o trabalho. Permissões que mostram custos e lucro devem ficar com quem administra.'],
        ],

        'auditoria' => [
            'title' => 'Auditoria',
            'section' => 'Administração',
            'icon' => 'journal-text',
            'can' => ['auditoria.ver'],
            'routes' => ['audit.index'],
            'intro' => 'Registro de quem fez o quê e quando: acessos, vendas, cancelamentos, alterações de cadastro e de permissões.',
            'steps' => [
                'Filtre por evento, usuário e período.',
                'Use **Limpar filtros** para voltar à lista completa.',
            ],
            'tips' => ['Os registros não podem ser alterados nem apagados.'],
        ],

        'sessoes' => [
            'title' => 'Sessões',
            'section' => 'Minha conta',
            'icon' => 'laptop',
            'can' => [],
            'routes' => ['sessions.index'],
            'intro' => 'Aparelhos e navegadores em que o seu usuário está conectado.',
            'steps' => [
                'Confira a lista de acessos abertos.',
                'Se não reconhecer algum, clique em **Encerrar** e troque a sua senha.',
            ],
            'tips' => [],
        ],

        'alterar-senha' => [
            'title' => 'Alterar senha',
            'section' => 'Minha conta',
            'icon' => 'key',
            'can' => [],
            'routes' => ['account.password'],
            'intro' => 'Troca da sua senha de acesso.',
            'steps' => [
                'Digite a senha atual e a nova senha duas vezes.',
                'Clique em **Salvar nova senha**. Os outros aparelhos conectados são desconectados.',
            ],
            'tips' => ['Use uma senha só sua, com letras e números, e não anote em lugar visível.'],
        ],
    ],
];
