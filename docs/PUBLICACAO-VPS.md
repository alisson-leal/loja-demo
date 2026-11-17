# Publicação da Loja Demo na VPS

A demo roda na mesma VPS da Lelé Kids (Ubuntu 24.04, 1 GB de RAM), isolada dela:

| Item | Valor |
|---|---|
| Endereço | `https://lojademo.demo.devzero1.com.br` (DNS na Locaweb, zona `devzero1.com.br`, registro A `lojademo.demo`) |
| Usuário do sistema | `lojademo` (sem login), pasta `/var/www/lojademo` |
| Código | `/var/www/lojademo/app`, clonado do GitHub (`alisson-leal/loja-demo`, privado) com deploy key somente leitura |
| PHP | Pool `/etc/php/8.3/fpm/pool.d/lojademo.conf`, socket `/run/php/lojademo.sock`, até 4 processos |
| Banco | `lojademo`, usuário `lojademo@localhost` |
| Tarefa agendada | `crontab -u lojademo`: `schedule:run` a cada minuto, incluindo a restauração diária às 03:00 |

## Instalação (uma vez)

```bash
sudo useradd --system --create-home --home-dir /var/www/lojademo --shell /usr/sbin/nologin lojademo
sudo -u lojademo mkdir -p -m 700 /var/www/lojademo/.ssh
sudo -u lojademo ssh-keygen -t ed25519 -N "" -C "deploy-lojademo" -f /var/www/lojademo/.ssh/id_ed25519
sudo cat /var/www/lojademo/.ssh/id_ed25519.pub        # cadastrar como deploy key do repositório
sudo -u lojademo git clone git@github.com:alisson-leal/loja-demo.git /var/www/lojademo/app
sudo bash /var/www/lojademo/app/deploy/preparar-vps.sh lojademo lojademo.demo.devzero1.com.br
sudo -u lojademo bash /var/www/lojademo/app/deploy/configurar-env.sh lojademo.demo.devzero1.com.br admin@lojademo.test
sudo -u lojademo bash /var/www/lojademo/app/deploy/atualizar.sh      # gera os dados fictícios (alguns minutos)
sudo certbot --nginx -d lojademo.demo.devzero1.com.br
```

## Atualizar

```bash
sudo -u lojademo bash /var/www/lojademo/app/deploy/atualizar.sh
```

## Restaurar os dados agora

```bash
sudo -u lojademo php /var/www/lojademo/app/artisan demo:resetar --force
```

## Onde olhar quando algo der errado

| O quê | Onde |
|---|---|
| Erros da aplicação | `/var/www/lojademo/app/storage/logs/` |
| Erros do Nginx | `/var/log/nginx/lojademo.error.log` |
| Memória | `free -h` |
