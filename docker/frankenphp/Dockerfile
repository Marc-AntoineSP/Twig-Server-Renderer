ARG FRANKENPHP_IMAGE=dunglas/frankenphp:1.13-php8.5-trixie@sha256:ada77754f475352a9105b7961f89ab17ece97048514799532d2d27a1dea2fda7
ARG COMPOSER_IMAGE=composer:2@sha256:b6dcd72ca98fa19d0e5b8476de179859704891fad06dd9e0b96833d4c430a5fc
FROM ${COMPOSER_IMAGE} AS composer
FROM ${FRANKENPHP_IMAGE} AS dev

ARG LOCAL_UID=1000
ARG LOCAL_GID=1000
WORKDIR /app
RUN install-php-extensions intl pdo_pgsql zip opcache mbstring dom xml xmlwriter pcntl \
    && groupadd --gid ${LOCAL_GID} studio \
    && useradd --uid ${LOCAL_UID} --gid studio --create-home studio \
    && mkdir -p /app/var/catalogue /data/caddy /config/caddy \
    && chown -R studio:studio /app /data /config
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY docker/frankenphp/Caddyfile /etc/frankenphp/Caddyfile
COPY docker/frankenphp/conf.d/app.ini /usr/local/etc/php/conf.d/app.ini
ENV APP_ENV=dev APP_DEBUG=1
USER studio
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]

FROM node:24-trixie-slim@sha256:43b9ba1142b8dcf33fa8f922ca1be85714adc444e0586c7cf7726daaeabc1bea AS studio-assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY assets ./assets
COPY templates ./templates
RUN npm run build:css

FROM dev AS production-build
USER root
ENV APP_ENV=prod APP_DEBUG=0
COPY composer.json composer.lock symfony.lock importmap.php ./
COPY .env.prod ./.env
COPY packages/catalogue-contracts ./packages/catalogue-contracts
COPY bin ./bin
COPY config ./config
COPY src ./src
COPY migrations ./migrations
COPY templates ./templates
COPY public/index.php ./public/index.php
COPY assets ./assets
COPY agent-kit/examples ./agent-kit/examples
COPY fixtures ./fixtures
COPY workers/render/composer.lock ./workers/render/composer.lock
COPY workers/build/package-lock.json ./workers/build/package-lock.json
RUN --mount=type=cache,target=/tmp/composer-cache \
    export COMPOSER_CACHE_DIR=/tmp/composer-cache \
 && rm src/Command/BrowserAccountsCommand.php \
 && composer install --no-dev --no-scripts --no-interaction --optimize-autoloader \
 && php bin/console importmap:install --env=prod --no-debug
COPY --from=studio-assets /app/assets/styles/app.css ./assets/styles/app.css
RUN php bin/console asset-map:compile --env=prod --no-debug \
 && rm -rf var/cache var/log \
 && mkdir -p var/cache var/log var/sessions var/catalogue \
 && chown -R studio:studio var

FROM dev AS production
USER root
ENV APP_ENV=prod APP_DEBUG=0
COPY --from=production-build --chown=root:root /app /app
RUN chown -R studio:studio /app/var && rm /usr/local/bin/composer \
 && setcap -r /usr/local/bin/frankenphp
COPY docker/frankenphp/production.Caddyfile /etc/frankenphp/production.Caddyfile
COPY docker/frankenphp/conf.d/production.ini /usr/local/etc/php/conf.d/zz-production.ini
RUN frankenphp adapt --config /etc/frankenphp/production.Caddyfile > /dev/null
USER studio
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/production.Caddyfile"]

FROM ${FRANKENPHP_IMAGE} AS https-proxy
RUN setcap -r /usr/local/bin/frankenphp \
 && mkdir -p /data/caddy /config/caddy \
 && chown -R www-data:www-data /data /config
COPY docker/frankenphp/proxy.Caddyfile /etc/frankenphp/proxy.Caddyfile
RUN frankenphp adapt --config /etc/frankenphp/proxy.Caddyfile > /dev/null
USER www-data
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/proxy.Caddyfile"]
