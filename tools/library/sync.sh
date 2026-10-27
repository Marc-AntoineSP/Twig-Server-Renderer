#!/usr/bin/env bash
set -euo pipefail
read -r -a compose <<< "${COMPOSE:-docker compose}"
dc() { "${compose[@]}" "$@"; }
kind="${1:?Choose fonts or themes}"; shift
if [[ "$kind" == fonts ]]; then
    failed=0
    dc run --rm --no-deps node node tools/library/fonts-download.mjs "$@" || failed=1
    dc exec -T app php bin/console styleguide:fonts:sync "$@" || failed=1
    exit "$failed"
elif [[ "$kind" == themes ]]; then
    dc run --rm --no-deps research-browser node tools/library/themes-collect.mjs
    dc run --rm --no-deps node node tools/library/themes-candidates.mjs
    dc exec -T app php bin/console styleguide:themes:collect "$@"
else
    echo 'Choose fonts or themes.' >&2
    exit 2
fi
