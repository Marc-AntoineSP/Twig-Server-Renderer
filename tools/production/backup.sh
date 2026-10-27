#!/usr/bin/env bash
set -euo pipefail
umask 077
read -r -a dc <<< "${PRODUCTION:-docker compose --env-file var/production/compose.env -f compose.production.yaml}"
destination=${1:-var/backups/$(date -u +%Y%m%dT%H%M%SZ)}
if [[ -e "$destination" ]]; then echo 'Backup destination already exists; refusing overwrite.' >&2; exit 1; fi
mkdir -p "$destination"
restart() { "${dc[@]}" up -d --wait app preview-consumer maintenance; }
trap restart EXIT
"${dc[@]}" stop maintenance preview-consumer app
"${dc[@]}" run --rm --no-deps app php bin/console styleguide:storage:verify
"${dc[@]}" exec -T database pg_dump -U styleguide -d styleguide --format=custom --no-owner --no-acl > "$destination/database.dump"
"${dc[@]}" run --rm --no-deps --entrypoint tar app -C /app/var/catalogue -czf - . > "$destination/blobs.tar.gz"
"${dc[@]}" images --format json > "$destination/images.json"
(cd "$destination" && sha256sum database.dump blobs.tar.gz images.json > SHA256SUMS)
echo "Consistent local backup written to $destination. Keep runtime secrets separately."
