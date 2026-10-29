#!/usr/bin/env bash
set -euo pipefail
umask 077
# The fixture's private env file is authoritative, including when invoked from a production shell.
unset STUDIO_PROJECT STUDIO_RELEASE STUDIO_SECRET_DIR STUDIO_PRODUCTION_PORT COMPOSE_PROJECT_NAME COMPOSE_FILE COMPOSE_PROFILES
specs=("$@")
if ((${#specs[@]} == 0)); then specs=(tests/Browser/appearance.spec.js); fi
for spec in "${specs[@]}"; do
    case "$spec" in tests/Browser/appearance.spec.js|tests/Browser/discovery.spec.js|tests/Browser/catalogue_preview.spec.js|tests/Browser/reference_picker.spec.js|tests/Browser/font_pairing_contrast.spec.js|tests/Browser/instance_properties.spec.js|tests/Browser/theme_components.spec.js|tests/Browser/editing_protection.spec.js|tests/Browser/composition_history.spec.js|tests/Browser/preview.spec.js|tests/Browser/authentication.spec.js|tests/Browser/composition.spec.js|tests/Browser/compose_mcp.spec.js|tests/Browser/profile.spec.js|tests/Browser/favorites.spec.js) ;; *) echo 'Choose an appearance, preview, authentication, composition, profile, favorites or theme-component acceptance spec.' >&2; exit 1;; esac
done
fixture_name="appearance-$(date -u +%Y%m%d%H%M%S)-${RANDOM}"
fixture_dir="var/${fixture_name}"
php_image='dunglas/frankenphp:1.13-php8.5-trixie@sha256:ada77754f475352a9105b7961f89ab17ece97048514799532d2d27a1dea2fda7'
docker run --rm --user "$(id -u):$(id -g)" --workdir /app -v "$PWD:/app" --entrypoint php "$php_image" tools/production/init.php "$fixture_name"
sed -i "s/^STUDIO_PRODUCTION_PORT=.*/STUDIO_PRODUCTION_PORT=${APPEARANCE_PRODUCTION_PORT:-0}/" "$fixture_dir/compose.env"
mcp_check=0
for spec in "${specs[@]}"; do if [[ "$spec" == tests/Browser/compose_mcp.spec.js || "$spec" == tests/Browser/profile.spec.js ]]; then mcp_check=1; fi; done
if ((mcp_check)); then
    printf '\nMCP_COMPOSE_ENABLED=1\nMCP_PUBLIC_HOST=localhost\nMCP_PUBLIC_BASE_URL=http://localhost:%s\n' "${APPEARANCE_PRODUCTION_PORT:-8125}" >> "$fixture_dir/compose.env"
fi
dc=(docker compose --env-file "$fixture_dir/compose.env" -p "styleguide-$fixture_name" -f compose.production.yaml)
python3 tests/BrowserHarness/production-ownership.py "$fixture_dir" "${APPEARANCE_COMPOSE_OVERRIDE:-}"
dc+=(-f "$fixture_dir/ownership.json")
# Optional local build/network trust configuration; never alters the deployed project.
if [[ -n "${APPEARANCE_COMPOSE_OVERRIDE:-}" ]]; then dc+=(-f "$APPEARANCE_COMPOSE_OVERRIDE"); fi
app_started=0
cleanup() {
    local status=$?
    if ((app_started)); then
        for role in reader editor admin; do
            if [[ -f "$fixture_dir/$role.email" ]]; then
                read -r email < "$fixture_dir/$role.email" || true
                "${dc[@]}" exec -T app php bin/console styleguide:user:disable --email="$email" || true
            fi
        done
    fi
    if ! "${dc[@]}" -f compose.appearance-test.yaml --profile tools down --volumes --remove-orphans; then
        echo "Cleanup incomplete for styleguide-$fixture_name; private resources/configuration retained in $fixture_dir." >&2
        echo "Retry: docker compose --env-file $fixture_dir/compose.env -p styleguide-$fixture_name -f compose.production.yaml -f $fixture_dir/ownership.json -f compose.appearance-test.yaml --profile tools down --volumes --remove-orphans" >&2
        if ((status == 0)); then status=1; fi
        return "$status"
    fi
    rm -f "$fixture_dir/browser-private/accounts.json" "$fixture_dir/browser-private/ownership.json" "$fixture_dir/browser-private/reference-fixture.json" "$fixture_dir/accounts.json" "$fixture_dir/"*.stdin
    python3 - "$fixture_dir" <<'PYTHON'
from pathlib import Path
import sys
for path in (Path(sys.argv[1])/'secrets').iterdir():
    if path.is_file() and not path.is_symlink(): path.unlink()
PYTHON
    echo "Retained isolated production appearance evidence in $fixture_dir."
    return "$status"
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
"${dc[@]}" build app build-worker render-worker
"${dc[@]}" -f compose.appearance-test.yaml build browser
"${dc[@]}" up -d --wait database render-worker build-worker
"${dc[@]}" run --rm --no-deps -v "$PWD/tests/BrowserHarness/initialize.php:/app/tests/BrowserHarness/initialize.php:ro" app php tests/BrowserHarness/initialize.php
"${dc[@]}" run --rm --no-deps app php tools/mcp/init.php
"${dc[@]}" run --rm --no-deps app php bin/console doctrine:migrations:migrate --no-interaction
# Maintenance-only mounted staging: production runtime and AssetMapper remain immutable.
mkdir -p "$fixture_dir/fonts" "$fixture_dir/browser-results"
"${dc[@]}" -f compose.appearance-test.yaml run --rm --no-deps -v "$PWD/$fixture_dir/fonts:/export" browser sh -c 'cp -r /app/font-fixtures/fonts/. /export/; cp /app/resources/library/fonts.lock.json /export/fonts.lock.json'
: > "$fixture_dir/fonts/import-report.json"
for family in inter lora jetbrains-mono; do
    # Font input stays read-only; the CLI's import report has a task-owned output mount.
    report="$fixture_dir/$family-import-report.json"
    : > "$report"
    "${dc[@]}" run --rm --no-deps -v "$PWD/$fixture_dir/fonts:/app/var/library-staging/fonts:ro" -v "$PWD/$fixture_dir/fonts/fonts.lock.json:/app/resources/library/fonts.lock.json:ro" -v "$PWD/$report:/app/var/library-staging/fonts/import-report.json:rw" app php bin/console styleguide:fonts:sync --family="$family"
done
"${dc[@]}" up -d --wait app preview-consumer
app_started=1
docker run --rm --user "$(id -u):$(id -g)" --workdir /app -v "$PWD:/app" --entrypoint php "$php_image" tests/AppearanceProduction/credentials.php "$fixture_name"
for role in reader editor admin; do
    read -r email < "$fixture_dir/$role.email" || true
    "${dc[@]}" exec -T app php bin/console styleguide:user:create --email="$email" --role="ROLE_${role^^}" < "$fixture_dir/$role.stdin"
done
if ((mcp_check)); then
    read -r email < "$fixture_dir/editor.email" || true
    "${dc[@]}" exec -T app php bin/console styleguide:mcp:token:issue --email="$email" --label='Production browser acceptance' --catalogue='browser/*' --token-only > "$fixture_dir/compose-token"
    python3 - "$fixture_dir" <<'PYTHON'
import json,sys
from pathlib import Path
p=Path(sys.argv[1]);accounts=json.loads((p/'accounts.json').read_text());accounts['editor']['composeToken']=(p/'compose-token').read_text().strip();(p/'accounts.json').write_text(json.dumps(accounts));(p/'compose-token').unlink()
PYTHON
    "${dc[@]}" exec -T app php bin/console styleguide:showcase --publish
    "${dc[@]}" exec -T app php bin/console styleguide:mcp:smoke --url=http://localhost:8080/mcp/compose | tee "$fixture_dir/mcp-sdk-smoke.json"
fi
cat > "$fixture_dir/browser.yaml" <<YAML
services:
  browser:
    environment:
      STUDIO_ACCOUNTS_FILE: /run/styleguide-test/accounts.json
      STUDIO_TEST_OWNERSHIP_FILE: /run/styleguide-test/ownership.json
      STUDIO_TEST_RUN_ID: $(python3 -c 'import json,sys; print(json.load(open(sys.argv[1]))["runId"])' "$fixture_dir/browser-private/ownership.json")
      STUDIO_BROWSER_ARTIFACTS: /artifacts
      APPEARANCE_PRODUCTION: "1"
    volumes:
      - $PWD/$fixture_dir/browser-private:/run/styleguide-test:ro
      - $PWD/$fixture_dir/browser-results:/artifacts
YAML
cp "$fixture_dir/accounts.json" "$fixture_dir/browser-private/accounts.json"
"${dc[@]}" exec -T app sha256sum public/assets/manifest.json > "$fixture_dir/compiled-before.sha256"
"${dc[@]}" exec -T app php bin/console doctrine:schema:validate
for spec in "${specs[@]}"; do
    if [[ "$spec" == tests/Browser/reference_picker.spec.js ]]; then
        "${dc[@]}" run --rm --no-deps -v "$PWD/tests/Fixtures:/app/tests/Fixtures:ro" -v "$PWD/tests/ReferencePicker:/app/tests/ReferencePicker:ro" app php tests/ReferencePicker/seed.php
        break
    fi
done
"${dc[@]}" -f compose.appearance-test.yaml -f "$fixture_dir/browser.yaml" run --rm --no-deps browser bash -c 'mkdir -p var/library-staging; cp -r /app/font-fixtures/fonts var/library-staging/; exec npx playwright test "$@" --reporter=list' browser "${specs[@]}"
"${dc[@]}" exec -T app sha256sum public/assets/manifest.json > "$fixture_dir/compiled-after.sha256"
cmp "$fixture_dir/compiled-before.sha256" "$fixture_dir/compiled-after.sha256"
"${dc[@]}" exec -T app php bin/console styleguide:fonts:verify
"${dc[@]}" exec -T app php -r 'if (is_writable("public/assets/manifest.json") || getenv("APP_DEBUG") !== "0") throw new RuntimeException("Production asset boundary changed."); echo "PASS: AssetMapper manifest is read-only and unchanged after new family/variant creation and application.\n";'
mkdir -p var/appearance-verification
printf '%s\n' "$fixture_dir/compose.env" > var/appearance-verification/production-environment.txt
echo 'PASS: application theming, new post-build families, immutable assets, isolated accounts and no redeployment.'
