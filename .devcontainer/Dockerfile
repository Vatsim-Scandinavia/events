FROM mcr.microsoft.com/devcontainers/php:8.4-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libicu-dev libonig-dev libsqlite3-dev libzip-dev \
        nginx ripgrep sqlite3 \
    && docker-php-ext-install -j"$(nproc)" bcmath intl mbstring pcntl pdo_sqlite zip \
    && rm -rf /var/lib/apt/lists/* \
    && rm -f /etc/nginx/sites-enabled/default

COPY nginx.conf /etc/nginx/conf.d/events.conf

ENV XDEBUG_MODE=off
