FROM composer:2@sha256:b6dcd72ca98fa19d0e5b8476de179859704891fad06dd9e0b96833d4c430a5fc AS dependencies
WORKDIR /worker
COPY workers/render/composer.json workers/render/composer.lock ./
RUN composer install --no-dev --no-scripts --no-plugins --no-interaction --optimize-autoloader
FROM dunglas/frankenphp:1.13-php8.5-trixie@sha256:ada77754f475352a9105b7961f89ab17ece97048514799532d2d27a1dea2fda7
WORKDIR /worker
COPY --from=dependencies /worker/vendor ./vendor
COPY workers/render/index.php workers/render/render.php ./
USER www-data
CMD ["php", "-d", "memory_limit=64M", "-d", "display_errors=0", "-S", "0.0.0.0:8080", "index.php"]
