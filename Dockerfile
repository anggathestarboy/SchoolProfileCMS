# syntax=docker/dockerfile:1

########################################
# 1) PHP dependencies
########################################
FROM composer:2 AS vendor
WORKDIR /app
COPY . .
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress

########################################
# 2) Frontend assets
#
# Needs vendor/ present before building — resources/css/app.css and
# site.css both use Tailwind v4 @source directives that scan
# vendor/livewire/flux(-pro) for class names (see those files), so the
# asset build can't run before composer install.
########################################
FROM node:20-alpine AS assets
WORKDIR /app
COPY . .
COPY --from=vendor /app/vendor ./vendor
RUN npm ci && npm run build

########################################
# 3) Application image — FrankenPHP, a single binary bundling PHP and the
# Caddy web server, running the app plus the Laravel scheduler under
# Supervisor. Lighter than a separate Nginx + PHP-FPM setup: one web-server
# process instead of two, and no fastcgi config to keep in sync (see
# docker/Caddyfile).
########################################
FROM dunglas/frankenphp:php8.4 AS app

RUN install-php-extensions \
        pdo_mysql \
        mbstring \
        bcmath \
        pcntl \
        zip \
        gd \
        intl \
        exif \
        opcache

RUN apt-get update && apt-get install -y --no-install-recommends supervisor \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php/local.ini /usr/local/etc/php/conf.d/local.ini

WORKDIR /app

COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build

RUN chown -R www-data:www-data /app \
    && chmod -R 775 storage bootstrap/cache

COPY docker/Caddyfile /etc/caddy/Caddyfile
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]
CMD ["supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
