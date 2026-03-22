# syntax=docker/dockerfile:1.7
FROM composer:2 AS composer

FROM dunglas/frankenphp:1-php8.4-alpine AS base

WORKDIR /app

# Extensions système requises
RUN apk add --no-cache acl && \
    install-php-extensions \
        intl \
        opcache \
        pdo_sqlite \
        zip

COPY --link frankenphp/Caddyfile /etc/caddy/Caddyfile
COPY --link frankenphp/conf.d/app.ini $PHP_INI_DIR/conf.d/app.ini

# ─── Build des assets ──────────────────────────────────────────────────────────
FROM base AS builder

COPY --from=composer /usr/bin/composer /usr/bin/composer

ENV APP_ENV=prod APP_DEBUG=0 APP_SECRET=buildsecret

COPY --link composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --prefer-dist --no-progress

COPY --link . .

RUN composer dump-autoload --optimize --no-dev && \
    php bin/console importmap:install && \
    php bin/console asset-map:compile && \
    php bin/console cache:warmup --env=prod

# ─── Image finale ──────────────────────────────────────────────────────────────
FROM base AS final

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    DATABASE_URL="sqlite:////data/data_prod.db"

# Données SQLite sur un volume persistant
VOLUME /data

COPY --from=builder --link /app /app

# Le répertoire var est réécrit au runtime, on le prépare
RUN mkdir -p var/cache var/log && \
    setfacl -R -m u:www-data:rwX -m u:"$(whoami)":rwX var || \
    chmod -R 777 var

EXPOSE 80 443 443/udp

# Migrations + démarrage
CMD ["sh", "-c", "php bin/console doctrine:migrations:migrate --no-interaction --env=prod && frankenphp run --config /etc/caddy/Caddyfile"]
