# Development bootstrap reference. M00 must verify tag and record digest.
ARG FRANKENPHP_IMAGE=dunglas/frankenphp:1.13-php8.5-trixie
FROM ${FRANKENPHP_IMAGE} AS base
WORKDIR /app
RUN install-php-extensions intl pdo_pgsql zip opcache
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY docker/frankenphp/Caddyfile /etc/caddy/Caddyfile
COPY docker/frankenphp/conf.d/app.ini /usr/local/etc/php/conf.d/app.ini

FROM base AS dev
ENV APP_ENV=dev APP_DEBUG=1
