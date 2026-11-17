#!/usr/bin/env bash
# Configura o envio de e-mails pelo SMTP da Locaweb no .env de produção.
#
# Uso (a senha é pedida sem aparecer na tela):
#   sudo -u lelekids bash /var/www/lelekids/app/deploy/configurar-email.sh atendimento@lelekidsmodas.com.br
#
# Servidor e porta podem ser trocados: ... configurar-email.sh CONTA [SERVIDOR] [PORTA]
set -euo pipefail
cd "$(dirname "$0")/.."

CONTA="${1:?Informe a conta de e-mail, ex.: atendimento@lelekidsmodas.com.br}"
SERVIDOR="${2:-email-ssl.com.br}"
PORTA="${3:-465}"

[ -f .env ] || { echo "Falta o .env."; exit 1; }

read -r -s -p "Senha da conta ${CONTA}: " SENHA
echo
[ -n "$SENHA" ] || { echo "Senha vazia. Nada foi alterado."; exit 1; }

definir() {
    local chave="$1" valor
    valor="$(printf '%s' "$2" | sed -e 's/[\\&|]/\\&/g')"
    if grep -q "^${chave}=" .env; then
        sed -i "s|^${chave}=.*|${chave}=${valor}|" .env
    else
        printf '%s=%s\n' "$chave" "$2" >> .env
    fi
}

# Aspas simples no .env: a senha é lida literalmente, mesmo com $ ou #.
if [[ "$SENHA" == *"'"* ]]; then
    echo "A senha contém aspas simples ('), que o .env não aceita. Troque a senha da conta e tente de novo."
    exit 1
fi

if [ "$PORTA" = "465" ]; then ESQUEMA=smtps; else ESQUEMA=smtp; fi

definir MAIL_MAILER smtp
definir MAIL_SCHEME "$ESQUEMA"
definir MAIL_HOST "$SERVIDOR"
definir MAIL_PORT "$PORTA"
definir MAIL_USERNAME "$CONTA"
definir MAIL_PASSWORD "'${SENHA}'"
definir MAIL_FROM_ADDRESS "\"${CONTA}\""
chmod 600 .env

php artisan config:cache > /dev/null
echo "E-mail configurado: ${CONTA} via ${SERVIDOR}:${PORTA}."
echo "Teste com: sudo -u lelekids php $(pwd)/artisan email:testar SEU_EMAIL"
