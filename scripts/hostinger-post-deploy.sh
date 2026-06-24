#!/usr/bin/env bash
# Pós-deploy na Hostinger: migrate, cache, symlink storage.
# Chamado pelo GitHub Actions após rsync. Também pode rodar manualmente via SSH.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="${HOSTINGER_APP_DIR:-$(cd "$SCRIPT_DIR/.." && pwd)}"
PUBLIC_HTML="${HOSTINGER_PUBLIC_HTML:-$(dirname "$APP_DIR")}"

if [ -n "${HOSTINGER_PHP:-}" ] && [ -x "${HOSTINGER_PHP}" ]; then
    PHP="${HOSTINGER_PHP}"
elif [ -x /opt/alt/php84/usr/bin/php ]; then
    PHP=/opt/alt/php84/usr/bin/php
elif [ -x /usr/bin/php84 ]; then
    PHP=/usr/bin/php84
elif [ -x /usr/bin/php ]; then
    PHP=/usr/bin/php
else
    PHP=php
fi

PHP_MAJOR=$("$PHP" -r 'echo PHP_MAJOR_VERSION;".".PHP_MINOR_VERSION;' 2>/dev/null || echo "unknown")
echo "    PHP=$PHP ($PHP_MAJOR)"

if [ "$PHP_MAJOR" = "unknown" ] || { [ "$PHP_MAJOR" != "8.4" ] && [ "$PHP_MAJOR" != "8.5" ]; }; then
    echo "ERRO: PHP $PHP_MAJOR em $PHP — requer >= 8.4."
    echo "      Hostinger: hPanel → Avançado → Configuração PHP → 8.4"
    echo "      Ou export HOSTINGER_PHP=/opt/alt/php84/usr/bin/php antes de rodar este script."
    exit 1
fi

echo "==> Post-deploy Revskin"
echo "    APP_DIR=$APP_DIR"
echo "    PUBLIC_HTML=$PUBLIC_HTML"

cd "$APP_DIR"

if [ ! -f .env ]; then
    echo "ERRO: .env não encontrado em $APP_DIR"
    echo "      Copie .env.example para .env e configure (ver DEPLOY_HOSTINGER.md §8)."
    exit 1
fi

$PHP artisan down --retry=60 2>/dev/null || true

$PHP artisan migrate --force
$PHP artisan optimize:clear
$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache

# URLs /storage/... resolvem em public_html/storage (document root)
rm -rf "$PUBLIC_HTML/storage"
ln -sfn "$APP_DIR/storage/app/public" "$PUBLIC_HTML/storage"

$PHP artisan storage:link --force 2>/dev/null || true

$PHP artisan up 2>/dev/null || true

echo "==> Post-deploy concluído."
