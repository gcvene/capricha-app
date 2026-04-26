#!/bin/sh
set -e

# Validate required secrets before starting
for var in APP_URL DB_HOST DB_PASSWORD CRYPTO_KEY ASAAS_API_KEY ASAAS_WEBHOOK_TOKEN WHATSAPP_APP_SECRET WHATSAPP_WEBHOOK_VERIFY_TOKEN; do
    eval val=\$$var
    if [ -z "$val" ]; then
        echo "ERROR: Required environment variable $var is not set." >&2
        exit 1
    fi
done

# Generate config.php from environment variables (file is in .gitignore — never committed)
cat > /var/www/html/config.php << EOF
<?php
class Config
{
    const BASE_URL = '${APP_URL}';
    const LANGUAGE = '${APP_LANGUAGE:-portuguese-br}';
    const DEBUG_MODE = false;

    const DB_HOST = '${DB_HOST}';
    const DB_NAME = '${DB_NAME:-capricha}';
    const DB_USERNAME = '${DB_USERNAME:-capricha}';
    const DB_PASSWORD = '${DB_PASSWORD}';

    const GOOGLE_SYNC_FEATURE = false;
    const GOOGLE_CLIENT_ID = '';
    const GOOGLE_CLIENT_SECRET = '';
}
EOF

# Set correct permissions on storage
chown -R www-data:www-data /var/www/html/storage
chmod -R 755 /var/www/html/storage

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
