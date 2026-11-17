#!/usr/bin/env bash
# Instala ou atualiza o sistema com a versão mais recente do GitHub.
#
# Uso:
#   sudo -u lelekids bash /var/www/lelekids/app/deploy/atualizar.sh
#
# Na primeira execução o seeder cria o administrador e mostra a senha
# provisória uma única vez. Nas seguintes ele só acrescenta o que faltar.
set -euo pipefail
cd "$(dirname "$0")/.."

if [ ! -f .env ]; then
    echo "Falta o .env. Rode antes deploy/configurar-env.sh."
    exit 1
fi

# Arquivos legíveis pelo grupo, para o Nginx entregar public/.
umask 027

if php artisan --version > /dev/null 2>&1 && [ -d vendor ]; then
    php artisan down --retry=15 || true
fi
trap 'php artisan up > /dev/null 2>&1 || true' EXIT

git pull --ff-only
composer install --no-dev --optimize-autoloader --no-interaction --no-progress
php artisan migrate --force
php artisan db:seed --force
php artisan optimize

php artisan up
trap - EXIT

echo
echo "Sistema atualizado: $(git log -1 --format='%h %s')"
