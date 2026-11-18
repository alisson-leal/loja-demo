#!/usr/bin/env bash
# Cria o .env de produção na VPS. Roda uma única vez, na instalação.
#
# Uso (como usuário do sistema):
#   sudo -u lojademo bash /var/www/lojademo/app/deploy/configurar-env.sh DOMINIO EMAIL_DO_ADMIN
#
# A senha do banco é lida de ../.db-password (criado na preparação da VPS),
# gravada no .env e o arquivo é apagado em seguida.
set -euo pipefail
cd "$(dirname "$0")/.."

DOMINIO="${1:?Informe o domínio, ex.: lojademo.devzero1.com.br}"
ADMIN_EMAIL="${2:?Informe o e-mail do administrador}"
ARQ_SENHA="../.db-password"

if [ -f .env ]; then
    echo "O arquivo .env já existe. Nada foi alterado."
    exit 1
fi
if [ ! -f "$ARQ_SENHA" ]; then
    echo "Não encontrei $ARQ_SENHA com a senha do banco."
    exit 1
fi

DB_SENHA="$(tr -d '\r\n' < "$ARQ_SENHA")"

umask 077
cp .env.example .env

definir() {
    local chave="$1" valor
    valor="$(printf '%s' "$2" | sed -e 's/[\\&|]/\\&/g')"
    if grep -q "^${chave}=" .env; then
        sed -i "s|^${chave}=.*|${chave}=${valor}|" .env
    else
        printf '%s=%s\n' "$chave" "$2" >> .env
    fi
}

definir APP_ENV production
definir APP_DEBUG false
definir APP_URL "https://${DOMINIO}"
definir APP_FORCE_HTTPS true
definir LOG_STACK daily
definir LOG_LEVEL warning
definir LOG_DAILY_DAYS 30
definir DB_PASSWORD "\"${DB_SENHA}\""
definir SESSION_SECURE_COOKIE true
definir SESSION_ENCRYPT true
definir MAIL_FROM_ADDRESS "\"nao-responda@${DOMINIO#*.}\""
definir ADMIN_EMAIL "$ADMIN_EMAIL"

definir APP_KEY "base64:$(php -r 'echo base64_encode(random_bytes(32));')"
chmod 600 .env
rm -f "$ARQ_SENHA"

echo
echo "Arquivo .env criado para https://${DOMINIO}."
echo "A senha do banco agora está só no .env (o arquivo .db-password foi apagado)."
