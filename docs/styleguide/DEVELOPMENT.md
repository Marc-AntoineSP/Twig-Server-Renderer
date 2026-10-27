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

## Local consumer installer (M07)

The installer is a local Composer package, not a Packagist release. `make consumer`
creates a fresh marked Symfony 8.1 fixture under ignored `var/consumer`, installs the
committed Composer/npm locks, publishes test releases to the actual private registry,
and runs install, production AssetMapper, Chromium, conflict, update/remove and real
SIGKILL/recovery checks. It replaces only its marked test directory. The consumer is
available at http://localhost:8110; test registry accounts/tokens are removed afterward.

For an existing compatible consumer, add Composer path repositories for both local
`packages/catalogue-contracts` and `packages/catalogue-installer-bundle` (version 0.1.0),
require `styleguide/catalogue-installer-bundle:0.1.0`, and register
`Styleguide\Installer\CatalogueInstallerBundle` in config/bundles.php. The fixture's
composer.json shows the exact path-repository configuration; adjust its absolute path.
Do not copy the fixture over an existing application.

Provide PHP 8.5, Symfony 8.1, Twig 3.30, AssetMapper, StimulusBundle/browser Stimulus 3,
Node 24, Tailwind/CLI 4.3.3 and daisyUI 5.7.50. Source validation uses fixed Acorn 8.19.0
and Lightning CSS 1.32.0; add these to the consumer's trusted toolchain and commit its
npm lock. No package install runs npm, imported scripts, Composer hooks or source JS.
The parser only reads source. Tailwind invokes the consumer's already trusted config.
The default CSS input is assets/styles/app.input.css, separate from compiled app.css:

```css
@import "tailwindcss" source(none);
@source "../../templates";
@source "../controllers";
@plugin "daisyui";
```

Exclude the input from AssetMapper. The bundle excludes package CSS compiler sources.
Use `const app = startStimulusApp()` in assets/stimulus_bootstrap.js. Compile the initial
CSS, then `php bin/console styleguide:doctor` reports concrete missing setup. Custom
integration paths can be set using registry:add --css-input/--css-output/--bootstrap
before installing packages. Configure `<html data-theme="{{ styleguide_theme|default('light') }}">`
in the consumer base template for installed theme selection.

```sh
php bin/console styleguide:registry:add local https://catalogue.example/api/v1/registry
php bin/console styleguide:require awui/alert@1.0.0 --registry=local --dry-run
php bin/console styleguide:require awui/alert@1.0.0 --registry=local
php bin/console styleguide:theme:install awui/ember@1.0.0 --registry=local
php bin/console styleguide:install /path/to/resolved-collection.zip
php bin/console styleguide:update awui/alert@1.1.0
php bin/console styleguide:remove awui/alert
php bin/console styleguide:doctor --recover
php bin/console asset-map:compile --env=prod --no-debug
php bin/console cache:clear --env=prod --no-debug
```

Use HTTPS; --allow-http explicitly permits local development origins only. Registry
credentials are read from STYLEGUIDE_TOKEN_LOCAL or mode-0600
$XDG_CONFIG_HOME/styleguide/auth.json (default ~/.config/styleguide/auth.json), shaped
as `{ "local": "<read token>" }`. They never enter project config, locks or archives.
Redirects cannot forward them to a different origin. Dry-run stages a plan, candidates,
licenses, hashes and the actual browser controller code for review. Installed browser
code is trusted by the consumer; review its origin and source before deploying.

The installer locks the project, verifies exact dependency versions and archive/file
hashes, parses every Twig/JS/CSS source, compiles proposed CSS before changing destinations,
and journals atomic writes with lockfile last. Modified owned/managed files and even
byte-identical unowned collisions abort with staged candidates. There is no force mode.
Recovery restores the interrupted operation's original hashes and refuses later external
edits. Retain such journals for manual review. Dry-run/conflict candidates under
.styleguide/staging have no active journal and may be removed after review. Do not remove
active journals or .styleguide/packages. Run install as an offline release preparation;
then compile AssetMapper/clear cache and switch the consumer deployment atomically.

## Showcase and renderer acceptance

`php bin/console styleguide:showcase --publish` adds twelve local sample packages and
validates their defaults/examples through the real workers. Button variants use full
literal class maps; the alert keeps its supplied MIT licence. New sample code retains
UNLICENSED declarations. Native accordion, cards/navigation/fields, keyboard tabs,
focus-restoring dialog and a progress controller demonstrate real portable behaviour.
Progress clears its interval/listener on disconnect, pauses for reduced motion and supports
manual advance/reset. Pricing and dashboard are exact-release compositions. Re-running
seeding preserves modified drafts and every immutable release; changed bundled definitions
never overwrite old versions or silently enter a composition. This is optional operator
seeding, not production startup or a default account.

Preview property fields come from bounded schema properties alongside raw JSON. They alter
transient props; explicit Save renderer settings persists them without changing the draft.
Colour scheme, bounded surfaces and a decorative device frame are real renderer controls.
The frame leaves CSS viewport width unchanged. Theme comparisons have reset and script
controls. Theme choices are bounded but include the currently stored older selection.
Typed-theme names cannot steal the workshop sample; it chooses a free sample suffix.

Catalogue cards show actual revision/release/dependency metadata and cached release
thumbnails. Thumbnails are default-prop render results, displayed in lazy opaque iframes
with scripts disabled. This derived cache is optional (256 KiB/thumbnail, 50 MiB total);
large or uncached releases still open normally. It grants no new source execution path.
Only validated immutable source is authoritative. Copy-command controls use the Clipboard
API or select the real command for manual copying; credentials stay outside it. Duplicate
draft remaps owned template/controller paths, retains composition/source ownership and
leaves original drafts/releases untouched.

## Local fonts and website presets

Application appearance is documented in [APPEARANCE.md](APPEARANCE.md). **Apply theme**
now selects your personal application snapshot; **Preview showcase** retains the independent
renderer action described below. Header light/dark/system controls, Save & Apply and
temporary editor trials share the existing theme/font data. Run the additive migration
before using this extension, and `make appearance-production` for its isolated production
asset check. Component previews and their export/preset choices remain independent.

Each theme detail now includes a server-rendered **Component showcase** with 65 panels,
category/search controls and independent family variant links. Its sandboxed document uses
that detail theme's palette/fonts even when the application has another theme applied.
`make assets` builds both studio and showcase assets; `make theme-showcase-production`
checks real production CSS plus the existing appearance flows in an isolated fixture.
See [THEME_SHOWCASE.md](THEME_SHOWCASE.md) for coverage, interactions and browser limits.

The updated development application is at http://localhost:8095: `/fonts` contains
the local library and `/themes` contains the managed website presets. Start it with
`docker compose up -d --wait app database render-worker build-worker preview-consumer`.
The immutable production application at port 8096 has its own image, database and accounts;
restarting an earlier production image does not include development changes or imports.
Create a development login with `docker compose exec app php bin/console
styleguide:user:create --email=you@example.com --admin`, using the hidden password prompt.
The longer production Compose command creates an account only in production.

The development catalogue now contains the curated `popular` font collection and
`website-inspired` theme collection. Browse `/fonts` or `/themes`. The studio continues
to use its own typography. Theme body, heading and code choices, real weight/style
selections and renderer overrides are saved independently. The font browser provides
search, category filters, editable French/English specimens and local licence text.
Specimens load only after Preview, and include only the requested weight/style and its
Unicode subsets. Theme previews embed the effective selected families in the opaque
iframe; they retain `sandbox="allow-scripts"`, data-only font CSP and system fallbacks.
There are no font preloads or runtime font-CDN requests.

Acquisition is an operator maintenance operation, separate from the offline render/build
workers and public web requests. Exact maintenance dependencies are in
`tools/library/package-lock.json`; npm scripts are disabled during installation.
Font package versions, npm integrity, upstream authority revisions and individual asset
hashes are in `resources/library/fonts.lock.json`. The Google Fonts metadata verifies
the family and licence; the pinned Fontsource tarball identifies the downloaded bytes.
Fontkit checks the actual character maps, Unicode ranges, weight axes and italic/OS/2
metadata. Each supported style/weight group covers the 137 checked English/French
characters. These checks are not an accessibility guarantee.

```sh
make fonts-dry-run
make fonts-sync
make fonts-sync FONT_ARGS='--family=inter'
docker compose exec -T app php bin/console styleguide:fonts:verify
make themes-dry-run
make themes-collect
make themes-research RESEARCH_ARGS='--reference=github'
make themes-candidates
docker compose exec -T app php bin/console styleguide:themes:collect --dry-run
docker compose exec -T app php bin/console styleguide:themes:collect
```

`fonts:sync` validates and atomically registers already staged maintenance downloads;
`make fonts-sync` performs acquisition and registration, continuing eligible families
after individual failures. `themes:collect` validates staged candidates with the existing
theme importer, then creates managed drafts and immutable local theme releases. The Make
wrapper also runs the online research and candidate translation. Dry-run does not modify
the catalogue or register assets. Keep `var/library-cache` and `var/library-staging` to
avoid reacquisition. Font blobs and versioned family metadata live in the existing
`catalogue_blobs` volume under `fonts/`; normal catalogue backups include them.

Managed presets have stable slugs, source surfaces, retrieval dates, source hashes and
per-token observed/adapted/derived provenance. Apply opens the shared showcase and saves
the current account's renderer selection. Duplicate/Edit creates an independent copy.
Managed seeds reject direct edits. Reruns preserve copies and existing data; a changed
managed candidate requires a newer declared collection version and emits a diff before
creating a revision. Earlier immutable releases remain available. Failed online research
retains a previous valid evidence snapshot with its real retrieval date and records the
failed attempt; it never invents a palette from an unstyled or blocked page.

The source editor and composition builder both expose body, heading and code fonts plus
immutable theme selection. Save renderer settings persists those choices for the current
account and item, separately from source/tree edits. Picking another published theme
restores theme inheritance for all three roles; explicit overrides remain available.

Add families/subsets in `resources/library/fonts.json`, resolve and review the pinned
source metadata, then run the same importer. Add website selectors/token mappings in
`resources/library/websites.json`; collection reads these manifests without a feature
rebuild. Retain and review the generated source evidence and candidates. Culori 4.0.2
converts observed colours to sRGB hex, clips out-of-gamut channels and composites alpha
on the recorded base surface. Unsupported properties, gradients and shadows are omitted.
The optional `geometry.highContrastBorders` setting uses canonical content/error colours
for visible field boundaries; it is generated consistently in preview and consumer CSS.

Theme `bundle.zip` is now an inventoried portable package, accepted by the existing
package importer and `styleguide:install`. It contains only the selected local families,
their real faces and licences, with relative font URLs. JSON/YAML envelopes encode WOFF2
as canonical `base64:` strings; ZIP payloads contain raw WOFF2. Individual CSS downloads
retain relative URLs and should be used with the bundle's fonts. Static exports embed
fonts in HTML and also include the font files/licences and checksums. No studio server
or public font service is required after export. The installer preserves conflict,
ownership, staging, rollback and lockfile checks, rebases emitted font URLs and maps font
assets through AssetMapper while excluding the editable CSS sources.

```sh
docker compose run --rm --no-deps browser node tests/FontConsumer/fontfaces.mjs
docker compose run --rm --no-deps browser node tests/FontConsumer/showcases.mjs
make font-consumer
```

The font consumer test creates an absent, uniquely named fixture under `var/`, leaving
existing consumers in place. It creates temporary test credentials when none exist and
removes only the credentials it created on exit; existing test credentials are reused.
An exit trap stops its own started consumer on failure and retains diagnostic artifacts. It uses the already bootstrapped studio's exact cached
Stimulus 3.2.2 dependency, with verified copy hashes, when preparing the consumer. The
consumer runs on an internal network with no host port; its browser rejects external
requests. The test compiles production assets, checks real decoded fonts and controls,
repeats after restart and retains artifacts in `var/library-verification`.
