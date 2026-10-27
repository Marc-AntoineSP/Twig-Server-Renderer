# Docker and operations

## Development

M00 adapts agent-kit/reference into the real repository. The reference Dockerfile,
compose.yaml and Makefile cover the initial app/database/tooling bootstrap only. They
intentionally do not pretend to be the production multi-worker deployment.
Create or retain the Symfony skeleton first using the app container's Composer binary.
An empty target requires a temporary bootstrap directory because kit files already make
the repository nonempty. Copy generated Symfony files only after reviewing collisions.
Do not run composer create-project directly over an existing application.

Use the latest patched compatible release within FrankenPHP 1.13 and PHP 8.5/Trixie.
Verify the actual remote tag, PHP version and image digest. Record them with the lockfiles.
Floating development tags are acceptable while bootstrapping; production images require
reviewed digests or immutable build tags. Do not infer a whole multi-architecture image
digest from a single platform layer digest. Check advisory/audit output at implementation
time; this kit does not certify any future image as vulnerability-free.

M02 adds render-worker and build-worker images and internal network. M03 adds the
app-side queue consumer if used. Make targets run Composer, PHP console, Node and tests
in containers. Node is not required on the developer's Windows/WSL host.
Document bind-mount ownership and UID/GID handling on WSL/Linux without chmod 777.

## Production service topology

| Service | Network / exposure | Persistence / privileges |
| --- | --- | --- |
| app | HTTPS ingress; DB and worker networks | App blob volume; least privilege |
| job-consumer (if async) | DB and worker networks; no published port | Same owned blobs; job orchestration |
| db | Dedicated private network, no published port | PostgreSQL volume |
| render-worker | Internal worker network only | No app mounts; tmpfs per job; non-root |
| build-worker | Internal worker network only | Fixed toolchain, bounded tmpfs; non-root |

Worker network is internal and separate from DB network. Only app/consumer bridge those
networks. Workers expose narrow authenticated internal endpoints with maximum body sizes
and job limits; a client cannot choose a command line or filesystem output destination.
Worker authentication secrets are scoped to that endpoint, not APP_SECRET or DB credentials.
Container resource limits complement subprocess limits. Workers have no Docker socket,
network package downloads, host paths or source bind mounts. Drop unnecessary capabilities.

Use FrankenPHP's Caddy directly for HTTPS if this is the public entry point, persisting
/data and /config. Behind an existing Caddy reverse proxy, serve internal HTTP and configure
trusted proxies/hosts and forwarded headers precisely. Avoid conflicting certificate
automation at two layers. Maintain WebSocket/SSE only if actually introduced.
Start in classic FrankenPHP mode. Worker mode for the studio is an optional measured
optimization after shared-state/reset behaviour is tested; it does not replace isolated
per-job Twig execution.

## Image build and release order

1. Resolve and commit Composer/npm lockfiles; audit and test in CI.
2. Install production PHP dependencies and fixed Node toolchain from locks.
3. Build studio CSS/JS resources, then asset-map:compile for APP_ENV=prod.
4. Build separate worker images with only their code/dependencies; no production secrets.
5. Run unit/integration/browser/consumer-install checks against these images.
6. Take backup; run reviewed DB migrations in a single release step.
7. Start/update services with APP_DEBUG=0 and real secrets; verify health/readiness.

No migrations inside every web replica's entrypoint. No development source mounts,
watchers, test dependencies, exposed Postgres or default credentials in production.
Do not warm production caches by accidentally embedding build-time fake secrets as real
runtime configuration. Pass environment-specific values at runtime and test that boundary.

## Health, logs and lifecycle

Separate liveness (process responds) from readiness (DB and necessary workers available).
Return only high-level status publicly. Health checks have explicit timeouts and startup
grace periods. Graceful shutdown stops accepting jobs, finishes/cancels bounded work and
requeues abandoned jobs. Use idempotent job state transitions and retry only transient
failures; invalid templates do not become infinite retry loops.
Log request/job IDs, timings, input hash and error categories. Redact tokens, session data
and private source by default. Track queue wait, render/build failures, timeout rate,
cache size, blob volume growth and DB disk usage. Enforce TTL/quota cleanup.

## Backup and recovery

Persist and back up both PostgreSQL and the immutable blob volume. Stop publishing or
use a consistent backup boundary so DB references cannot outlive missing blobs. Preserve
application secrets separately under the deployment's secret-management policy.
Preview caches, temp jobs and compiled outputs are reproducible; source uploads/releases
and typed theme data are not expendable. Garbage collection uses a grace period and
referenced-object mark phase; start with dry-run and never delete referenced releases.

M08 must restore to a fresh volume/database and verify a saved draft, theme, release
download, dependency chain and consumer installation. A successful pg_dump alone is not
a recovery test. Rollback restores compatible code/images; destructive migrations need
an explicit backup/restore plan. Never use docker compose down -v as routine shutdown.
