#!/usr/bin/env bash
# Envia um dump SQL do CLW2 para a Hostinger, onde o importador incremental vai lê-lo.
#
# O dump NÃO vai no pacote de deploy: `docs/` está no .gitignore (são ~14 MB de dado de
# paciente, que não devem entrar no git). O destino é `revskin/storage/app/legado/`, que fica
# dentro da pasta protegida por `.htaccess` (Require all denied) e é excluída do rsync do
# deploy — ou seja, o arquivo sobrevive aos deploys seguintes.
#
# Uso:
#   HOSTINGER_HOST=... HOSTINGER_USER=... HOSTINGER_PORT=... \
#     scripts/enviar-dump-legado.sh docs/clinicaweb/database/bkp_cw2_20260806.sql
#
# Host/usuário/porta são os mesmos secrets do deploy (GitHub → Settings → Secrets).
# A chave costuma ser ~/.ssh/revskin_hostinger (mude com SSH_KEY=...).
set -euo pipefail

DUMP="${1:-}"
if [ -z "$DUMP" ] || [ ! -f "$DUMP" ]; then
    echo "Uso: $0 <caminho-do-dump.sql>" >&2
    exit 1
fi

: "${HOSTINGER_HOST:?defina HOSTINGER_HOST}"
: "${HOSTINGER_USER:?defina HOSTINGER_USER}"
PORT="${HOSTINGER_PORT:-65002}"
SSH_KEY="${SSH_KEY:-$HOME/.ssh/revskin_hostinger}"
REMOTE_PATH="${HOSTINGER_REMOTE_PATH:-/home/u368085046/domains/clinicaweb.revskin.com.br/public_html}"
DEST_DIR="$REMOTE_PATH/revskin/storage/app/legado"

SSH_CMD="ssh -i $SSH_KEY -p $PORT -o StrictHostKeyChecking=accept-new"

echo "==> Criando $DEST_DIR"
$SSH_CMD "$HOSTINGER_USER@$HOSTINGER_HOST" "mkdir -p '$DEST_DIR' && chmod 750 '$DEST_DIR'"

echo "==> Enviando $(basename "$DUMP") ($(du -h "$DUMP" | cut -f1))"
rsync -avz --progress -e "$SSH_CMD" "$DUMP" "$HOSTINGER_USER@$HOSTINGER_HOST:$DEST_DIR/"

echo "==> Conferindo no servidor"
$SSH_CMD "$HOSTINGER_USER@$HOSTINGER_HOST" "ls -la '$DEST_DIR'"

cat <<EOF

==> Pronto. Falta apontar o .env de produção para essa pasta:

    LEGADO_SQL_PATH=$DEST_DIR

Edite $REMOTE_PATH/revskin/.env, acrescente a linha acima e rode:

    php artisan config:cache

Depois o dump aparece em Ferramentas → Importação CLW2 e no
'migration:importar-legado-incremental'.
EOF
