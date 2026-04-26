# Stage 1: Build frontend assets
FROM node:18-alpine AS node-builder
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY assets/ ./assets/
COPY gulpfile.js babel.config.json ./
RUN npx gulp compile

# Stage 2: Production runtime (PHP-FPM + Nginx)
FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
        nginx \
        supervisor \
        libpng-dev \
        libxml2-dev \
        oniguruma-dev \
        freetype-dev \
        libjpeg-turbo-dev \
        icu-dev \
        libzip-dev \
        curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        curl \
        gd \
        mbstring \
        mysqli \
        pdo \
        pdo_mysql \
        simplexml \
        fileinfo \
        intl \
        xml \
        zip \
    && rm -rf /tmp/*

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
RUN chmod +x /usr/local/bin/start-production

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/start-production"]
