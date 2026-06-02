#!/usr/bin/env bash
# Dump completo da MariaDB do container local (revskin .env → clinicaweb_mysql_local).
# Saída em storage/backups/ (ignorada pelo git).

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

CONTAINER="${MYSQL_DOCKER_CONTAINER:-clinicaweb_mysql_local}"

if ! docker ps --format '{{.Names}}' | grep -qx "$CONTAINER"; then
  echo "Container não está a correr: $CONTAINER" >&2
  echo "Lista: docker ps --filter name=$CONTAINER" >&2
  exit 1
fi

if [[ ! -f .env ]]; then
  echo "Falta .env em $ROOT" >&2
  exit 1
fi

# shellcheck disable=SC1090
set -a
# Carrega só variáveis DB_* (valores sem aspas especiais no .env típico)
while IFS= read -r line; do
  [[ "$line" =~ ^DB_ ]] || continue
  [[ "$line" =~ ^# ]] && continue
  export "${line?}"
done < <(grep -E '^DB_(CONNECTION|HOST|PORT|DATABASE|USERNAME|PASSWORD)=' .env)
set +a

OUT_DIR="$ROOT/storage/backups"
mkdir -p "$OUT_DIR"
STAMP="$(date +%Y%m%d-%H%M%S)"
OUT_FILE="$OUT_DIR/${DB_DATABASE}_full_${STAMP}.sql"

echo "Dump → $OUT_FILE (container=$CONTAINER)"

docker exec "$CONTAINER" mariadb-dump \
  -h 127.0.0.1 -P 3306 \
  -u "$DB_USERNAME" \
  -p"$DB_PASSWORD" \
  --single-transaction \
  --routines \
  --triggers \
  --default-character-set=utf8mb4 \
  "$DB_DATABASE" > "$OUT_FILE"

echo "Feito ($(wc -l < "$OUT_FILE" | tr -d ' ') linhas). Importar na prod com phpMyAdmin ou:"
echo "  mysql -u USER -p -h HOST NOME_BD < \"$OUT_FILE\""
