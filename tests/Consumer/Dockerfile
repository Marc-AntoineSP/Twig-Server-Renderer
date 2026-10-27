FROM node:24-trixie-slim@sha256:43b9ba1142b8dcf33fa8f922ca1be85714adc444e0586c7cf7726daaeabc1bea AS node
FROM composer:2@sha256:b6dcd72ca98fa19d0e5b8476de179859704891fad06dd9e0b96833d4c430a5fc AS composer
FROM dunglas/frankenphp:1.13-php8.5-trixie@sha256:ada77754f475352a9105b7961f89ab17ece97048514799532d2d27a1dea2fda7
ARG LOCAL_UID=1000
ARG LOCAL_GID=1000
RUN install-php-extensions intl zip mbstring opcache \
 && groupadd --gid ${LOCAL_GID} consumer \
 && useradd --uid ${LOCAL_UID} --gid consumer --create-home consumer \
 && mkdir -p /consumer /data/caddy /config/caddy \
 && chown -R consumer:consumer /consumer /data /config
COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY tests/Consumer/Caddyfile /etc/frankenphp/consumer.Caddyfile
WORKDIR /consumer
USER consumer
ENV APP_ENV=dev APP_DEBUG=0
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/consumer.Caddyfile"]
