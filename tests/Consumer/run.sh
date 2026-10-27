#!/usr/bin/env bash
set -euo pipefail
read -r -a compose <<< "${COMPOSE:-docker compose}"
dc() { "${compose[@]}" "$@"; }
cleanup() { dc exec -T app php bin/console styleguide:test:accounts --cleanup; rm -f var/consumer-auth/styleguide/auth.json; }
trap cleanup EXIT
dc stop consumer
dc exec -T app php tests/Consumer/bootstrap.php
dc build consumer consumer-tools
dc run --rm consumer-tools composer install --no-interaction --no-scripts
dc run --rm consumer-tools php bin/console importmap:install
dc run --rm -w /app/var/consumer node npm ci --ignore-scripts
dc run --rm consumer-tools node node_modules/@tailwindcss/cli/dist/index.mjs -i assets/styles/app.input.css -o assets/styles/app.css --minify
dc run --rm consumer-tools php bin/console styleguide:doctor
dc exec -T app php bin/console styleguide:test:accounts
dc run --rm browser node tests/Consumer/prepare.mjs
dc run --rm consumer-tools php /workspace/tests/Consumer/install.php install
dc up -d --wait consumer
dc run --rm browser node tests/Consumer/verify.mjs
dc run --rm consumer-tools php /workspace/tests/Consumer/install.php update
dc restart consumer
dc up -d --wait consumer
dc run --rm browser node tests/Consumer/verify.mjs
