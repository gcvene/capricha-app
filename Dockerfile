# Stage 1: Build frontend assets
FROM node:18-alpine AS node-builder
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY assets/ ./assets/
COPY gulpfile.js babel.config.json ./
RUN npx gulp compile

# Stage 2: Production runtime (PHP-FPM + Nginx via supervisord)
FROM php:8.4-fpm-alpine

# install-php-extensions handles Alpine package resolution and skips pre-compiled extensions
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions \
    && install-php-extensions \
        gd \
        mysqli \
        pdo_mysql \
        intl \
        zip \
        bcmath \
        exif

RUN apk add --no-cache nginx supervisor rclone dcron mariadb-client

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .
COPY --from=node-builder /app/assets/vendor ./assets/vendor
COPY --from=node-builder /app/assets/js ./assets/js
COPY --from=node-builder /app/assets/css ./assets/css

RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress

RUN chown -R www-data:www-data storage \
    && chmod -R 755 storage

COPY docker/nginx/nginx.prod.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/start-production.sh /usr/local/bin/start-production
COPY docker/backup.sh /usr/local/bin/backup.sh
COPY docker/crontab /etc/cron.d/capricha-backup
RUN chmod +x /usr/local/bin/start-production /usr/local/bin/backup.sh \
    && chmod 0644 /etc/cron.d/capricha-backup

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/start-production"]
