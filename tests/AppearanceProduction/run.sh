#!/usr/bin/env bash
set -euo pipefail
umask 077
fixture_name="appearance-$(date -u +%Y%m%d%H%M%S)-${RANDOM}"
fixture_dir="var/${fixture_name}"
docker compose exec -T app php tools/production/init.php "$fixture_name"
sed -i "s/^STUDIO_PRODUCTION_PORT=.*/STUDIO_PRODUCTION_PORT=${APPEARANCE_PRODUCTION_PORT:-8125}/" "$fixture_dir/compose.env"
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
"${dc[@]}" run --rm --no-deps app php bin/console doctrine:migrations:migrate --no-interaction
# Maintenance-only mounted staging: production runtime and AssetMapper remain immutable.
for family in inter lora jetbrains-mono; do
    "${dc[@]}" run --rm --no-deps -v "$PWD/var/library-staging:/app/var/library-staging:ro" app php bin/console styleguide:fonts:sync --family="$family"
done
"${dc[@]}" up -d --wait app preview-consumer
app_started=1
docker compose exec -T app php tests/AppearanceProduction/credentials.php "$fixture_name"
for role in reader editor admin; do
    read -r email < "$fixture_dir/$role.email" || true
    "${dc[@]}" exec -T app php bin/console styleguide:user:create --email="$email" --role="ROLE_${role^^}" < "$fixture_dir/$role.stdin"
done
cat > "$fixture_dir/browser.yaml" <<YAML
services:
  browser:
    environment:
      STUDIO_ACCOUNTS_FILE: $fixture_dir/accounts.json
      APPEARANCE_PRODUCTION: "1"
YAML
"${dc[@]}" exec -T app sha256sum public/assets/manifest.json > "$fixture_dir/compiled-before.sha256"
"${dc[@]}" exec -T app php bin/console doctrine:schema:validate
"${dc[@]}" -f compose.appearance-test.yaml -f "$fixture_dir/browser.yaml" run --rm --no-deps browser npx playwright test tests/Browser/appearance.spec.js --output="$fixture_dir/browser-results" --reporter=list
"${dc[@]}" exec -T app sha256sum public/assets/manifest.json > "$fixture_dir/compiled-after.sha256"
cmp "$fixture_dir/compiled-before.sha256" "$fixture_dir/compiled-after.sha256"
"${dc[@]}" exec -T app php bin/console styleguide:fonts:verify
"${dc[@]}" exec -T app php -r 'if (is_writable("public/assets/manifest.json") || getenv("APP_DEBUG") !== "0") throw new RuntimeException("Production asset boundary changed."); echo "PASS: AssetMapper manifest is read-only and unchanged after new family/variant creation and application.\n";'
mkdir -p var/appearance-verification
printf '%s\n' "$fixture_dir/compose.env" > var/appearance-verification/production-environment.txt
echo 'PASS: application theming, new post-build families, immutable assets, isolated accounts and no redeployment.'
