#!/bin/sh
set -eu

TIMESTAMP=$(TZ=America/Sao_Paulo date +%Y%m%d_%H%M)
BACKUP_FILE="/tmp/capricha_${TIMESTAMP}.sql.gz"
RCLONE_CONF=$(mktemp /tmp/rclone_XXXXXX.conf)

cleanup() {
    rm -f "${BACKUP_FILE}" "${RCLONE_CONF}"
}
trap cleanup EXIT

echo "[backup] Iniciando dump — ${TIMESTAMP}"

# Config rclone gerada em runtime a partir das env vars (nunca persiste no repo)
cat > "${RCLONE_CONF}" << EOF
[r2]
type = s3
provider = Cloudflare
access_key_id = ${R2_ACCESS_KEY}
secret_access_key = ${R2_SECRET_KEY}
endpoint = ${R2_ENDPOINT}
region = auto
no_check_bucket = true
EOF
chmod 600 "${RCLONE_CONF}"

# Dump + compress
mysqldump \
    --host="${DB_HOST}" \
    --port="${DB_PORT:-3306}" \
    --user="${DB_USERNAME}" \
    --password="${DB_PASSWORD}" \
    --single-transaction \
    --routines \
    --triggers \
    "${DB_NAME}" | gzip > "${BACKUP_FILE}"

SIZE=$(du -h "${BACKUP_FILE}" | cut -f1)
echo "[backup] Dump concluído: ${SIZE}"

# Upload para R2
rclone copy "${BACKUP_FILE}" "r2:${R2_BUCKET}" \
    --config "${RCLONE_CONF}" \
    --stats-log-level NOTICE

echo "[backup] Upload concluído → r2:${R2_BUCKET}/capricha_${TIMESTAMP}.sql.gz"

# Remove backups com mais de 30 dias
rclone delete "r2:${R2_BUCKET}" \
    --config "${RCLONE_CONF}" \
    --min-age 30d \
    --stats-log-level NOTICE

echo "[backup] Limpeza de backups > 30 dias concluída"
