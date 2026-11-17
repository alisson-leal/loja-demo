#!/usr/bin/env bash
# Prepara a VPS para um sistema novo, ao lado dos que já existem:
# banco e usuário MySQL, pool PHP-FPM próprio, site no Nginx e tarefa agendada.
#
# Antes: usuário de sistema criado e código clonado em /var/www/SISTEMA/app.
# Uso (como administrador):
#   sudo bash /var/www/lojademo/app/deploy/preparar-vps.sh lojademo lojademo.demo.devzero1.com.br
#
# Pode ser repetido: o que já existe é mantido.
set -euo pipefail

APP="${1:?Informe o nome do sistema (o mesmo do usuário), ex.: lojademo}"
DOMINIO="${2:?Informe o domínio, ex.: lojademo.demo.devzero1.com.br}"
BASE="/var/www/${APP}"
CODIGO="${BASE}/app"

[ "$(id -u)" -eq 0 ] || { echo "Rode com sudo."; exit 1; }
id "$APP" > /dev/null 2>&1 || { echo "O usuário ${APP} não existe."; exit 1; }
[ -f "${CODIGO}/artisan" ] || { echo "Código não encontrado em ${CODIGO}."; exit 1; }
[[ "$APP" =~ ^[a-z][a-z0-9_]{1,30}$ ]] || { echo "Nome inválido: use letras minúsculas e números."; exit 1; }

echo "1/5 Banco de dados"
if [ -f "${CODIGO}/.env" ] && grep -q '^DB_PASSWORD=.' "${CODIGO}/.env"; then
    echo "    .env já tem a senha do banco: mantido."
else
    if [ ! -f "${BASE}/.db-password" ]; then
        openssl rand -base64 48 | tr -dc 'A-Za-z0-9' | head -c 28 > "${BASE}/.db-password"
        chown "${APP}:${APP}" "${BASE}/.db-password"
        chmod 600 "${BASE}/.db-password"
    fi
    SENHA="$(cat "${BASE}/.db-password")"
    mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${APP}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${APP}'@'localhost' IDENTIFIED BY '${SENHA}';
ALTER USER '${APP}'@'localhost' IDENTIFIED BY '${SENHA}';
GRANT ALL PRIVILEGES ON \`${APP}\`.* TO '${APP}'@'localhost';
SQL
    echo "    Banco ${APP} pronto. Senha guardada em ${BASE}/.db-password."
fi

echo "2/5 PHP-FPM (pool ${APP})"
cat > "/etc/php/8.3/fpm/pool.d/${APP}.conf" <<CONF
[${APP}]
user = ${APP}
group = ${APP}
listen = /run/php/${APP}.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660
pm = ondemand
pm.max_children = 4
pm.process_idle_timeout = 20s
pm.max_requests = 300
php_admin_value[memory_limit] = 128M
php_admin_value[upload_max_filesize] = 10M
php_admin_value[post_max_size] = 12M
CONF
php-fpm8.3 -t
systemctl reload php8.3-fpm

echo "3/5 Nginx"
sed -e "s/__DOMINIO__/${DOMINIO}/g" -e "s/__APP__/${APP}/g" "${CODIGO}/deploy/nginx-site.conf" > "/etc/nginx/sites-available/${APP}"
ln -sf "/etc/nginx/sites-available/${APP}" "/etc/nginx/sites-enabled/${APP}"
# O Nginx precisa ler a pasta public/ do sistema.
usermod -aG "${APP}" www-data
chmod 750 "${BASE}"
nginx -t
systemctl restart nginx

echo "4/5 Tarefa agendada"
echo "* * * * * cd ${CODIGO} && php artisan schedule:run >> /dev/null 2>&1" | crontab -u "${APP}" -
crontab -u "${APP}" -l

echo "5/5 Concluído."
echo
echo "Próximos passos:"
echo "  sudo -u ${APP} bash ${CODIGO}/deploy/configurar-env.sh ${DOMINIO} EMAIL_DO_ADMIN"
echo "  sudo -u ${APP} bash ${CODIGO}/deploy/atualizar.sh"
echo "  sudo certbot --nginx -d ${DOMINIO}"
