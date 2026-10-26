ARG FRANKENPHP_IMAGE=dunglas/frankenphp:1.13-php8.5-trixie@sha256:ada77754f475352a9105b7961f89ab17ece97048514799532d2d27a1dea2fda7
ARG COMPOSER_IMAGE=composer:2@sha256:b6dcd72ca98fa19d0e5b8476de179859704891fad06dd9e0b96833d4c430a5fc
FROM ${COMPOSER_IMAGE} AS composer
FROM ${FRANKENPHP_IMAGE} AS dev

ARG LOCAL_UID=1000
ARG LOCAL_GID=1000
WORKDIR /app
RUN install-php-extensions intl pdo_pgsql zip opcache mbstring dom xml xmlwriter \
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
