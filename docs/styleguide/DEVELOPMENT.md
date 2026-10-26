# Local development

Only Docker Engine with Compose and GNU Make are required on the host. PHP, Composer,
Node and browser test dependencies run in containers. This Compose configuration is
for local development. Production hardening is M08.

From a fresh checkout:

```sh
make bootstrap
```

Open <http://localhost:8095>. Set `STUDIO_PORT=...` before running Make to change the
loopback port. The database has no published port. Optional mail testing uses
`docker compose --profile mail up -d mailer` (the pre-existing Mailpit service).

Make passes the current Linux/WSL UID and GID to Docker. Files created by Composer
and npm therefore belong to you. Run Make as your regular user, with Docker socket
access. On a machine with a different UID/GID, rebuild with `make build`; direct
Compose commands can set `LOCAL_UID` and `LOCAL_GID` explicitly. Do not use chmod 777.
Avoid `make -j bootstrap`: dependency installation and compilation run in order.

```sh
make check
make test
make browser
make console ARGS=about
make console ARGS=debug:router
make logs
make down
```

`make browser` runs Chromium in a pinned Playwright container matching the npm lock. Tests use the app
on the private Compose network; no host browser is required. `make test` creates and
migrates a separate PostgreSQL `styleguide_test` database. It never resets the studio
database. Test fixtures own their test data.

Create your first administrator interactively (no default account or password):

```sh
docker compose exec app php bin/console styleguide:user:create --admin
```

The command asks for email and a hidden, confirmed password of at least 12 characters.
For other accounts use `--role=ROLE_EDITOR` or `--role=ROLE_READER`. Administrators
inherit editor and reader permissions. Accounts are stored in PostgreSQL; registration
is private. Login and every mutation require CSRF. Publishing is deferred until render
and build validation exist.

`make browser` provisions random, temporary dev/test accounts for all three roles,
stores credentials in ignored `var/browser/accounts.json` (mode 0600), and removes the
accounts and file after the test run. Interrupted runs can be cleaned with
`make console ARGS='styleguide:test:accounts --cleanup'`. No browser-test account is
available in production.

`GET /health` checks HTTP liveness; `GET /health/ready` checks database connectivity
and returns 503 on failure. The Compose readiness check uses the latter. Framework
commands and migrations run explicitly; the web entrypoint does not run migrations.

After editing studio Twig, JS or styles, run `make assets`. AssetMapper serves local
modules; Node only compiles CSS and runs tests. `assets/styles/app.css` is generated.
Keep the existing Composer and npm lockfiles in version control. No external CDN is
required for the studio UI.

PostgreSQL and catalogue blobs use named volumes. `make down` retains them. Never use
`docker compose down -v` as routine shutdown. The development credentials in Compose
are local defaults; there is no default studio account.

Base image tags are pinned to registry index digests in Dockerfiles/Compose. Actual
versions, verification commands and milestone evidence are in `agent-kit/STATUS.md`.
Update pins deliberately and rerun the checks. The initial skeleton already supplied
Symfony 8.1, Doctrine, Twig, Security, AssetMapper, Stimulus, Forms and PHPUnit; these
are retained rather than generating a replacement app.

## Catalogue workflow

Load validated example drafts without overwriting existing items:

```sh
make console ARGS=styleguide:fixtures
```

Browse `/catalogue`, filter by kind, search names/titles/descriptions/tags, and sort or
page through database results. Editors can create an item, edit its metadata, manifest
and source map, then save a draft revision. Names and kinds stay fixed after creation.
File hashes/byte counts are regenerated on draft save. Invalid props schemas, defaults,
examples, unsafe paths and duplicate names are rejected. Invalid Twig/JS can remain in
a draft for repair; saved code is displayed as escaped text until M02 adds isolation.

Session-authenticated JSON endpoints:

| Request | Behaviour |
| --- | --- |
| `GET /api/v1/csrf` | Return a session token for the `X-CSRF-Token` mutation header |
| `POST /api/v1/items` | `name`, `kind`, `title`, optional `description`/`tags`; 201 draft |
| `GET /api/v1/items/{id}/draft` | Metadata, manifestData, sourceMap, revision; quoted revision ETag |
| `PUT /api/v1/items/{id}/draft` | `title`, `description`, `tags`, `manifestData`, `sourceMap`; requires `If-Match: "1"` |

Mutations require ROLE_EDITOR (ROLE_ADMIN inherits it). Reads require ROLE_READER.
Missing revision returns 428; stale revision returns 412 with no writes. Forms keep
unsaved submitted values on conflict. Errors include code/message/fieldErrors/requestId.
Drafts are JSONB documents preserving JSON object/array distinctions. The current text
profile caps files at 1 MiB each, 2 MiB total and 100 files; props/schema/examples have
64 KiB bounds. Props use the documented v1 subset, with nonrecursive local `$defs`
references. Executable URL schemes in URL/href/src/action props are rejected. Binary
uploads, full dependency resolution and publication are later milestones.

For the explicit restart acceptance check:

```sh
docker compose exec -T app php bin/console styleguide:test:accounts
docker compose run --rm browser node tests/Browser/restart.mjs prepare
docker compose restart app database
docker compose up -d --wait app database
docker compose run --rm browser node tests/Browser/restart.mjs verify
docker compose exec -T app php bin/console styleguide:test:accounts --cleanup
```

Keep the same random accounts between prepare and verify. This creates and edits a real
component, restarts both services, then checks source, title, tags, revision and search
from a new browser. It retains the acceptance item. The studio targets browsers with
native import maps; AssetMapper's external CDN polyfill is disabled.
