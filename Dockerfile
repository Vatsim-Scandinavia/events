# syntax=docker/dockerfile:1.7

# The VATSIM Event Hub: Apache and PHP serving the app, with the frontend built in a separate
# stage. TLS is left to the reverse proxy in front (see TRUSTED_PROXIES in .env.example).
# compose.yaml shows how to run it with persistent storage and the scheduler.

####################################################################################################
# Frontend build
FROM node:22.23.4-alpine AS frontend

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts

COPY . .
RUN npm run build

####################################################################################################
# The app
FROM php:8.5.12-apache-trixie

# curl is for the health check below.
RUN apt-get update \
    && apt-get install -y --no-install-recommends curl \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod rewrite

COPY --from=mlocati/php-extension-installer:2.12.3 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions gd intl bcmath zip pdo_mysql pdo_pgsql opcache

COPY --from=composer:2.9.8 /usr/bin/composer /usr/bin/composer

# PHP: production defaults, uploads big enough for banners (8 MB) and logos, and opcache that
# trusts the code baked into the image.
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY <<'EOF' /usr/local/etc/php/conf.d/hub.ini
expose_php = Off
memory_limit = 256M
upload_max_filesize = 10M
post_max_size = 12M
opcache.enable = 1
opcache.validate_timestamps = 0
opcache.max_accelerated_files = 20000
EOF

# Apache serves public/ on port 80 and logs to the container's output.
COPY <<'EOF' /etc/apache2/sites-available/000-default.conf
<VirtualHost *:80>
    DocumentRoot /app/public

    <Directory /app/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog /dev/stderr
    CustomLog /dev/stdout combined
</VirtualHost>
EOF
COPY <<'EOF' /etc/apache2/conf-enabled/hub.conf
ServerTokens Prod
ServerSignature Off
EOF

WORKDIR /app

# PHP dependencies first, so they're cached until composer.lock changes.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader

COPY --chown=www-data:www-data . .
COPY --from=frontend --chown=www-data:www-data /app/public/build public/build

RUN composer dump-autoload --optimize --no-dev \
    && php artisan storage:link \
    && chown -R www-data:www-data storage bootstrap/cache

# Runs before Apache (or any other command, e.g. the scheduler): readies storage, migrates and
# caches the configuration from the container's environment.
COPY --chmod=755 <<'EOF' /usr/local/bin/hub-entrypoint
#!/bin/sh
set -e
cd /app

# Artisan writes logs and caches, so it runs as the web server's user.
as_web() {
    if [ "$(id -u)" = 0 ]; then runuser -u www-data -- "$@"; else "$@"; fi
}

# storage/ is usually a fresh volume, so recreate what Laravel expects in it.
mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs
if [ "$(id -u)" = 0 ]; then chown -R www-data:www-data storage; fi

# A SQLite database has to exist before migrating, e.g. DB_DATABASE=/app/storage/database.sqlite.
if [ "${DB_CONNECTION:-sqlite}" = sqlite ] && [ -n "$DB_DATABASE" ] && [ ! -f "$DB_DATABASE" ]; then
    as_web touch "$DB_DATABASE"
fi

if [ "${RUN_MIGRATIONS:-true}" = true ]; then
    as_web php artisan migrate --force
fi

as_web php artisan optimize

exec docker-php-entrypoint "$@"
EOF

# Laravel only answers for APP_URL's host, so ask for /up by that name.
COPY --chmod=755 <<'EOF' /usr/local/bin/hub-healthcheck
#!/bin/sh
host=$(php -r 'echo parse_url(getenv("APP_URL") ?: "http://localhost", PHP_URL_HOST);')
exec curl -fsS -o /dev/null -H "Host: ${host}" http://127.0.0.1/up
EOF

EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --start-period=60s CMD ["hub-healthcheck"]
ENTRYPOINT ["hub-entrypoint"]
CMD ["apache2-foreground"]
