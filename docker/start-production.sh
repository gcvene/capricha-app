#!/bin/sh
set -e

# Generate config.php from environment variables
cat > /var/www/html/config.php << EOF
<?php
class Config
{
    const BASE_URL = '${APP_URL:-http://localhost}';
    const LANGUAGE = '${APP_LANGUAGE:-portuguese-br}';
    const DEBUG_MODE = false;

    const DB_HOST = '${DB_HOST:-mysql}';
    const DB_NAME = '${DB_NAME:-capricha}';
    const DB_USERNAME = '${DB_USERNAME:-user}';
    const DB_PASSWORD = '${DB_PASSWORD:-password}';

    const GOOGLE_SYNC_FEATURE = false;
    const GOOGLE_CLIENT_ID = '';
    const GOOGLE_CLIENT_SECRET = '';
}
EOF

# Set correct permissions on storage
chown -R www-data:www-data /var/www/html/storage
chmod -R 755 /var/www/html/storage

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
