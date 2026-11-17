# Publicação na VPS (Locaweb)

## Servidor

| Item | Valor |
|---|---|
| Sistema | Ubuntu 24.04, 1 GB de RAM, 2 vCPU, 40 GB, swap de 1 GB |
| Acesso | Usuário administrador `lealvale`, somente com chave SSH. Root e senha bloqueados. |
| Firewall | ufw: OpenSSH, 80 e 443 |
| Programas | Nginx 1.24, PHP 8.3-FPM, MySQL 8.0 (configuração de baixo consumo), Composer 2 |
| Usuário do sistema | `lelekids` (sem login), pasta `/var/www/lelekids` |
| Código | `/var/www/lelekids/app`, clonado do GitHub (`alisson-leal/lelekids-sistema`, privado) |
| PHP | Pool próprio `/etc/php/8.3/fpm/pool.d/lelekids.conf`, socket `/run/php/lelekids.sock` |
| Banco | `lelekids`, usuário `lelekids@localhost` |
| Endereço | `https://sistema.lelekidsmodas.com.br` (DNS na Locaweb, registro A `sistema`) |

O servidor lê o repositório com uma **deploy key somente leitura** (`/var/www/lelekids/.ssh/id_ed25519`).
Os arquivos compilados (`public/build`) vão no repositório: não há Node no servidor.

## Instalação (uma vez)

```bash
sudo -u lelekids bash /var/www/lelekids/app/deploy/configurar-env.sh sistema.lelekidsmodas.com.br EMAIL_DO_ADMIN
sudo -u lelekids bash /var/www/lelekids/app/deploy/atualizar.sh   # mostra a senha provisória do administrador
sudo usermod -aG lelekids www-data                                  # Nginx lê a pasta public/
sudo cp /var/www/lelekids/app/deploy/nginx-lelekids.conf /etc/nginx/sites-available/lelekids
sudo ln -s /etc/nginx/sites-available/lelekids /etc/nginx/sites-enabled/lelekids
sudo nginx -t && sudo systemctl restart nginx
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d sistema.lelekidsmodas.com.br               # HTTPS (renovação automática)
```

E-mail (SMTP da Locaweb, conta `atendimento@lelekidsmodas.com.br`). A senha é pedida sem aparecer na tela:

```bash
sudo -u lelekids bash /var/www/lelekids/app/deploy/configurar-email.sh atendimento@lelekidsmodas.com.br
sudo -u lelekids php /var/www/lelekids/app/artisan email:testar SEU_EMAIL
```

O padrão é `email-ssl.com.br`, porta 465. Para usar outro servidor ou porta, passe-os depois da conta.
Os e-mails de campanha saem aos poucos: no máximo `MARKETING_EMAIL_PER_HOUR` por hora (padrão 100).
Esse valor deve ficar abaixo do limite de envio do plano de e-mail da Locaweb.

Tarefa agendada (`sudo crontab -u lelekids -e`):

```
* * * * * cd /var/www/lelekids/app && php artisan schedule:run >> /dev/null 2>&1
```

Ela envia os e-mails de marketing respeitando o limite por hora e prepara as mensagens de aniversário (de hora em hora, das 8h às 20h).
Também libera reservas de estoque vencidas e faz limpezas diárias.
Não há worker permanente, para economizar memória.

## Atualizar o sistema

1. No computador de desenvolvimento: `npm run build` (se mudou algo em `resources/`), testes, commit e `git push`.
2. Na VPS:

```bash
sudo -u lelekids bash /var/www/lelekids/app/deploy/atualizar.sh
```

O script coloca o sistema em manutenção, baixa a versão nova, instala dependências, roda migrações e seeders
(que só acrescentam o que falta), refaz os caches e reabre o sistema.

## Onde olhar quando algo der errado

| O quê | Onde |
|---|---|
| Erros da aplicação | `/var/www/lelekids/app/storage/logs/laravel-AAAA-MM-DD.log` (30 dias) |
| Erros do Nginx | `/var/log/nginx/lelekids.error.log` |
| Erros do PHP-FPM | `sudo journalctl -u php8.3-fpm` |
| Memória | `free -h` |

## Conferência final

- [ ] `https://sistema.lelekidsmodas.com.br` abre o login e `http://` redireciona para `https://`.
- [ ] `https://sistema.lelekidsmodas.com.br/.env` não abre o arquivo.
- [ ] O login do administrador exige a troca da senha provisória.
- [ ] O envio de foto de produto funciona.
- [ ] `email:testar` entrega o e-mail de teste e a recuperação de senha chega.
- [ ] O link "Não quero mais receber e-mails" abre a página de descadastro em `https://`.
