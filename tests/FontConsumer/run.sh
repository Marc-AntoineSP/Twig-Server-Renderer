#!/usr/bin/env bash
set -euo pipefail
read -r -a compose <<< "${COMPOSE:-docker compose}"
dc() { "${compose[@]}" "$@"; }
# Fail before fixture creation or cleanup unless this is an owned disposable studio.
dc exec -T app php tests/BrowserHarness/verify.php
owns_accounts=0
consumer_started=0
cleanup() {
    local status=$?
    if ((consumer_started)); then dc stop font-consumer || true; fi
    if ((owns_accounts)); then dc exec -T app php bin/console styleguide:test:accounts --cleanup || true; fi
    return "$status"
}
trap cleanup EXIT
task_name="font-consumer-$(date -u +%Y%m%dT%H%M%SZ)-${RANDOM}"
export FONT_CONSUMER_DIR="${PWD}/var/${task_name}"
dc exec -T app php tests/FontConsumer/bootstrap.php "/app/var/${task_name}"
dc build font-consumer font-consumer-tools
dc run --rm --no-deps font-consumer-tools composer install --no-scripts --no-interaction
dc run --rm --no-deps font-consumer-tools php bin/console importmap:install
dc run --rm --no-deps -w "/app/var/${task_name}" node npm ci --ignore-scripts
dc run --rm --no-deps font-consumer-tools node node_modules/@tailwindcss/cli/dist/index.mjs -i assets/styles/app.input.css -o assets/styles/app.css --minify
if [[ ! -f var/browser/accounts.json ]]; then
    dc exec -T app php bin/console styleguide:test:accounts
    owns_accounts=1
fi
dc run --rm --no-deps browser node tests/FontConsumer/prepare.mjs
dc run --rm --no-deps font-consumer-tools php /workspace/tests/FontConsumer/install.php
dc up -d --wait font-consumer
consumer_started=1
dc run --rm --no-deps browser node tests/FontConsumer/verify.mjs
dc restart font-consumer
dc up -d --wait font-consumer
dc run --rm --no-deps browser node tests/FontConsumer/verify.mjs
dc stop font-consumer
consumer_started=0
echo "Font consumer verification complete; retained ${FONT_CONSUMER_DIR}."
