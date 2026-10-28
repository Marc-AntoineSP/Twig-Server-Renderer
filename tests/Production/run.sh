#!/usr/bin/env bash
set -euo pipefail
umask 077
base=${PRODUCTION:-docker compose --env-file var/production/compose.env -f compose.production.yaml}
read -r -a dc <<< "$base"
read -r -a testdc <<< "$base -f compose.production-test.yaml"
# Verify the target before account creation, publication, pause/restart or recovery mutations.
if ! "${dc[@]}" exec -T app php -r 'require "vendor/autoload.php"; $k=new App\Kernel("prod",false); $k->boot(); (new App\Testing\BrowserRunGuard($k->getContainer()->get("doctrine")->getConnection()))->verify();'; then
 echo 'This recovery driver requires a verified disposable studio. Use make appearance-production/profile-production for maintained isolated checks; see docs/styleguide/BROWSER_TESTS.md.' >&2
 exit 2
fi
environment=${PRODUCTION_ENV:-var/production/compose.env}
project=$(sed -n 's/^STUDIO_PROJECT=//p' "$environment")
release=$(sed -n 's/^STUDIO_RELEASE=//p' "$environment")
secret_dir=$(sed -n 's/^STUDIO_SECRET_DIR=//p' "$environment")
cleanup() {
 "${dc[@]}" unpause build-worker >/dev/null 2>&1 || true
 "${dc[@]}" up -d preview-consumer >/dev/null 2>&1 || true
}
trap cleanup EXIT
install_importmap() {
 for attempt in 1 2 3; do
  if "$@" run --rm consumer-tools php bin/console importmap:install; then return 0; fi
  [[ "$attempt" == 3 ]] && return 1
  sleep 2
 done
}
phase_start=${PRODUCTION_FROM:-all}
if [[ "$phase_start" != all && "$phase_start" != consumer && "$phase_start" != restored-consumer ]]; then echo 'Use PRODUCTION_FROM=all, consumer or restored-consumer.' >&2; exit 1; fi
if [[ "$phase_start" != all ]]; then
 [[ -f var/production-test/containers.json ]] || { echo 'Prior production boundary evidence is required before resuming.'; exit 1; }
 base="$base -f compose.local-tls.yaml";read -r -a dc <<< "$base";read -r -a testdc <<< "$base -f compose.production-test.yaml"
 echo 'Resuming consumer/recovery checks after the recorded production browser and boundary gates.'
fi
"${dc[@]}" up -d --wait
if [[ ! -f var/production-test/accounts.json ]]; then
 docker compose run --rm --no-deps app php tools/production/credentials.php
 for role in reader editor admin; do
  account_email=$(cat "var/production-test/$role.email")
  if [[ "$role" == admin ]]; then role_flag=--admin; else role_flag="--role=ROLE_${role^^}"; fi
  "${dc[@]}" exec -T app php bin/console styleguide:user:create --email="$account_email" "$role_flag" < "var/production-test/$role.stdin"
 done
fi
if [[ "$phase_start" == all ]]; then
"${dc[@]}" exec -T app php bin/console styleguide:showcase --publish
"${testdc[@]}" run --rm browser npm run test:browser
# Verify local HTTPS separately; public certificates and remote deployment are out of scope.
read -r -a tlsdc <<< "$base -f compose.local-tls.yaml"
"${tlsdc[@]}" build proxy
"${tlsdc[@]}" up -d --wait
"${tlsdc[@]}" -f compose.production-test.yaml -f compose.local-tls-test.yaml run --rm browser node tests/Browser/production-tls.mjs
base="$base -f compose.local-tls.yaml"
read -r -a dc <<< "$base"
read -r -a testdc <<< "$base -f compose.production-test.yaml"
docker inspect $("${dc[@]}" ps -q) > var/production-test/containers.json
docker network inspect $(docker network ls -q --filter "label=com.docker.compose.project=$project") > var/production-test/networks.json
"${testdc[@]}" run --rm node node tests/Production/boundaries.mjs
"${dc[@]}" exec -T app php -r 'require "vendor/autoload.php";foreach(["phpunit/phpunit","symfony/web-profiler-bundle","symfony/debug-bundle"] as $p)if(Composer\InstalledVersions::isInstalled($p))exit(1);if(is_file("src/Command/BrowserAccountsCommand.php")||getenv("APP_DEBUG")!=="0"||getenv("APP_SECRET")||getenv("DATABASE_URL")||!extension_loaded("pcntl"))exit(2);echo "PASS: production dependency, runtime env and signal boundaries.\n";'
fi
if [[ "$phase_start" != restored-consumer ]]; then
# Fresh compatible consumer: only the marked test directory is replaced.
docker compose stop consumer
"${testdc[@]}" stop consumer
docker compose run --rm --no-deps app php tests/Consumer/bootstrap.php
"${testdc[@]}" build consumer consumer-tools
"${testdc[@]}" run --rm consumer-tools composer install --no-scripts --no-interaction
install_importmap "${testdc[@]}"
"${testdc[@]}" run --rm -w /app/var/consumer node npm ci --ignore-scripts
"${testdc[@]}" run --rm consumer-tools node node_modules/@tailwindcss/cli/dist/index.mjs -i assets/styles/app.input.css -o assets/styles/app.css --minify
"${testdc[@]}" run --rm browser node tests/Consumer/prepare.mjs
"${testdc[@]}" run --rm consumer-tools php /workspace/tests/Consumer/install.php install
"${testdc[@]}" up -d --wait consumer
"${testdc[@]}" run --rm browser node tests/Consumer/verify.mjs
# Parent cleanup removes stale compiler directories left by a killed child.
"${dc[@]}" exec -T build-worker node -e "require('fs').mkdirSync('/tmp/studio-build-stale',{recursive:true});require('fs').writeFileSync('/tmp/studio-build-stale/old',Buffer.alloc(1048576));"
for phase in graceful recover; do
 rm -f "var/production-test/$phase-ready.json" "var/production-test/$phase-done.json"
 "${dc[@]}" pause build-worker
 "${testdc[@]}" run --rm --no-deps browser node tests/Production/lease.mjs "$phase" > "var/production-test/$phase.log" 2>&1 &
 browser_pid=$!
 for ((i=0;i<200;i++)); do [[ -f "var/production-test/$phase-ready.json" ]] && break; sleep .1; done
 [[ -f "var/production-test/$phase-ready.json" ]] || { cat "var/production-test/$phase.log"; exit 1; }
 if [[ "$phase" == graceful ]]; then
  consumer_id=$("${dc[@]}" ps -q preview-consumer)
  "${dc[@]}" stop --timeout 30 preview-consumer
  [[ $(docker inspect --format '{{.State.ExitCode}}' "$consumer_id") == 0 ]] || { echo 'Consumer did not stop gracefully.'; exit 1; }
 else
  "${dc[@]}" kill --signal SIGKILL preview-consumer
 fi
 "${dc[@]}" unpause build-worker
 worker_id=$("${dc[@]}" ps -q build-worker)
 for ((i=0;i<30;i++)); do [[ $(docker inspect --format '{{.State.Health.Status}}' "$worker_id") == healthy ]] && break; sleep 1; done
 [[ $(docker inspect --format '{{.State.Health.Status}}' "$worker_id") == healthy ]] || { echo 'Compiler failed to recover health after unpause.'; exit 1; }
 "${dc[@]}" up -d --wait preview-consumer
 wait "$browser_pid"
 cat "var/production-test/$phase.log"
 "${dc[@]}" exec -T build-worker node -e "if(require('fs').existsSync('/tmp/studio-build-stale'))process.exit(1);console.log('PASS: compiler parent cleaned the stale job directory.');"
done
"${dc[@]}" exec -T app php bin/console styleguide:storage:verify
"${dc[@]}" logs --no-color > var/production-test/runtime.log
"${testdc[@]}" run --rm -e "TEST_SECRET_DIR=$secret_dir" node node tests/Production/secrets.mjs
backup="var/backups/production-$(date -u +%Y%m%dT%H%M%SZ)"
PRODUCTION="$base" bash tools/production/backup.sh "$backup"
# Restart the actual database/app/consumer, then verify saved data before restoring.
"${dc[@]}" restart database app preview-consumer
"${dc[@]}" up -d --wait
"${testdc[@]}" run --rm browser node tests/Production/restored.mjs
"${testdc[@]}" stop consumer
# The restore script refuses existing target volumes. Each run has a new target.
restore_name="restore-$(date -u +%Y%m%d%H%M%S)"
docker compose run --rm --no-deps app php tools/production/init.php "$restore_name"
restore_env="var/$restore_name/compose.env"
sed -i "s/^STUDIO_RELEASE=.*/STUDIO_RELEASE=$release/;s/^STUDIO_PRODUCTION_PORT=.*/STUDIO_PRODUCTION_PORT=8103/" "$restore_env"
bash tools/production/restore.sh "$backup" "$restore_env"
else
 restore_env=${PRODUCTION_RESTORE_ENV:?Supply the previously verified fresh restore environment}
 backup=${PRODUCTION_BACKUP:?Supply its verified snapshot directory}
 [[ -f "$restore_env" && -f "$backup/SHA256SUMS" ]] || { echo 'Restore evidence is missing.'; exit 1; }
 (cd "$backup" && sha256sum --check SHA256SUMS)
fi
restore=(docker compose --env-file "$restore_env" -f compose.production.yaml -f compose.production-test.yaml)
"${restore[@]}" run --rm browser node tests/Production/restored.mjs
# Repeat the complete install flow from restored registry bytes in a fresh consumer.
docker compose run --rm --no-deps app php tests/Consumer/bootstrap.php
"${restore[@]}" run --rm consumer-tools composer install --no-scripts --no-interaction
install_importmap "${restore[@]}"
"${restore[@]}" run --rm -w /app/var/consumer node npm ci --ignore-scripts
"${restore[@]}" run --rm consumer-tools node node_modules/@tailwindcss/cli/dist/index.mjs -i assets/styles/app.input.css -o assets/styles/app.css --minify
"${restore[@]}" run --rm consumer-tools php /workspace/tests/Consumer/install.php install
"${restore[@]}" up -d --wait consumer
"${restore[@]}" run --rm browser node tests/Consumer/verify.mjs
"${restore[@]}" run --rm consumer-tools php /workspace/tests/Consumer/install.php update
"${restore[@]}" restart consumer
"${restore[@]}" up -d --wait consumer
"${restore[@]}" run --rm browser node tests/Consumer/verify.mjs
# Disable only this driver's random accounts in both projects; preserve source/releases.
for role in reader editor admin; do
 account_email=$(cat "var/production-test/$role.email")
 "${dc[@]}" exec -T app php bin/console styleguide:user:disable --email="$account_email"
 "${restore[@]}" exec -T app php bin/console styleguide:user:disable --email="$account_email"
done
"${restore[@]}" --profile consumer down
rm -f var/consumer-auth/styleguide/auth.json var/production-test/*.stdin
printf '%s\n' "$backup" > var/production-test/backup-path
printf '%s\n' "$restore_env" > var/production-test/restore-env
printf '%s\n' 'PASS: immutable production authoring, consumer install, graceful stop, real message redelivery, restart, backup/fresh restore and repeated export/install.'
