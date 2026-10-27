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

## Isolated previews (M02)

`make build` builds the studio and both workers from locks. `make up` starts the app,
PostgreSQL, render-worker, build-worker and the app-side Messenger preview consumer.
After changing consumer PHP code, run `docker compose restart preview-consumer`.
After changing worker code/dependencies, rebuild that worker and recreate it.

On a draft's edit page, **Build preview** uses the current unsaved manifest/source/props.
The server validates and snapshots input, resolves exact immutable dependencies, stores
an owner-scoped job and sends its ID through the existing Doctrine Messenger queue.
The app-side consumer calls two internal workers and stores the resulting document
outside public/. The browser polls and places the returned document only in an opaque
`sandbox="allow-scripts"` srcdoc frame. Reset and disable-scripts controls are available.
Publishing remains M06. The bundled alert can already render and dismiss in preview.

Render uses a fresh PHP process with 128 MiB, a 2-second wall limit and 1 MiB HTML cap.
The dedicated Twig 3.30 `Sandbox` has a strict policy and finite ArrayLoader; props
are bounded arrays/scalars without app/request/user/services. All template includes
must be literal names in the locked closure. Cycles, unknown includes, raw/PHP-call
paths and unsupported tags/functions/filters fail. Compilation parses untrusted JS
with fixed Acorn/esbuild, bundles only Stimulus and declared local modules, and never
imports user JavaScript into Node. Tailwind scans materialized database source text.
Uploaded CSS plugins/configuration and network/filesystem/dynamic JS imports fail.

Workers run non-root with no studio source, DB credentials or Docker socket, read-only
filesystems, bounded tmpfs, CPU/memory/PID caps and an internal network separate from
PostgreSQL. Render and build have distinct scoped development auth tokens. No worker
ports are published. The build supervisor kills a fresh child process group after 10
seconds; its normal/error paths remove temporary job data. Render's supervisor survives
child failures. Readiness now checks PostgreSQL and both workers.

The iframe CSP denies network resources, objects, forms and nested frames; embedded
scripts use a random nonce. Messages are accepted only from the current iframe window,
with the current random channel and an exact bounded resize schema. Parent DOM/storage
and studio CSS stay outside the preview. Opaque isolation is not a promise against all
browser navigation/network abuse or a JS loop freezing a browser; reset/disable scripts
is a recovery control, and preview inputs must never contain studio secrets.

Preview API: `POST /api/v1/preview-jobs` requires itemId, sequence and a 32-hex random
channel, with optional unsaved manifestData/sourceMap/props; returns 202 plus job ID.
`GET /api/v1/preview-jobs/{id}` reports actual state/error; `/document` returns JSON
html/sequence only for a successful owner-authorized job. `DELETE` cancels queued or
marks running work cancelled so its eventual output is discarded. All mutations use
header CSRF. There are at most three pending jobs per account, guarded across containers
by Symfony Lock's PostgreSQL advisory store; jobs expire after 30 minutes. Retention
cleanup and production configuration are completed in later milestones.

## Live editor and renderer workspace (M03)

The source editor has keyboard-accessible Twig, JavaScript, Props and Examples tabs.
File selectors edit existing sources; Add Stimulus controller creates an owned source
and registration, then shows the identifier to bind in Twig. Advanced JSON remains
available for full manifests/source maps. Save draft explicitly persists source and
metadata with the loaded revision; unsaved previews never alter the draft. Unsaved
changes are labelled and warn before navigation. Invalid advanced/default JSON cannot
silently submit older editor data.

Preview starts on open and rebuilds 300 ms after the latest edit. Pending/error states
mark an existing frame stale. Syntax failures include the logical file and line while
keeping the last good frame. Requests/jobs that become obsolete are cancelled or their
bounded in-flight output is discarded. Creation responses are collected even when old,
so their job IDs can be cancelled; each poll/document response is checked against the
latest sequence. PostgreSQL conditional claim/finish transitions prevent duplicate
claims and stop a cancelled job becoming successful after a late result.

Viewport buttons set real iframe widths of 375/768/1280 px; custom width is 320–1920.
Zoom is 50/75/100% visual scaling and preserves CSS viewport width. Root font size
12–24 px also scales rem spacing. Text size is inherited; explicit component classes
retain their sizes. Body/heading fonts use the local system font registry. Reset frame
works immediately; Reset settings restores defaults; Reset to example restores selected
example/default props. None executes code in the studio DOM.

Save renderer settings persists a private workspace preset per user/item, separate
from the draft, including viewport, zoom, fonts, text, script toggle and sample props.
Reload restores it. `GET/PUT /api/v1/items/{id}/renderer-preset` enforce editor access,
owner-specific selection, header CSRF on PUT, bounds and props validation. Saving a
preset validates against the saved props schema; save a changed schema as a draft first.
Binary assets and named shared design-system presets are later capabilities.

Themes live at `/themes`. Create a named token draft, edit all semantic/content colours,
geometry and typography, then Save theme. The two opaque preview frames compare current
edits against a saved theme without applying their CSS to the studio. System sans, serif
and monospace are the supported font registry; this profile accepts no font uploads or
remote fonts. Publish theme release validates/compiles and stores an immutable snapshot.
Published themes appear in source-editor renderer settings, independently of source saves.

Theme JSON is authoritative. JSON, safe YAML and this studio's generated CSS can validate
before import; applying over an existing theme requires its revision. Generated CSS carries
canonical token data and must match regenerated declarations. Manual CSS/token conflicts
are rejected. Downloads label editable Tailwind source separately from sample-only compiled
`preview.css`; an output bundle includes integration instructions and grants no licence.

Blocks and layouts created in the catalogue use composition mode. Publish component
versions first, then add exact immutable references with concrete instance props. Tree
buttons support reorder, indent/outdent, duplicate and remove; arrow keys select rows.
Containers use bounded tags/literal utility classes. Text uses a Twig literal serializer
and an explicit escape filter, including when it contains Twig delimiters. The same
archive resolver, sandbox and compiler render nested compositions and standalone items.
Save composition persists a revision; preview is transient. Generated Twig/dependencies
are owned by meta/composition.json and cannot be edited through the source API. Duplicate
and convert to source creates another named item and keeps the original tree unchanged.
Source-owned items have their visual builder disabled. No database migration was needed:
composition source belongs to the existing versioned, validated source map.

The private registry is `/api/v1/registry/{namespace}/{slug}` with exact-version
`/{version}/manifest` and `/{version}/archive` endpoints. It accepts Bearer read tokens
through a separate stateless firewall. Studio cookies alone do not authorize registry
requests. Tokens cannot create studio sessions. Every request checks active owner,
expiry/revocation and package scope before ETag handling; downloads remain private.
Administrators manage tokens at `/registry-tokens`, where plaintext is shown once, or:

```
make console ARGS="styleguide:token:create admin@example.test --scope='awui/*' --days=30"
make console ARGS="styleguide:token:revoke TOKEN_ID"
```

Only hashes persist. Keep plaintext outside project configuration/lockfiles. Token pages
and registry requests are excluded from development profiling. Production profiler services
are absent. Download integrity is reported by archive SHA-256 in package metadata and headers.

Package transfer is available from catalogue release cards and `/imports`:

- Source ZIP preserves the exact immutable archive bytes and per-file inventory.
- Resolved collection ZIP includes `index.json`, all transitive package ZIPs and their
  SHA-256 values. An optional immutable theme is another collection root. No registry
  access is needed to import a collection. Missing/unreachable archives are rejected.
- JSON/YAML use `{schemaVersion: 1, manifest: {...}, sources: {path: text}}` envelopes.
  They preserve JSON objects versus arrays and are repacked canonically when imported.
- Static ZIP contains `index.html` with embedded compiled CSS/JS, labelled compiled
  copies and all dependency licence declarations. It runs offline without studio URLs.
- Theme output/package downloads distinguish editable Tailwind sources from compiled
  sample CSS. Generated packages default to UNLICENSED and grant no extra distribution
  rights. Existing licence files/declarations are preserved, never silently replaced.

Import Validate/Dry-run runs structural and semantic validation, exact graph resolution,
composition ownership checks and actual sandbox/build validation. Review the resulting
file/licence/dependency plan before Apply. Apply requires the current plan hash, checks
it again under a database transaction lock and installs immutable blobs before release
records. New items get imported drafts; existing drafts are preserved even when another
release version is added. Identical immutable archives skip; changed bytes at the same
version conflict. A failed validation/transaction does not partially change the catalogue.
Orphan blobs after a crash are handled by retention cleanup; release records never precede
blobs. Exports are owner-authorized, expire in 30 minutes, and have a 20-artifact/100-MiB
per-account quota. No token, hook or studio asset URL is put in packages.

The shared text profile bounds 100 files/package, 1 MiB/file, 2 MiB/closure, 10 MiB ZIP,
50 MiB inflated, 500 ZIP entries and 100:1 expansion. YAML parsing uses Symfony 8.1's
alias-rejection flag and a 32-level/20,000-line bound; it preserves empty objects and
sequences. Unused YAML anchors have no persisted representation. Custom types and all
alias references fail. Import render validation permits 64 variants with a 45-second
budget; split larger collections. ZIP/JSON remain alternatives for large YAML presentations.

Server-operator commands (run through `make console ARGS="..."`):

```
styleguide:import /path/package.zip --dry-run
styleguide:import /path/package.zip
styleguide:import /path/manifest.json --dry-run
styleguide:export namespace/name@1.0.0 --format=collection --output=/path/new.zip
styleguide:export namespace/name@1.0.0 --format=static --theme=namespace/theme@1.0.0 --output=/path/snapshot.zip
styleguide:theme:import /path/theme.json --dry-run
styleguide:theme:import /path/theme.json --revision=2
styleguide:theme:export namespace/theme@1.0.0 --output=/path/theme.zip
```

A CLI manifest can read only its adjacent declared, owned, nonsymlinked files. HTTP
imports require a complete ZIP or envelope and cannot read adjacent server files.
CLI imports are server-operator writes (updatedBy is null); they do not invent an account.
Exports refuse an existing output file. Theme draft replacement requires its revision.
Theme registry publication is an explicit action on an immutable theme release card.

`tests/Browser/roundtrip.mjs prepare` creates and exports a real component → block →
layout plus theme and writes ignored `var/roundtrip/` artifacts. After preparing temporary
accounts, run it with `docker compose run --rm browser node tests/Browser/roundtrip.mjs prepare`.
Clean up those accounts before another stack prepares its own accounts file. In a separate
Compose project with fresh database/blob volumes, build/migrate/start the same source,
create temporary accounts, and run `roundtrip.mjs verify` in that project's browser service.
It checks four new imported releases, exact normalized source/token data, pixel-identical
preview screenshots and nested Stimulus dismissal. Disable the development toolbar only
in the screenshot harness so account/timing overlays do not contaminate preview comparison.
The verified M06 project was `styleguide-m06-clean` on loopback port 8101; it is now stopped
with its test volumes retained. This is a clean development-store test, not a production claim.
