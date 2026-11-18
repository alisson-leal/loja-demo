# Loja Demo - Sistema de Gestão Comercial (DevZero1)

Loja de demonstração do sistema de gestão comercial da **DevZero1**: ERP + PDV para varejo de moda,
com dados **fictícios** de uma loja de moda infantil para apresentar o sistema a clientes.

Módulos: painel, usuários e perfis de acesso, auditoria, catálogo com variações por SKU, fornecedores,
estoque e inventário, frente de caixa, controle de caixa, trocas e devoluções, clientes (CRM),
marketing (campanhas, fila de WhatsApp, aniversários e agradecimento pela compra, com consentimento LGPD),
financeiro (contas a pagar, recebimentos de cartão com taxas, fluxo de caixa), relatórios gerenciais,
comissão de vendedores com fechamento mensal, etiquetas com código de barras (EAN-13 ou Code 128),
venda condicional (cliente leva para provar, com estoque reservado até o acerto), crediário próprio (carnê, com limite por cliente,
multa e juros por atraso) e importação de produtos por planilha CSV, além do estoque inicial direto na grade do produto.

Segmento (`LOJA_SEGMENTO`): `infantil`, `adulto` ou `misto`. Em loja adulta somem os recursos de filhos dos clientes.

Tecnologias: PHP 8.3, Laravel 13, Livewire 4, Bootstrap 5, MySQL.

## Modo demonstração

Com `DEMO_MODE=true` no `.env`:

- `php artisan migrate --seed` gera, pelo `DemoDataSeeder`, cerca de 6 meses de operação até ontem:
  produtos, estoque, clientes, vendas, caixas, devoluções, contas, recebimentos e campanhas.
  Tudo passa pelos serviços do sistema, então estoque, caixa e financeiro fecham entre si.
- A tela de login mostra os acessos de teste (senha `DEMO_PASSWORD`, padrão `demo1234`):

| Perfil | E-mail |
|---|---|
| Administrador | `admin@lojademo.test` |
| Gerente | `gerente@lojademo.test` |
| Vendedor | `vendedora@lojademo.test` |
| Estoquista | `estoque@lojademo.test` |

- Os usuários de teste não podem ter senha nem dados alterados (são compartilhados pelos visitantes).
- Nenhum e-mail é enviado de verdade e o WhatsApp abre sem destinatário (os telefones são fictícios).
- `php artisan demo:resetar` apaga tudo e gera os dados de novo. O agendador faz isso todo dia em `DEMO_RESET_AT` (padrão 03:00).

Sem `DEMO_MODE`, o sistema se comporta como uma instalação normal: o seeder cria só o administrador de `ADMIN_EMAIL`.

## Ambiente local

As ferramentas portáteis ficam em `D:\LeleKids-Tools`. Em cada terminal novo:

```powershell
. D:\LeleKids-Tools\env.ps1
D:\LeleKids-Tools\mysql-start.ps1       # MySQL em 127.0.0.1:3307
php artisan serve --port=8010           # http://127.0.0.1:8010
```

Instalação em outra máquina:

```bash
composer install
npm install && npm run build
cp .env.example .env              # preencha o banco
php artisan key:generate
php artisan migrate --seed        # gera a loja de demonstração
```

## Testes

```powershell
php artisan test
```

Os testes usam o banco MySQL `lojademo_test` e apagam o conteúdo dele a cada execução.

## Organização

| Local | Conteúdo |
|---|---|
| `app/Services` | Regras de negócio. As telas apenas chamam estes serviços. |
| `app/Livewire` | Telas administrativas. |
| `config/loja.php` | Dados da loja, modo demonstração, catálogo de módulos e permissões, perfis padrão. |
| `database/seeders/DemoDataSeeder.php` | Gerador dos dados fictícios. |
| `deploy/` | Scripts de instalação e atualização na VPS e configuração do Nginx. |

## Publicação

Os arquivos compilados (`public/build`) ficam no repositório, então o servidor não precisa de Node.
**Antes de cada commit que mexa em `resources/`, rode `npm run build`.**
