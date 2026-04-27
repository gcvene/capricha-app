#!/bin/sh
set -e

# Validate only what is strictly needed to generate config.php and start the app.
# Asaas/WhatsApp secrets are validated lazily when those features are actually used.
for var in APP_URL DB_HOST DB_PASSWORD; do
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

# Set correct permissions on storage (mounted volume may be owned by root on first boot)
chown -R www-data:www-data /var/www/html/storage
chmod -R 755 /var/www/html/storage

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
