# syntax=docker/dockerfile:1.7
# check=error=true

ARG APP_VERSION=unknown
ARG BUILD_DATE=unknown

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

# ─── Build ─────────────────────────────────────────────────────────────────────
FROM base AS builder

COPY --from=composer /usr/bin/composer /usr/bin/composer

ENV APP_ENV=prod APP_DEBUG=0 APP_SECRET=buildsecret

COPY --link composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --prefer-dist --no-progress && \
    rm -rf ~/.composer/cache

COPY --link . .

RUN composer dump-autoload --optimize --no-dev && \
    php bin/console importmap:install && \
    php bin/console asset-map:compile && \
    php bin/console cache:warmup --env=prod

# ─── Image finale ──────────────────────────────────────────────────────────────
FROM base AS final

ARG APP_VERSION
ARG BUILD_DATE

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    DATABASE_URL="sqlite:////storage/data_prod.db" \
    APP_VERSION=${APP_VERSION} \
    BUILD_DATE=${BUILD_DATE}

# Utilisateur non-root + répertoires Caddy/FrankenPHP
RUN addgroup -S -g 1000 php && adduser -S -u 1000 -G php php && \
    mkdir -p /storage /data/caddy /config/caddy && \
    chown -R 1000:1000 /storage /data /config

USER 1000:1000

# Données SQLite sur un volume persistant
VOLUME /storage


COPY --chown=1000:1000 --from=builder --link /app /app

ENTRYPOINT ["/app/frankenphp/docker-entrypoint.sh"]

EXPOSE 80

CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
