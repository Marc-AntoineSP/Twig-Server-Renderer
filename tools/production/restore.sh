#!/usr/bin/env bash
set -euo pipefail
backup=${1:?Use restore.sh BACKUP_DIRECTORY NEW_ENV_FILE}
environment=${2:?Supply a generated environment for a fresh target}
project=$(sed -n 's/^STUDIO_PROJECT=//p' "$environment")
[[ "$project" =~ ^[a-z][a-z0-9-]+$ ]] || { echo 'Invalid target project.' >&2; exit 1; }
if [[ -n "$(docker volume ls -q --filter "label=com.docker.compose.project=$project")" ]]; then echo 'Restore requires fresh target volumes; existing storage is preserved.' >&2; exit 1; fi
(cd "$backup" && sha256sum --check SHA256SUMS)
dc=(docker compose --env-file "$environment" -f compose.production.yaml)
"${dc[@]}" config --quiet
"${dc[@]}" up -d --wait database render-worker build-worker
"${dc[@]}" exec -T database pg_restore -U styleguide -d styleguide --exit-on-error --no-owner --no-acl < "$backup/database.dump"
"${dc[@]}" run --rm --no-deps --entrypoint tar app -C /app/var/catalogue -xzf - < "$backup/blobs.tar.gz"
"${dc[@]}" run --rm --no-deps app php bin/console styleguide:storage:verify
"${dc[@]}" run --rm --no-deps app php bin/console doctrine:schema:validate
"${dc[@]}" up -d --wait app preview-consumer maintenance
echo "Restore completed in fresh project $project. Verify saved content and repeat export/consumer installation."
