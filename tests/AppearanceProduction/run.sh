#!/usr/bin/env bash
set -euo pipefail
umask 077
specs=("$@")
if ((${#specs[@]} == 0)); then specs=(tests/Browser/appearance.spec.js); fi
for spec in "${specs[@]}"; do
    case "$spec" in tests/Browser/appearance.spec.js|tests/Browser/theme_components.spec.js|tests/Browser/preview.spec.js|tests/Browser/authentication.spec.js|tests/Browser/composition.spec.js|tests/Browser/compose_mcp.spec.js|tests/Browser/profile.spec.js) ;; *) echo 'Choose an appearance, preview, authentication, composition, profile or theme-component acceptance spec.' >&2; exit 1;; esac
done
fixture_name="appearance-$(date -u +%Y%m%d%H%M%S)-${RANDOM}"
fixture_dir="var/${fixture_name}"
docker compose exec -T app php tools/production/init.php "$fixture_name"
sed -i "s/^STUDIO_PRODUCTION_PORT=.*/STUDIO_PRODUCTION_PORT=${APPEARANCE_PRODUCTION_PORT:-8125}/" "$fixture_dir/compose.env"
mcp_check=0
for spec in "${specs[@]}"; do if [[ "$spec" == tests/Browser/compose_mcp.spec.js || "$spec" == tests/Browser/profile.spec.js ]]; then mcp_check=1; fi; done
if ((mcp_check)); then
    printf '\nMCP_COMPOSE_ENABLED=1\nMCP_PUBLIC_HOST=localhost\nMCP_PUBLIC_BASE_URL=http://localhost:%s\n' "${APPEARANCE_PRODUCTION_PORT:-8125}" >> "$fixture_dir/compose.env"
fi
dc=(docker compose --env-file "$fixture_dir/compose.env" -f compose.production.yaml)
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
    "${dc[@]}" -f compose.appearance-test.yaml --profile tools down --remove-orphans || true
    echo "Retained isolated production appearance evidence in $fixture_dir."
    return "$status"
}
trap cleanup EXIT
"${dc[@]}" build app build-worker render-worker
"${dc[@]}" up -d --wait database render-worker build-worker
"${dc[@]}" run --rm --no-deps app php tools/mcp/init.php
"${dc[@]}" run --rm --no-deps app php bin/console doctrine:migrations:migrate --no-interaction
# Maintenance-only mounted staging: production runtime and AssetMapper remain immutable.
for family in inter lora jetbrains-mono; do
    # Font input stays read-only; the CLI's import report has a task-owned output mount.
    report="$fixture_dir/$family-import-report.json"
    : > "$report"
    "${dc[@]}" run --rm --no-deps -v "$PWD/var/library-staging:/app/var/library-staging:ro" -v "$PWD/$report:/app/var/library-staging/fonts/import-report.json:rw" app php bin/console styleguide:fonts:sync --family="$family"
done
"${dc[@]}" up -d --wait app preview-consumer
app_started=1
docker compose exec -T app php tests/AppearanceProduction/credentials.php "$fixture_name"
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
      STUDIO_ACCOUNTS_FILE: $fixture_dir/accounts.json
      APPEARANCE_PRODUCTION: "1"
YAML
"${dc[@]}" exec -T app sha256sum public/assets/manifest.json > "$fixture_dir/compiled-before.sha256"
"${dc[@]}" exec -T app php bin/console doctrine:schema:validate
"${dc[@]}" -f compose.appearance-test.yaml -f "$fixture_dir/browser.yaml" run --rm --no-deps browser npx playwright test "${specs[@]}" --output="$fixture_dir/browser-results" --reporter=list
"${dc[@]}" exec -T app sha256sum public/assets/manifest.json > "$fixture_dir/compiled-after.sha256"
cmp "$fixture_dir/compiled-before.sha256" "$fixture_dir/compiled-after.sha256"
"${dc[@]}" exec -T app php bin/console styleguide:fonts:verify
"${dc[@]}" exec -T app php -r 'if (is_writable("public/assets/manifest.json") || getenv("APP_DEBUG") !== "0") throw new RuntimeException("Production asset boundary changed."); echo "PASS: AssetMapper manifest is read-only and unchanged after new family/variant creation and application.\n";'
mkdir -p var/appearance-verification
printf '%s\n' "$fixture_dir/compose.env" > var/appearance-verification/production-environment.txt
echo 'PASS: application theming, new post-build families, immutable assets, isolated accounts and no redeployment.'
