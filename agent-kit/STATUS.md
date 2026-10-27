# Implementation status

Last updated: 2026-10-27. Scope: M00–M08, including a local production build; no remote deployment or push.

| Milestone | Status | Evidence |
| --- | --- | --- |
| M00 Bootstrap | Complete | Fresh clone Docker bootstrap, lint, PHPUnit and Chromium all pass |
| M01 Catalogue/authentication | Complete | 43 PHPUnit tests/160 assertions, 5 Chromium tests, app/database restart verified |
| M02 Isolated preview | Complete | 66 PHPUnit tests/230 assertions, 9 Chromium tests; worker limits and isolation verified |
| M03 Live editor | Complete | 68 PHPUnit tests/248 assertions and 15 Chromium tests pass |
| M04 Theme laboratory | Complete | 71 PHPUnit tests/285 assertions and 17 Chromium flows pass |
| M05 Composition | Complete | 75 PHPUnit tests/325 assertions and 19 Chromium flows pass |
| M06 Imports/exports/registry | Complete | 93 tests/469 assertions, 21 browser flows, clean-store pixel-identical round-trip |
| M07 Consumer installer | Complete | 98 PHPUnit tests/494 assertions, 21 browser flows, fresh consumer production and SIGKILL recovery gates |
| M08 Production/recovery | Not started | Next milestone; local production only |

## Repository audit and preservation

Base: `main` at `c5d2497`, an existing Symfony 8.1 webapp skeleton. No application
controllers/entities or working catalogue existed. Retained Doctrine, Forms, Security,
Twig, AssetMapper, Stimulus, Turbo, Messenger and existing optional Mailpit service.
The original Compose ran only PostgreSQL and Mailpit; no app or Node service existed.
User work on arrival: modified AGENTS.md, staged README.md, untracked .codex/, .idea/,
SHA256SUMS, docs/styleguide/ and agent-kit/. Unrelated files are not staged or rewritten.
Only requested STATUS.md and directly used kit resources enter implementation commits.

Stack: `feature/m00-bootstrap` → `feature/m01-authentication` →
`feature/m01-catalogue` (later branches created after their predecessors pass).
Branches are local; no push or deployment is authorized.

## M00 implementation and resolved versions

Docker development stack, non-root UID/GID support, PostgreSQL volume, blob volume,
loopback port 8095, Make workflow, Twig home, database readiness, local AssetMapper
modules, fixed CSS toolchain and Chromium tests. Documentation:
`docs/styleguide/DEVELOPMENT.md`. The pre-existing generated CSS is now ignored;
`make assets` builds it from studio.input.css. AssetMapper excludes the compiler input.

Observed in containers: FrankenPHP **1.13.4**, PHP **8.5.12 ZTS**, Caddy **2.11.7**,
Debian **13.7 Trixie**, Symfony **8.1.9**, PostgreSQL **17.11**, Node **24.22.1**,
Composer **2.10.3**, Doctrine ORM **3.7.5** / DBAL **4.5.0** / bundle **3.3.3**,
Twig **3.30.0**, StimulusBundle **3.6.0**, browser Stimulus **3.2.2**,
PHPUnit **13.4.3**, Tailwind/CLI **4.3.3**, daisyUI **5.7.50**,
esbuild **0.28.2**, Playwright **1.64.1**. Composer and npm locks committed.
`composer.json` now explicitly requires PHP ^8.5 and symfony/uid 8.1.*.
Required extensions verified: ctype, iconv, intl, pdo_pgsql, zip, mbstring, dom, xml,
xmlwriter and OPcache. Symfony 8.1 requires PHP >=8.4; PHP 8.5 meets that requirement.

Registry index digests resolved with `docker buildx imagetools inspect` (not layer IDs):

| Image | Digest |
| --- | --- |
| dunglas/frankenphp:1.13-php8.5-trixie | sha256:ada77754f475352a9105b7961f89ab17ece97048514799532d2d27a1dea2fda7 |
| composer:2 | sha256:b6dcd72ca98fa19d0e5b8476de179859704891fad06dd9e0b96833d4c430a5fc |
| node:24-trixie-slim | sha256:43b9ba1142b8dcf33fa8f922ca1be85714adc444e0586c7cf7726daaeabc1bea |
| postgres:17-trixie | sha256:b8faeff7712663c2773a190181e1246e37e364b887611c04658c28b44e2408fe |
| mcr.microsoft.com/playwright:v1.64.1-noble | sha256:47b510dd9230bf0b9bfc9b20102241e577ea7a99334ab4c3ca19f19770e9b8ea |

Official references checked: [Symfony 8.1](https://symfony.com/releases/8.1),
[FrankenPHP Docker](https://frankenphp.dev/docs/docker/),
[Tailwind CLI](https://tailwindcss.com/docs/installation/tailwind-cli),
[daisyUI installation](https://daisyui.com/docs/install/). Exact installed versions
above come from runtime/lock output, rather than documentation assumptions.

## M00 actual checks

- `docker compose config --quiet`: pass.
- `docker compose build --pull`: app image built successfully.
- `composer require php:^8.5 symfony/uid:8.1.*`: Flex recipe/lock update successful.
- `make migrate up`: pass; both app/database healthy. No domain entities yet, so the
  migration runner correctly reports no registered migrations (allowed at bootstrap).
- `make check`: pass (strict Composer validation; container; 24 YAML; 2 Twig files).
- `make test`: **2 tests, 13 assertions**, pass against PostgreSQL test database.
- `docker compose exec -T app composer audit --locked`: no advisories.
- `docker compose run --rm node npm run build:css`: real Tailwind/daisyUI compilation passes.
  npm install audit: zero vulnerabilities.
- `curl --fail http://localhost:8095/`: 200 Twig page. `/health/ready`: 200 ready.
- Stopped this stack's database: `/health/ready` returned **503 unavailable** while
  `/health` remained **200 ok**; restarted database/app to healthy.
- `python3 agent-kit/tools/check_kit.py`: pass (kit fixture/static verification only).
- `make browser`: **1 Chromium test passed**, actual CSS + Stimulus interaction, no page errors.
- Fresh clone `/tmp/styleguide-m00-clean` (no vendor/node_modules/generated CSS):
  `STUDIO_PORT=8097 make COMPOSE='docker compose -p styleguide-m00-clean' bootstrap check test browser`:
  **exit 0**, image build + dependency installs + compile + migration runner + readiness +
  lint + **2 PHPUnit tests/13 assertions** + **1 Chromium test** passed. Separate fresh
  volumes; `curl --fail http://localhost:8097/health/ready` returned ready.
- First fresh clone found Docker created a root-owned bind-mount parent for the nested
  blob volume. Make now creates `var/catalogue` before starting Docker. The second
  fresh clone above verifies the fix with no pre-existing runtime directories.

Failures corrected during M00: original Composer lacked name/description for strict
validation; added metadata and regenerated lock. PHPUnit inherited APP_ENV=dev from
Compose; Make now explicitly runs it with APP_ENV=test. AssetMapper tried compiling
Tailwind source; excluded studio.input.css. Chromium enforces HSTS on the `app` DNS
name; uses the `studio` network alias. A custom browser build hit a Debian HTTP mirror
hash mismatch, then HTTPS trust failure; replaced with the official pinned Playwright
image. An early browser assertion expected inline-flex on a flex item (computed display
is blockified); corrected the assertion to verify actual component styling.

M00 gates completed and recorded before starting M01. Next step: M01 authentication.
Production images, isolated preview execution, release publishing, registry and consumer
installation remain M02–M08. No claim that those boundaries have been tested yet.

## M01 authentication feature

Branch `feature/m01-authentication` stacks on verified M00. Added PostgreSQL User,
Symfony form login with role hierarchy (admin → editor → reader), account status checks,
stateful CSRF on login/logout/forms, secure-auto/HttpOnly/SameSite=Lax host-only cookies,
login throttling, and hidden/confirmed interactive `styleguide:user:create --admin`.
No registration or default account. A development-only command creates/removes random
browser accounts with credentials in ignored mode-0600 storage; cleanup passed.

Migration `Version20261026222319` was generated with `make:migration`, reviewed and
applied to both development and test PostgreSQL. It creates studio_user plus the table
for the skeleton's pre-existing Doctrine Messenger transport. Unique canonical email
is enforced by both the service and database. Added symfony/rate-limiter **8.1.6**
with Composer/Flex; no existing dependency versions changed.

Actual checks before continuing to catalogue:

- `make check test browser`: **exit 0**; strict Composer/container/YAML/Twig checks;
  **8 PHPUnit tests, 47 assertions**; **2 Chromium tests passed**.
- PHPUnit exercises real form login, missing login CSRF, valid/invalid logout CSRF,
  private access, disabled accounts, persisted interactive admin/password hashing,
  duplicate canonical email and noninteractive command rejection.
- `doctrine:schema:validate`: mapping valid and database in sync.
- Studio CSS recompiled after adding the login page; Chromium verifies styling/action.
- No failed authentication checks remain. Catalogue permissions, revisions and package
  validation are still pending and M01 is not yet declared complete.

User extended scope to M02–M08 on 2026-10-26: continue in order after M01, completing
all milestone gates and a local production build only. No remote deployment or push.

## M01 catalogue feature and completion gate

Branch `feature/m01-catalogue` stacks on verified authentication. Added CatalogueItem,
versioned Draft and read-only Release metadata. Generated/reviewed migration
`Version20261026223512` applies cleanly to dev/test PostgreSQL; schema validation passes.
Names are unique across kinds, drafts use Doctrine optimistic version locking inside
an atomic metadata/source transaction, and releases have a database item/version unique
constraint and defensive immutable snapshots. Sources are JSONB objects outside the
studio template loader; the UI escapes them. Empty JSON objects survive round-trips.

SSR pages implement name/title/description/tag search, kind filtering, sorting and
12-item database pagination; detail/create/edit forms; preserved form input on 412.
The JSON API uses MapRequestPayload/MapQueryString, role checks, header CSRF, quoted
If-Match revisions and structured private/no-store errors. Missing If-Match is 428.
Publishing/preview remain explicitly unavailable at the M01 checkpoint.

`opis/json-schema` **2.6.0** (opis/string 2.1.0, opis/uri 1.1.0) was resolved/locked in
Docker. Symfony has no JSON Schema component; Opis validates the normative package
schema. Semantic validation enforces owned portable paths/types, no PHP/foreign trees,
case/duplicate inventory collisions, source/JSON/depth limits, file integrity, controller
identity, dependency uniqueness and bounded props with defaults/examples. Remote refs,
recursive refs and unsupported schema keywords are rejected. Draft checksums are
regenerated; immutable/import checks verify exact supplied hashes. Bundled alert and
notice-grid drafts validate and load idempotently without replacing existing edits.
No imported source is executed at this milestone. Binary assets are a later profile.

Actual completed checks, run before continuing:

- `make check test browser`: **exit 0**, Composer/container/24 YAML/7 Twig checks;
  **43 PHPUnit tests / 160 assertions** and **5 Chromium tests passed**.
- After the last service changes, `make check test` again passed **43 / 160**.
- `doctrine:schema:validate`: mapping valid and database in sync.
- `composer audit --locked`: no advisories. CSS rebuilt using the locked toolchain.
- `styleguide:fixtures` twice: two valid examples created, then both skipped unchanged.
  PHPUnit separately edits a fixture and proves re-running the command preserves it.
- Browser `restart.mjs prepare` creates/edits a component, then actual
  `docker compose restart app database` + `up -d --wait`, followed by
  `restart.mjs verify`: **PASS**. Source, title, tags, revision 2 and database search
  survive both container restarts. Temporary accounts are removed afterward.
- PHPUnit covers real form/API persistence, reader/anonymous denial, CSRF, stale API
  and HTML saves returning **412** with first data preserved and second input retained,
  duplicate names, bad JSON, missing revisions, pagination, invalid paths/props/defaults,
  checksums, local/remote/recursive schema refs, URL injection and immutable version
  uniqueness. Chromium covers login/logout, actual styling/Stimulus, source-inertness,
  full create/edit/reload/search, reader denial and two-tab conflicts.

Failures corrected: Doctrine's Version mapping requires an explicit integer column
before type inference; generated the migration after fixing it. Symfony 8.1 Choice
requires named `choices:` rather than legacy positional options. API errors must run
before the firewall exception handler (priority 10) to return JSON 401. Twig loops
need an array view of JSONB source objects. A source-isolation assertion initially
matched AssetMapper's own script; it now checks the untrusted script itself. The
external importmap polyfill is disabled to keep studio browser assets local.

M00 and M01 gates are complete. Next: M02 isolated renderer/compiler, then M03–M08
in order with local production verification. No remote deployment, package publication
or branch push. M02–M08 are not yet implemented or claimed complete.

M01 fresh-clone verification: `/tmp/styleguide-m01-clean`, with separate fresh volumes
and loopback port 8098, ran `STUDIO_PORT=8098 make COMPOSE='docker compose -p
styleguide-m01-clean' bootstrap check test browser`: **exit 0**. Both generated
migrations applied, clean Composer/npm installs and CSS compilation succeeded, services
became healthy, **43 PHPUnit tests/160 assertions** and **5 Chromium tests** passed.
Only directly needed example packages and the normative package schema were added to
Git; the staged README, AGENTS edits and unrelated kit/IDE files remain untouched.

## M02 work in progress

Branch `feature/m02-isolated-preview`. Both workers are built from independent lockfiles,
with no studio source/credentials mounted. Twig uses installed 3.30.0 Sandbox API with
strict SecurityPolicy and bounded fresh PHP subprocesses. Fixed Node compiler uses
Tailwind/CLI 4.3.3, daisyUI 5.7.50, esbuild 0.28.2, Stimulus 3.2.2 and Acorn **8.19.0**.
Worker Composer lock: Twig 3.30.0, Symfony Process 8.1.9 plus polyfills/contracts.
Root symfony/lock **8.1.9** added with Flex; advisory locks serialize preview quota checks.

Generated/reviewed/applied `Version20261026225507` for private expiring preview jobs.
Doctrine Messenger orchestration, job polling/document/cancel endpoints, finite dependency
resolution through verified immutable archives, restrictive srcdoc/CSP and strict resize
messages are implemented. Initial real checks passed 59 PHPUnit tests/204 assertions,
then 61/211 after explicit wall-time and CSS-plugin tests. Nine Chromium tests passed,
including actual alert dismissal, a new unsaved responsive utility, parent DOM/storage
and CSS isolation, wrong-source/channel/schema message rejection. Additional dependency
and full-template policy checks are now in final verification; M02 not yet marked done.

Docker inspection/probes actually confirmed: both workers have only the internal workers
network, no published ports, no bind/volume mounts, no APP_SECRET/DATABASE_URL in their
environment, non-root users, read-only root filesystems. Renderer: 256 MiB, 32 PIDs, 1 CPU;
compiler: 512 MiB, 64 PIDs, 1 CPU. Both cannot resolve the database service. Worker network
Internal=true. Parent worker tokens are removed from render/build child environments.

Resolved checks during implementation: render image initially lacked ZIP tooling for
Composer; installs now run in a separate Composer build stage, leaving a narrow runtime.
Consumer inherited a web-server health check; it now checks for the real Messenger process.
Compose health-check PHP variables need doubled dollars. A repeated browser search exposed
a test synchronization race; the test now waits for the filtered URL and one matching item.

M02 additional results: dependency resolver tests use real ZIP archives and PostgreSQL
release records to exercise exact-version conflicts, cycles, missing releases and archive
integrity. The expanded PHPUnit run passed **66 tests/230 assertions**. The new static
sandbox policy pass then caught a real alert regression: Twig inserts a safe boolean
`true` test for `if`; the policy now explicitly permits boolean tests, with a backend
conditional-render regression case. Browser acceptance is being re-run before completion.
A test helper initially collided with PHPUnit's final name() method; renamed it.
Runtime `npm audit` cannot reach the registry from the intentionally internal compiler
network; running the audit in the designated network-enabled tooling container reports
**zero vulnerabilities**. Worker runtime networking was left restricted.

M02 completion gate: final `make check test browser` **exit 0**, with **66 PHPUnit
tests / 230 assertions** and **9 Chromium tests passed** after the conditional policy
fix and atomic queue transaction. This includes real wall-time termination plus recovery,
memory/output failures, all source/compile boundaries, dependency archive/graph checks,
alert rendering/dismissal, disabling scripts, live unsaved responsive CSS, closing script
tags/Unicode assembly, parent DOM/storage/CSS isolation, and message source/channel/bounds.
`doctrine:schema:validate`, strict root/worker Composer validation and root Composer audit
pass; build-worker lock audit in the tooling container reports zero vulnerabilities.
App, workers and Messenger consumer are healthy. User AGENTS.md and staged README hashes
remain identical to the initial audit. Next: verify fresh M02 bootstrap, then M03.

M02 fresh-clone verification (2026-10-27): `/tmp/styleguide-m02-clean`, separate fresh
PostgreSQL/blob volumes and port 8099, ran `STUDIO_PORT=8099 make COMPOSE='docker compose
-p styleguide-m02-clean' bootstrap check test browser`: **exit 0**. Locked root and worker
images built, fresh Composer/npm install + stylesheet compilation and all three migrations
succeeded; app, database, workers and consumer became healthy; **66 PHPUnit tests/230
assertions** and **9 Chromium tests passed**. M02 is complete before beginning M03.

## M03 work in progress

Branch `feature/m03-live-editor`. Added tabbed source/file editors, unsaved indicators,
300 ms debounce, automatic preview, stale/error diagnostics and sequence/cancellation
handling. Added 375/768/1280/custom widths, zoom, root/text/body/heading font controls,
reset/disable scripts/example controls and PostgreSQL per-user/item renderer workspace
presets. `Version20261026232516` was generated/reviewed/applied; schema validation passes.
Preview claim/finish/cancel use atomic conditional database transitions; cancellation
cannot be overwritten by a late completion. Snapshot+queue enqueue remain transactional.
No new dependencies. Compiler now also scans the actual rendered HTML for prop-driven
literal classes. Presets and previews do not mutate the saved draft.

Actual checks so far: `make check test` passes **68 tests / 248 assertions**, including
preset persistence, owner separation, CSRF/bounds and exclusive claims/cancellation.
Chromium passes **14 flows**, including live Twig + Stimulus edits with no source writes,
save/reload, invalid Twig location + retained stale frame, real delayed HTTP responses,
375/768/1280 breakpoints, zoom preserving viewport, root/body/heading typography, saved
presets, keyboard tabs, examples and reset. The browser test uses real worker responses;
it delays a real document response to exercise the ordering race. Final explicit debounce
and invalid-JavaScript checks are pending before marking M03 complete.

Resolved: renderer selects initially used implicit labels containing option text; added
explicit labelled controls. Legacy viewport tests now use the real new width buttons,
since resize handling correctly reapplies the selected viewport. Reset no longer waits
for network cancellation before clearing the frame. Invalid editor JSON blocks stale
source submission rather than discarding the current text.

Follow-up during M06 contract consolidation: extend controller path/identifier validation
to owned nested controllers, centralize URL-like value checks and duplicate JSON-key
rejection for imports. Current fixtures use the validated primary-controller profile.

M03 completion gate (2026-10-27): final `make check test browser` **exit 0**. Composer,
container, 25 YAML and 7 Twig checks pass; **68 PHPUnit tests/248 assertions** and
**15 Chromium tests** pass. Explicit rapid-edit test confirms no request before the
300 ms debounce and one request for three rapid edits; invalid JavaScript reports its
syntax location and retains the same good frame. Responsive widths, zoom, private
settings persistence, source persistence, keyboard tabs, examples and immediate reset
all ran in Chromium against real workers. Schema validation and git diff checks pass.
M03 is complete before starting M04. Source and renderer settings remain separate.


## M04 theme laboratory completion

Branch `feature/m04-theme-laboratory` stacks on M03. Added typed Doctrine Theme drafts
with optimistic revisions and immutable ThemeRelease snapshots, all 20 semantic/content
colours, bounded geometry, per-theme typography, registered system fonts, contrast guidance,
paired isolated previews, source-editor theme presets, canonical JSON/YAML import/export,
CSS metadata/declaration round-trip validation, source and compiled downloads and output ZIPs.
The compiler validates typed theme input using locked Ajv **8.20.0**; imported CSS is never
executed as configuration. System-font-only profile is explicit; no remote/font uploads.
Migration `Version20261026235652` was generated, reviewed and applied to dev/test PostgreSQL.

Actual gates: `make check test` exit 0, **71 PHPUnit tests/285 assertions**; `make browser`
exit 0, **17 Chromium flows**. After stricter CSS string normalization, targeted theme-import
checks pass **2 tests/14 assertions**. Tests cover real persistence/reload, stale saves,
CSRF/permissions, immutable release snapshots/duplicate versions, compiled token/font CSS,
ZIP contents, JSON/YAML/generated CSS round-trips and injection/alias/font rejection.
Chromium changes colour/font/text/radius, compares frames and studio styles, saves/reloads,
exports/reimports exact tokens, publishes and persists a source-editor theme selection.
Generated exported app.css independently compiled with the locked Tailwind CLI (exit 0).
Schema validation, root Composer audit, build-tool npm audit and git diff checks pass.

Corrected failures: IntegerType has no html5 option; apply it only to NumberType. Existing
preset assertions now include nullable themeReleaseId. Concurrent browser/backend checks
exposed genuine worker busy responses; WorkerClient now retries 429/busy within its original
bounded request deadline. Final suites ran successfully in sequence. Unrelated AGENTS/README
hashes and staged README are preserved. Next: M05 real release publication foundation,
then bounded composition authoring; no remote push/publication/deployment.

## M05 release publication foundation

Branch `feature/m05-release-publication` adds actual immutable component/block/layout
publication ahead of composition references. Stable version + expected revision and CSRF
are required. Defaults/examples pass the dedicated sandbox and actual compiler; invalid
source creates no release. Canonical inventories, licence declaration/integration README,
content-addressed staged ZIP installation and a final optimistic revision check precede
the immutable database record. Stored ZIP entries avoid false expansion-ratio rejection.
Actual feature checks: `make check test` exit 0, **72 tests/298 assertions**; targeted real
Chromium publication flow **1 passed**. Archive reader verifies published bytes/checksums;
invalid include, stale revision, duplicate version, role/CSRF and immutable snapshots tested.
M05 remains in progress. Next: bounded composition builder on a new stacked branch.


M04 dependency reference: Ajv draft-2020-12 uses its dedicated Ajv2020 class, verified
against https://ajv.js.org/json-schema.html#draft-2020-12 and the installed 8.20.0 lock.

## M05 composition verification in progress

Branch `feature/m05-composition` adds bounded schema-backed container/reference/text
composition in existing draft sources, exact release props validation and dependency
closure checks, serialized/generated Twig, keyboard selection/tree operations, transient
previews, optimistic saves and explicit duplicate-to-source conversion. Generic source
writes cannot bypass composition ownership. No new dependencies or database migration.
Current `make check test` passes **75 tests/325 assertions**. Final full Chromium suite
is running before declaring M05 complete. Actual rendering caught Twig's constant-string
escaping behavior; text now explicitly uses escape. Canonical object-key ordering keeps
emitted Twig stable after canonical JSON persistence. Other corrected checks: stdClass
must be converted/presented appropriately for studio Twig; renamed duplicate inventories
must be regenerated; test helper must not override PHPUnit's final name() method. Browser
selector now uses the fixture's actual dismissLabel, rather than assuming another label.


M05 completion gate: `make check test browser` **exit 0**, **75 tests/325 assertions**
and **19 Chromium flows**. Real browser flow creates distinct alert instances, reorders by
keyboard, nests/outdents/indents text and containers, duplicates/removes, saves/reloads,
publishes a block, references it from a layout, exercises nested Stimulus dismissal and
converts a duplicate to source. Backend tests render nested verified release archives,
check exact order/props/CSS/literal HTML/Twig escaping, tree limits and invalid tags/classes,
unknown/self/conflicting references, invalid props, CSRF/revisions and ownership protection.
Existing dependency cycle/archive-integrity tests still pass. Schema and git diff checks
pass; AGENTS/README hashes remain unchanged. M05 is complete before M06. Next: shared
contract validation for studio/consumer, safe import/export, registry and scoped tokens.


## M06 shared contracts feature

Branch `feature/m06-package-contracts` extracts validation into the local Composer library
`packages/catalogue-contracts` version 0.1.0, with a locked root path-repository dependency.
Official Composer path-repository behavior checked at https://getcomposer.org/doc/05-repositories.md#path.
No external package versions changed. Studio and future consumer use the same strict JSON,
portable paths, bounded ZIP reader, inventory/props/theme validation, composition serializer
and exact dependency graph. Normative schema copies are checked for equality. Runtime
render/build isolation is unchanged. Root JSON bodies reject duplicate keys before DTO
mapping; a structural scan bounds allocations. All nested Stimulus controller files must
be declared with their actual path-derived identifier. Unsafe URL schemes fail regardless
of prop name. Theme packages validate all generated source CSS against typed tokens.

Actual feature gates: final `make check test` exit 0, **83 tests/363 assertions**. Full
browser regression after extraction passes **19 flows**. Then the rebuilt compiler's
literal package-CSS inclusion was verified by real compiler output in the final PHPUnit
run. Adversarial real ZIP tests include exact duplicate central-directory entries,
traversal/absolute/drive/backslash/dot/reserved paths, case collisions, symlinks and expansion
bombs. Tests cover escaped duplicate JSON keys and allocation limits, root HTTP ambiguity,
nested controller compilation, arbitrary-key URL schemes and the real Ember theme fixture.
Composer root/library validation and root audit pass; git diff checks pass.

Corrected an autowiring failure from old implicit namespace references during extraction,
and a malformed JSON test string. Worker image rebuilt/recreated after CSS inclusion fix.
M06 is still in progress. Next: private registry/scoped tokens, safe staged transfer,
resolved/static/theme exports and clean-studio round-trip checks before M07.

## M06 private registry feature

Branch `feature/m06-private-registry` adds hashed scoped/expiring/revocable read tokens,
administrator SSR token management and real create/revoke console commands. A separate
stateless firewall grants a registry-only ROLE_READER identity and never creates a studio
session. Owner status, expiry, revocation and exact/namespace/all scopes precede every
metadata/manifest/archive ETag check. Archive bytes are integrity-verified and private.
Generated/reviewed/applied `Version20261027004207`; schema mapping/database validate.
Token pages and registry requests explicitly disable development profiler collection.

Actual gates: `make check test` exit 0, **86 tests/399 assertions**. Targeted Chromium
admin issue/download-scope/reload/revoke flow **1 passed**. Real commands issue hashed
tokens and revoke by ID. HTTP tests verify bytes/hash, private caching, 304 only after
authorization, wrong scope, expired/disabled/revoked tokens and no Set-Cookie/session
privilege escalation. Production container lint (`--env=prod --no-debug`) passes with
optional profiler service absent; this is configuration validation, not a production
image/deployment claim. Git diff checks pass. Temporary browser accounts/tokens removed.

Corrected checks: private helper collided with AbstractController::json; renamed it.
BinaryFileResponse defaults to public and now explicitly uses public:false. HTML invalid
CSRF is handled as 403 before the authentication redirect. Nullable Profiler autowiring
initially injected null; explicit optional service injection now prevents debug-token
headers on private token/registry responses, verified in the real development browser.
M06 still pending package transfer and clean-studio round-trip before M07.


## M06 package transfer completion

Branch `feature/m06-package-transfer` adds safe ZIP/manifest/envelope/YAML ingestion,
reviewed validate/dry-run plans, transactional apply with staged immutable blobs, complete
resolved collections, source/JSON/YAML/static/theme exports, scoped owner-only expiring
artifacts and operator CLI import/export commands. New items receive imported drafts;
existing modified drafts stay unchanged. Unlicensed generated themes say UNLICENSED;
provided licence files remain authoritative. Source CSS and compiled snapshots are labelled.
Theme packages use the same token/CSS/marker validator, have real immutable archives, and
can be explicitly published to the private registry. Source and theme namespaces cannot
silently collide. Template/JS validation uses the actual isolated workers, not mocks.

Actual completion gates: `make assets check test browser` **exit 0**, **93 PHPUnit tests /
469 assertions** and **21 Chromium flows**. Tests cover real staged imports, unchanged
catalogue after invalid builds/stale plans, identical import idempotence and modified-draft
preservation, private owner exports and archive hashes, JSON/YAML empty object/array fidelity,
missing closure/checksum rejection, theme packaging, operator commands and output collision
preservation. Chromium opens a downloaded static index by file://, checks actual styles and
Stimulus dismissal and observes **zero HTTP requests**, then reviews/applies a real import.

Clean-store gate: `roundtrip.mjs prepare` on the main stack creates/publishes a real alert,
block, layout and theme and exports a complete collection. Built a separate Compose project
`styleguide-m06-clean` with **fresh PostgreSQL/blob volumes**, loopback port 8101, applied
all migrations and ran `roundtrip.mjs verify`: **exit 0**. Four new releases import;
normalized manifests/sources and typed tokens match; source/target PNG hashes match exactly;
nested alerts preserve order/props, font/theme styling and functional Stimulus dismissal.
Temporary accounts removed and clean project stopped; its test volumes are retained.
This is fresh development storage, not a production deployment check. M08 remains required.

Corrected checks: Symfony YAML dumps empty arrays as maps by default; explicit empty-array
sequence dumping plus object-map parsing now preserves both. YAML uses the installed
Symfony 8.1 parser's alias rejection/depth controls rather than a regex that rejected literal
punctuation in sources. Pixel comparison first caught the development toolbar overlay
(account/timing text); the harness hides that overlay and the complete test was repeated
on fresh storage. Static alert uses literal flex, not a presumed grid; browser assertion fixed.
CSS marker metadata is also compared with authoritative theme JSON. Final schema, root
Composer audit, build npm audit and git diff checks pass; AGENTS/README hashes remain intact.
M06 is complete before M07. Next: actual Symfony installer bundle and fresh consumer gates,
then immutable local production images, cleanup and backup/restore acceptance in M08.

Final M06 guard: concurrent theme-package uniqueness violations map to HTTP 409, matching
normal immutable publication. Targeted real theme package test passes **1 test/6 assertions**
and container lint passes after that guard. No remote publication, deployment or branch push.


## M07 consumer installer completion

Branch `feature/m07-consumer-installer` supplies a real local Composer bundle, version
0.1.0, with registry:add, require, install, theme:install, update, remove and doctor.
The separate fresh Symfony 8.1 consumer uses committed Composer/npm locks and mirrored
local path packages; nothing is published to Packagist. Consumer semver uses actual
Composer semver **3.5.2**, now explicit in root development requirements. Runtime versions
match the recorded M00 toolchain. Source parsing adds fixed **Lightning CSS 1.32.0**
(already Tailwind's transitive dependency) alongside Acorn **8.19.0**. Official strict
transform API checked at https://lightningcss.dev/docs.html; npm registry returned the
exact 1.32.0 version/integrity now recorded in worker/consumer locks. The pure shared
parser validates every JS module's syntax/finite imports and literal CSS, never executes
source and never loads plugins. Generated typed theme entries follow the theme validator.

Installation verifies private credentials, same-origin redirects, bounded downloads,
immutable hashes, portable paths, compatibility and the exact shared dependency graph.
It stages complete candidates and a controller-source review, compiles CSS with an env
without studio/registry secrets, then serializes journalled atomic writes with the lockfile
last. Metadata is relocated below .styleguide/packages; declared renderable paths remain
unchanged. Owned hashes and managed CSS/Stimulus edits protect local changes; unowned
identical files still conflict. No force/adoption, automatic npm installs or imported hooks.
An interrupted transaction restores original hashes; externally changed files retain the
journal for manual review. Production AssetMapper/cache compilation is an explicit release
step after source/CSS installation. Consumer theme data is a scalar Twig global only.

Actual completion gates, before M08:

- `make check test`: exit 0, **98 tests / 494 assertions**; configuration, Twig/YAML,
  schema mapping/database and strict root Composer validation pass.
- `make browser`: exit 0, **21 Chromium flows**. Temporary accounts/tokens removed.
- `make consumer`: final fresh run exit 0. Locked PHP/npm installs, concrete dry-run,
  actual private-registry alert + composed block/layout + theme installation, unchanged
  reinstall with **zero changed files**, unowned-identical and edited-file conflict with
  every destination hash preserved, forbidden local-file JS rejection, dependency-pinned
  update/removal rejection, real **SIGKILL after the first journalled mutation**, fresh
  CLI recovery restoring exact original hashes, and doctor pass.
- That same gate compiles production AssetMapper resources and renders nested Twig,
  responsive md:grid-cols-3, typed Georgia theme and functional Stimulus in Chromium.
  Every request uses the independent consumer origin; no studio/CDN runtime requests.
  It then removes a dependent, updates the leaf to 1.1.0, deletes only owned files,
  installs the offline resolved collection, verifies idempotence and repeats the
  production browser check after consumer restart. Both browser checks pass.
- Bundle/consumer/root strict Composer validation pass. Root Composer audit and worker
  npm audit report **zero advisories**. Production container lint and git diff checks
  pass. AGENTS.md/README.md original hashes still match and the staged user README remains.

Failures corrected: initial fresh fixture lacked required title/default URI and used an
obsolete importmap config location. Real production compilation found theme app.css source
being scanned as a browser stylesheet; the bundle now excludes package CSS compiler sources.
Shared parsing initially treated generated theme entries as user CSS; those entries already
undergo exact token-derived validation and now follow the same separate typed-theme path
as studio rendering. All corrected gates were repeated on the fresh consumer above.

M07 is complete. Next: M08 immutable production app/workers, limits/cleanup, acceptance
fixtures, local production end-to-end and fresh database/blob restore with consumer install.
No remote deployment, package publication or branch push is authorized or performed.
