# Local production build and operations

This repository prepares immutable images locally. It does not push images, publish
Composer packages, obtain public certificates or deploy to a remote host. Docker Compose
is the runtime prerequisite; PHP, Composer and Node run in pinned containers.

```sh
make prod-init prod-build prod-migrate prod-up prod-check
```

`prod-init` generates random runtime secrets in ignored var/production/secrets and a
mode-0600 Compose configuration. It preserves an existing configuration. The enclosing
directory is mode 0700; individual secret files are read-only within their containers.
No account, default password, APP_SECRET or database URL is embedded in the image.
Compose local file secrets are bind-mounted files, not an encrypted secret manager.
Protect the host directory and back it up separately under your secret-management policy.

The default HTTP listener is http://localhost:8096, bound to loopback. There is no
source/vendor mount in production. App, consumer and maintenance use the same application
image with prod dependencies and precompiled AssetMapper/studio CSS; their root filesystems
are read-only. Writable cache/log/tmp are capped tmpfs; sessions and immutable blobs persist
in volumes. Workers have only their own endpoint token, private worker network and tmpfs.
PostgreSQL has a separate private network and no host port. Resource caps and non-root
identities are explicit. The official PostgreSQL entrypoint starts as root, initializes
owned storage and drops to postgres; application/worker processes are non-root from startup.

Create a real account using the interactive hidden password prompt:

```sh
docker compose --env-file var/production/compose.env -f compose.production.yaml exec app php bin/console styleguide:user:create --email=you@example.com --admin
```

There is no registration or production test-account command. Disable an account with
styleguide:user:disable --email=...; existing sessions and all its token authority fail.
Read tokens are managed in the authenticated administrator UI or token CLI. Do not put
passwords/tokens on command lines, in project config or in backups of source packages.

## HTTPS and trusted proxy

Production HTTP is an internal/loopback listener. Terminate public HTTPS at your reviewed
reverse proxy and permit ingress to the app only from that proxy. Configure TRUSTED_HOSTS
as an anchored host regex and TRUSTED_PROXIES as its exact IP/CIDR; the default trusts no
forwarded headers. The proxy must overwrite forwarded host/proto/port rather than trust
client values. Secure cookies follow the verified request scheme. No wildcard proxy trust.

Local TLS/proxy verification uses an internal CA and a separate network:

```sh
docker compose --env-file var/production/compose.env -f compose.production.yaml -f compose.local-tls.yaml build proxy
docker compose --env-file var/production/compose.env -f compose.production.yaml -f compose.local-tls.yaml up -d --wait
```

Open https://localhost:8443, trusting the local test CA explicitly if needed. This is not
a publicly trusted certificate. LOCAL_PROXY_SUBNET/IP can select a free private subnet
(default 10.250.187.0/24 and .2). Only that proxy IP is trusted by this override. The test
checks real TLS login, Secure/HttpOnly/SameSite cookies and rejection of forged forwarded
headers sent directly. Global Caddy options and Symfony proxy configuration were checked
against https://caddyserver.com/docs/caddyfile/options and
https://symfony.com/doc/current/deployment/proxies.html.

## Release and migration order

Run the development, browser and fresh-consumer gates first. Give each release a new
STUDIO_RELEASE tag in the private Compose env file; never replace a deployed release tag.
Build from committed Composer/npm locks and recorded base digests. Record actual image IDs
with `docker compose ... images --format json`; the local tags are not registry digests.
For a production host, use your registry's immutable image references after a separately
authorized push; this implementation performs no push.

Take a consistent backup, run `prod-migrate` once, then `prod-up` and `prod-check`.
Migrations do not run in every web entrypoint. New nullable preview lease columns permit
existing successful previews to remain readable. Old running jobs become queued during
that migration and are recovered through the durable queue. Stop consumers before schema
changes and retain the previous compatible images. `make prod-down` preserves volumes.
Never use down --volumes as routine shutdown. For destructive schema changes, restore a
verified backup into new storage and switch deployments; do not blindly run down migrations.

## Limits, cleanup and diagnosis

Render child: 128 MiB, 2 seconds, 1 MiB HTML. Compiler child: 192 MiB, 10 seconds,
fixed CLI child 128 MiB/7 seconds, 2 MiB compiled assets. Containers also cap memory,
CPU and processes. Imported JavaScript is parsed/bundled, never evaluated on the server.
Compiler tmpfs is cleaned before/after each request, including killed children. Graceful
shutdown stops accepting builds, bounds remaining work and kills the child process group
if required. PHP CLI pcntl lets Messenger finish bounded work on SIGTERM. A killed consumer
leaves its queue message for 60-second redelivery; 45-second attempt leases and attempt-
specific output files prevent an old worker from completing/replacing the new attempt.

Preview jobs expire after 30 minutes: at most three pending and the latest 60 retained per account; older terminal jobs
are expired as new work arrives, so sustained editing remains usable;
preview files share a 512 MiB quota. Exports expire after 30 minutes with 20/100 MiB per
account, serialized across processes. Immutable archives have a 2 GiB/20,000-object initial
profile. These are deliberately bounded initial limits; adjust reviewed code/config and
capacity together rather than disabling validation to handle a large catalogue.

`styleguide:cleanup` defaults to dry-run. `--apply` removes expired DB jobs/exports,
expired preview files, hour-old orphan/temp files and unreferenced release blobs older than
24 hours. Referenced releases are never deleted. Reusing an immutable archive refreshes its
grace age while holding the same storage lock as cleanup. A maintenance service runs apply
every minute. Review reported counts/bytes and storage capacity. Do not remove arbitrary
files from the blob volume or an installer journal to resolve a quota error.

`styleguide:storage:verify` checks every referenced release archive's hash and shared package
contract. Health /health is liveness; /health/ready checks DB and necessary workers without
exposing credentials. Monitor container health, queue wait/failure transport, job timing/hash
logs, cleanup output, and disk growth. Production app logs keep only diagnostic IDs/categories;
private messages, source, headers, cookies and exception arguments are discarded. Doctrine
SQL/profiling and debug/profiler dependencies are absent/disabled in production.

## Consistent backup and fresh restore

```sh
bash tools/production/backup.sh var/backups/reviewed-snapshot
# Generate a separate target configuration; do not use existing target storage.
docker compose run --rm app php tools/production/init.php restore
# Set var/restore/compose.env STUDIO_RELEASE to the backed-up image tag and use a free port.
bash tools/production/restore.sh var/backups/reviewed-snapshot var/restore/compose.env
```

Backup stops web writes, queue consumer and maintenance, verifies referenced archives,
then records pg_dump, immutable/private blobs, image IDs and SHA256SUMS. Services restart
through an exit trap. The snapshot includes private source and password hashes: keep its
directory private and encrypt/offsite-copy according to your infrastructure policy.
Runtime secrets stay separate. Restore refuses a target with existing Compose volumes,
verifies backup checksums, restores the database and blobs to fresh storage, verifies hashes
and schema, then boots the app. It does not overwrite existing consumer files or old storage.
Restore also needs a browser check of saved source/theme/composition and another real
export/consumer installation; a pg_dump success alone is not a completed recovery exercise.

The local verification driver is `make prod-test` after production boot and development
bootstrap. It runs production Chromium, local TLS, private-boundary inspection, a fresh
consumer, real graceful stop/SIGKILL redelivery, backup, restart, fresh restore and repeated
consumer installation. It disables its random accounts afterward; create your own admin
for use. The checked-in CI workflow calls the same commands and does no push/deployment.
Its checkout action is pinned to the v4 tag SHA actually resolved with git ls-remote.

If an external prerequisite download fails, fix connectivity/trust and retry; TLS verification
stays enabled. The driver retries importmap downloads three times. A reviewed interrupted
verification can resume with PRODUCTION_FROM=consumer, or PRODUCTION_FROM=restored-consumer
plus PRODUCTION_RESTORE_ENV/PRODUCTION_BACKUP for an already verified restored target.
These modes reuse recorded evidence; they do not claim to rerun skipped stages. Full CI
runs the default complete sequence. Normal operations never need these test-only flags.

Operational job records are emitted at info level with job/input hashes, queue wait,
duration and error category; private data still passes through the global redactor. CSS
policy checks decode escaped directive keywords in both the shared PHP validator and fixed
Node parser before rejecting executable/import configuration. Source modules remain parse-only.
