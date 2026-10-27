# Implementation status

Last updated: 2026-10-27. Scope: M00–M08 complete, including a local production build; no remote deployment or push.

Earlier sections retain historical checkpoints. The final handoff below supersedes their in-progress notes.

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
| M08 Production/recovery | Complete | Fresh checkout: 108 tests/544 assertions, 25 Chromium flows and consumer; production restart/backup/fresh restore/install and HTTPS verified |

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


## M08 production runtime feature

Branch `feature/m08-production-runtime` adds locked immutable application/worker images,
production AssetMapper/CSS builds, runtime file secrets, separate private DB/worker networks,
non-root/read-only/capability-free services and explicit memory/CPU/PID/tmpfs limits. No
production dev dependencies or browser-account command. Persistent sessions/blobs are owned
volumes. Node compiler temp cleanup also handles killed children; shutdown is bounded. PHP
CLI **pcntl** is now built for proper Messenger/supervisor signal handling. Production log
processing retains diagnostic IDs/categories, drops private messages/context/arguments and
turns off Doctrine SQL/profiling. Health remains DB/worker-aware. Local TLS override uses an
internal Caddy CA and an exact proxy IP; it is not public certificate/deployment evidence.

Generated/reviewed/applied migration `Version20261027030742` adds nullable start time and
attempt tokens. A durable Doctrine message redelivers after 60 seconds; a 45-second lease
permits recovery, and conditional completion plus attempt-specific files reject old results.
Legacy successful preview files remain readable. Preview retained-job/storage, export and
immutable-archive quotas are serialized; scheduled cleanup defaults to safe dry-run, applies
once/minute, preserves every referenced release and gives orphan archives a 24-hour grace.
Added archive verifier, consistent stop-writes backup and fresh-target restore scripts,
operator account disable command and detailed docs/styleguide/PRODUCTION.md.

Actual feature checks before further M08 work:

- Final `make check test`: exit 0, **101 tests / 516 assertions**, including stale lease
  exclusion, cleanup dry-run/apply/reference retention and private-log redaction.
- Clean production database/blob/session volumes: `prod-init prod-build prod-migrate`
  succeeds, seven migrations applied. Image build consumes locks and compiles studio assets
  without production secrets. `prod-up prod-check` succeeds after fixes below; schema and
  production container lint pass. Production CLI confirms pcntl, APP_DEBUG=0, no profiler/
  debug/PHPUnit packages and no plaintext APP_SECRET/DATABASE_URL environment values.
- Full studio Chromium suite against production app: **21 passed**. Real edit/preview/
  composition/theme/publish/transfer and source isolation run on production assets.
- Actual Docker inspection passes non-root identities, read-only filesystems, dropped
  capabilities, limits, private networks/ports and no application/source mount or secret
  in either worker. Initial tested app image config ID b1054dc72bb8177dbf72ea6b70ba7acd98d38796f24f1293bda7a07833e6bc10;
  worker IDs are recorded in ignored production-test/containers.json. These are local
  image IDs, not invented registry digests; final acceptance rebuild still follows.
- Local TLS/proxy Chromium check passes Secure/HttpOnly/SameSite cookie assertions,
  HSTS and rejection of direct forged X-Forwarded-Proto. Current services remain local.

Corrected real checks: dropping all capabilities exposed the base binary's file capability
and prevented exec; production removes it and listens above 1024. Caddy's multiline syntax
is validated during build. CLI maintenance/proxy override inherited web health checks.
Caddy data uses bounded writable tmpfs; TLS data has its own volume. The initial local TLS
subnet overlapped an existing unrelated network; that network was preserved and the override
uses a separate configurable subnet with explicit app/proxy IPs. Cleanup test metadata is
now removed after assertions; leaving it in the test DB correctly caused warnings in a
subsequent real composition query. The final 101-test rerun above is warning-free.

M08 remains in progress. Next: finish sample/renderer acceptance controls, run fresh final
production end-to-end including interrupted consumer/redelivery, consistent backup/restore
into new volumes and repeat export/consumer installation; then record the acceptance matrix.
No remote deployment, push or public package publication.


## M08 showcase and authoring controls feature

Branch `feature/m08-showcase-and-controls` adds twelve validated local sample packages:
button literal variants, MIT alert, native accordion, reduced-motion-aware progress,
card, keyboard tabs, navigation, stats, labelled fields, native dialog, pricing block and
dashboard layout. `styleguide:showcase --publish` validates real default/example builds,
then reruns idempotently without changing immutable releases or edited drafts. Source
and composition ownership, exact releases and licences remain authoritative.

Added bounded schema-driven transient prop fields, colour scheme/surface/device frame,
persisted older-preset defaults, theme comparison reset/script toggles, bounded theme
choices retaining the selected older release and typed-theme/sample collision avoidance.
Catalogue metadata reflects actual revision/release/dependencies. Default-render thumbnail
caches are optional, bounded at 256 KiB each/50 MiB total and shown in lazy opaque
script-disabled iframes. Source duplication remaps controller/template ownership and
preserves original records. Installation commands include the required registry alias;
copy either writes the clipboard or selects the actual command for manual copying.

Actual gates: final `make assets check test` exit 0, **103 tests / 528 assertions**;
final `make browser` exit 0, **25 Chromium flows**. The real showcase publish command
creates all twelve releases; its next run reports twelve verified releases preserved.
Schema validation and git diff checks pass. Tests exercise accordion Enter, tab arrow/focus,
native dialog Escape/focus return, progress actual value updates, interval cleanup after
DOM removal, reduced-motion pause/manual advance, prop form/theme/surface persistence,
unchanged CSS viewport under device framing, duplicate controller behaviour, static cached
thumbnails and theme reset/script controls. Original AGENTS/README hashes still match.

Corrected gate: the longer live-edit browser suite correctly hit the initial 60-job request
limit. Retention now expires oldest terminal history as new jobs arrive while preserving
pending jobs and the latest 60, instead of blocking sustained authoring. A new real preview
HTTP/worker test verifies continued editing and pending-job protection. The full 25-flow
rerun above is clean. Remaining M08 work: final production rebuild, real interrupted queue
redelivery, consistent backup/fresh restore and export/consumer reinstallation, CI/local
verification script and final acceptance evidence. M08 is not yet declared complete.


## M08 recovery verification and final audit in progress

Current branch `feature/m08-recovery-verification` adds checked-in production browser,
container-boundary, secret-log, actual graceful/SIGKILL queue, backup/restore and consumer
verification drivers. The CI workflow uses the actual resolved checkout v4 SHA
11d5960a326750d5838078e36cf38b85af677262 and the same local commands. GitHub-hosted CI
has not been run; no push is authorized. Acceptance mapping: docs/styleguide/VERIFICATION.md.

Completed actual runs before the final clean checkout:

- Fresh `styleguide-production-final` PostgreSQL/blob/session volumes, seven migrations,
  immutable images and prod-check all pass. Final observed runtime remains FrankenPHP
  1.13.4 / PHP 8.5.12 / Caddy 2.11.7 / Debian 13.7 / Symfony 8.1.9 / Twig 3.30.0;
  pcntl is present. The complete studio suite against production passes **25 flows**.
- Docker inspection and local HTTPS cookie/forwarding tests pass. Core services have no
  source mounts or exposed DB/worker ports; workers receive only their own scoped secret.
- Real blocked-build SIGTERM: consumer exits **0**, job reaches explicit retryable failure
  in about **12 seconds**. Real SIGKILL: durable message redelivery succeeds after about
  **72 seconds**, with a different lease/output attempt. The stale compiler directory is
  actually removed by the parent. No test-only server success response is used.
- Consistent backup then actual database/app/consumer restart passes normalized source,
  token and nested Stimulus checks. Fresh project `styleguide-restore-20261027043705`
  restores PostgreSQL and blob volumes, archive/schema checks pass, saved drafts/tokens
  match and a new resolved collection is **byte-identical** to the original.
- Fresh Symfony consumer after restore passes real registry/production browser, modified
  and unowned collisions, forbidden JS, install SIGKILL recovery, update/remove and offline
  collection/idempotence checks. Six test-account disable operations succeed (three roles
  in each production project); restore volumes/backups are retained. The restore project
  is stopped, including its explicit consumer profile; no unrelated storage is removed.
- An additional complete consumer/recovery run with operational logging passes **exit 0**,
  including fresh project `styleguide-restore-20261027050056`. Actual job timing/hash/wait/
  category records are emitted at info level and actual Docker logs contain none of the
  runtime secrets, test passwords or read token. Source and exception context stays redacted.
- Current final PHP gate after CSS policy hardening: **108 tests / 540 assertions**, pass.
  This includes actual foreign-origin HTTP redirect refusal, private credential permissions,
  escaped CSS keywords in package validation and actual worker rejection/recovery.

Corrected observed checks: pausing a worker makes Docker health stale; the driver now waits
for real health recovery before dependent startup. The first test layout nested two separate
three-column grids, causing pointer overlap at 768 px; the fixture now gives its block the
full layout width and asserts the responsive block itself. The real layout/alert workflow
then passes without forced clicks. A fixed Stimulus prerequisite download transiently failed
TLS certificate validation; a normal verified HTTPS retry succeeds, and the driver now retries
up to three times without disabling verification. Resume modes report skipped stages explicitly.

Final policy audit actually reproduced an accepted escaped CSS directive. PHP and Node now
decode CSS escapes before applying the same forbidden-directive profile; imported JS remains
parse-only. Production job logging was also verified to emit diagnostics rather than merely
buffering them. Composer image builds cache only public locked dependency downloads with
BuildKit; no secret enters that cache. Latest policy production image/consumer gate and the
final fresh Git checkout are still running/pending; M08 is not yet declared complete here.

## Fresh-checkout fixture regression correction

The final fresh-clone run at commit 7a7c04a bootstrapped and passed lint, but its empty
PostgreSQL test database exposed a real error: the legacy notice-grid fixture referenced
awui/alert@1.0.0 before an immutable release existed. Earlier populated test data had hidden
that dependency, so this is not recorded as a passing fresh run. Branch
`feature/m08-clean-bootstrap-fix` installs the validated bundled alert snapshot through the
same safe import pipeline before generating the notice-grid draft. It never publishes an
edited user draft. New composition source is materialized canonically; existing drafts and
releases remain untouched. The existing persistence test now checks the dependency archive
and hash plus complete source preservation. The corrected clean-checkout gate follows.

## Composition save/preview race correction

The clean-checkout browser run exposed a timing-dependent stale revision response after
Save succeeded. Branch `feature/m08-composition-save-race` invalidates/cancels obsolete
preview work while a save is in flight and schedules a current preview with the resulting
revision afterward. Newer unsaved edits remain in the client. The real browser test now
holds a preview POST until after PUT commits, observes the actual old-request 412 and
requires a fresh current preview. Five focused composition/showcase Chromium tests pass.
The showcase helper now waits for actual render completion before interaction instead of
assuming a preview completes within Playwright's default five seconds. Final clean tests
are rerun with an explicitly recreated task-owned test database, followed by browser and
consumer gates. Main development/production/user data are not reset.


## Final handoff — M00–M08 complete

All requested milestone and A01–A38 acceptance gates have executed evidence in this file
and docs/styleguide/VERIFICATION.md. No required implementation/check remains pending.
Final source is on `feature/m08-composition-save-race`, with **17 local feature branches**
stacked from main c5d2497. Existing AGENTS.md and staged README.md are preserved byte-for-byte;
their final hashes still match the arrival hashes recorded above. Unrelated untracked files
remain untouched. No branch push, remote deployment or Packagist publication occurred.

Final completed checks:

- Fresh clone `/tmp/styleguide-m08-clean-050940`: locked Docker build/dependency installation,
  AssetMapper/studio CSS, migrations and bootstrap pass. After fixing the exposed legacy
  fixture dependency and composition race, fetched committed source e3655a2 into that clean
  checkout, explicitly dropped/recreated **only its task-owned styleguide_test database**,
  then `STUDIO_PORT=8115 CONSUMER_PORT=8116 make COMPOSE='docker compose -p styleguide-m08-clean'
  check test browser consumer`: **exit 0**, **108 PHPUnit tests / 544 assertions**, **25 Chromium
  flows**, fresh Symfony consumer production compilation/browser, immutable registry and
  offline install, changed=0 reinstall, conflicts, real install SIGKILL recovery and protected
  update/remove all pass. Its accounts are removed and project stopped with volumes retained.
- Latest complete security-policy production consumer/recovery gate:
  `PRODUCTION_FROM=consumer make prod-test`: **exit 0**. It reuses the recorded full 25-flow
  production/browser/TLS/boundary stage explicitly, then repeats actual authoring/export,
  fresh consumer install (including escaped CSS/forbidden JS rejection), graceful stop,
  SIGKILL redelivery, stale compiler directory cleanup, logged metrics/secret-redaction,
  consistent backup, restart, fresh database/blob restore and repeated export/install.
- That run's snapshot is `var/backups/production-20261027T050945Z`; restored target is
  `styleguide-restore-20261027051029` using `var/restore-20261027051029/compose.env`.
  Referenced archives/schema validate, saved drafts/tokens match and re-exported collection
  bytes match exactly. The restored independent consumer renders actual Twig/Stimulus/theme,
  responsive CSS and no studio/CDN runtime requests. Restored project is stopped; volumes
  and private snapshots remain available. All temporary production roles are disabled.
- After the final fixture/JS race corrections, rebuilt the handoff image and ran the real
  production fixture command: both drafts plus a validated immutable alert dependency are
  created correctly. Latest focused production browser suite: **5 passed**, including an
  intentionally delayed POST arriving after Save's PUT and returning an ignored 412.
  `prod-check` passes schema/container/cleanup/archive checks; **48 referenced releases**
  verify successfully. Production SQL confirms **zero enabled test/default accounts**.
- Final local HTTPS uses the actual Caddy internal root certificate with curl --cacert:
  /health/ready returns ready and /login sets Secure/HttpOnly/SameSite=Lax. Exact-proxy and
  forged-forwarded-header browser checks passed earlier. Reapplied the complete local TLS
  override after standalone HTTP browser tools so app/proxy retain their shared network;
  all seven production services are healthy at handoff. This is local/internal-CA evidence,
  not a claim of public certificate issuance or remote deployment.
- Final Composer audit: **no advisories**. Fixed build toolchain npm audit: **0 vulnerabilities**.
  Git diff checks pass. Remote GitHub-hosted CI was not run; its checked-in pinned workflow
  invokes these same Docker/local gates without publishing anything.

Final local image tag: `local-20261027053217-handoff` (built from committed source e3655a2;
this final status-only commit does not change runtime code). Actual local image IDs:

| Image | Local ID (not a registry digest) |
| --- | --- |
| app / preview-consumer / maintenance | sha256:2c5bfe42d68b3ed6cfb6410ec9697e9926e3045c3cbed63f5e3e7f4669ea92a4 |
| build-worker | sha256:ddd7ae37edd30220580638ea43d54e20e11a50720244780fcc672f42b83e1a2a |
| render-worker | sha256:14d39a8fd08bce645182474310e95cfa384cda28b8db87c932359267589f1007 |
| local TLS proxy | sha256:c2f1eca12a3a38066f60ff92d4a1da4d77a0a8e486c08a6e121f45eccb055085 |

Local URLs: development http://localhost:8095; production http://localhost:8096;
local HTTPS https://localhost:8443 (internal CA); independent consumer http://localhost:8110.
Production secrets/config are in ignored private var/production-final and var/production;
there is no enabled default account. Exact operator/admin setup, release, shutdown and
backup/restore commands are in docs/styleguide/PRODUCTION.md. Source/profile limits remain
intentional: system fonts and bounded text-only packages, Chromium verification, private
local catalogue. Public TLS, remote CI, remote hosting and package publication were not
requested or claimed. The browser checks are not a full accessibility certification.

Exact next step: create your own administrator with the documented hidden-password command
and use/review the local production build. Review real hostnames/proxy/secrets/capacity and
run your own authorized release procedure before any future remote deployment.


## Font library and website-inspired presets — completed 2026-10-27

Implemented `codex-font-library-and-website-themes.md` on the existing application,
branch `feature/m09-theming-and-fonts`, after inspecting the active font registry,
typed themes, isolated renderer/build workers, package contracts, transfer/import/export
and installer. No second theme system, frontend framework or database reset was added.
This extension supersedes the earlier system-font/text-only limitation; the earlier
M00–M08 handoff and production evidence remain preserved above.

Completed gates and actual imports:

- All **42 requested font families** are actually downloaded and registered in the
  existing persistent blob volume: **35 variable / 7 static**, **208 WOFF2 subset faces**,
  **104 style/weight groups**, **6,713,012 font bytes**, **42 licence references / 40 unique
  licence files (192,499 bytes)**. Font registry/history contains **374 files / 7,824,256
  bytes**. Every group passes actual cmap plus Unicode-range coverage of 137 required
  English/French characters, real axis/weight/style metadata, source integrity and hash
  checks. All 208 faces also decode in the real opaque-origin browser specimen.
- Fontsource **5.3.0** is pinned for 39 families; **5.3.1** for Source Sans 3, Source Serif 4
  and Source Code Pro. Google Fonts family/licence authority revision is
  `e7db6ca603fd4862010383ff17ffd6cc4ffac1c0`. There are 40 OFL families, Ubuntu UFL and
  Roboto Slab Apache-2.0. Exact per-family results are in resources/library/font-imports.csv.
  Pin/dependency manifests and lockfiles were committed independently as **78a21b5**;
  the user's staged README remains staged and unrelated edits remain untouched.
- **18 website reference families / 25 observed modes** imported into existing managed
  Theme drafts and immutable releases, latest **1.2.0**. All canonical values have explicit
  observed/adapted/derived provenance, evidence URL/time/hash, source surface/fonts and
  alternative-font attribution. No inverted/fictional mode was introduced. Detailed
  successful preset rows and provenance counts are in resources/library/theme-imports.csv
  and docs/styleguide/VERIFICATION.md. Earlier managed revisions and user copies survive.
- **Linear pending:** static.linear.app robots.txt disallows the required stylesheets;
  unstyled observations were rejected. **Twitch pending:** TLS certificate-chain validation
  fails for the main and alternative official developer source; verification was not
  disabled. Reddit uses its clearly labelled official corporate marketing surface because
  product access is robots-blocked. Shopify uses current App Home documentation UI after
  historic Polaris URLs moved. Spotify uses observed light Encore documentation rather
  than claiming music-player colours. Failed later refreshes retain last-good snapshots
  with their original dates and separate lastAttempt records.
- Search/filter, English/French editable specimens, supported weights/styles, source and
  licence details, body/heading/code theme choices and live previews are integrated.
  Menus load no font bytes; explicit specimens transfer only the requested real style/
  weight's subsets. Cleared-cache first catalogue load measures **25 resource requests /
  89,702 resource bytes + 18,186 document bytes**, **zero font requests**; Inter italic472
  embeds 143,732 selected raw bytes and really decodes. Studio typography stays independent.
  Source and composition editors save the same existing account-owned renderer presets.
  The final composition regression proves all three font/theme choices and viewport survive
  reload without changing a draft. Picking a theme restores all three font roles to theme
  inheritance. Opaque-origin iframe/CSP restrictions remain intact.
- Gallery swatches, labelled source/mode/fonts, Apply and independent Duplicate/Edit work.
  The existing shared showcase is complete and all **25 presets / 50 mobile+desktop
  screenshots** were rendered, interacted with and visually reviewed. Lowest actual normal
  text 5.51:1; hover/focus 4.67:1; selected 9.35:1; focus outline 4.94:1 (>=2px); field border
  9.35:1; error border 4.62:1. Disabled contrast is recorded separately. This is not a full
  WCAG certification. Culori parses/composites/clips source colours into canonical hex;
  per-value provenance retains source colours and explains readability adaptations.
- Fresh real consumer fixture **var/font-consumer-20261027T131001Z-12365** verifies a
  composed layout with Inter472italic, Lora631normal and JetBrains Mono400normal through
  collection ZIP, importable theme bundle and static export. Raw bytes, licences, canonical
  metadata, inventories and relative URLs survive real install/reimport; reinstall is
  **changed=0**. Production AssetMapper compilation, real loaded FontFaces, Stimulus
  counter/native modal, mobile/desktop and zero external/studio runtime requests pass,
  then pass again after restart. Internal-only consumer service was stopped, fixture
  retained; original consumer at 8110 was preserved. App/preview-consumer images were
  rebuilt/recreated without removing volumes; all fonts reverify afterward.

Main changed areas: src/Font, font/collection commands and controller; existing theme/
catalogue/composition controllers, forms and templates; Stimulus font/specimen/gallery/
preview/theme/composition controllers; RendererSettings/Presets/DocumentAssembler;
ThemeWriter/Sample and ThemePackager/TransferImporter/TransferExporter; shared contracts,
schemas, binary source-byte representation and installer/AssetMapper integration; Node
worker CSS generation; declarative resources/library manifests/locks/evidence/results;
Dockerized tools/library acquisition; compose.yaml/Makefile maintenance and isolated
consumer fixtures; real-font integration/browser tests; DEVELOPMENT/ARCHITECTURE/CONTRACTS/
SECURITY/VERIFICATION documentation. Existing text-only package hashes remain compatible.
No new migration was required; optional typed JSON contract fields extend the same model.

Actual versions: FrankenPHP **1.13.4**, PHP **8.5.12**, Caddy **2.11.7**, Symfony framework
and AssetMapper **8.1.9**, Twig **3.30.0**, StimulusBundle **3.6.0**, browser Stimulus
**3.2.2**, Node **24.22.1**, Tailwind **4.3.3**, daisyUI **5.7.50**, Playwright **1.64.1**.
New pinned maintenance dependencies: fontkit **2.0.4**, tar-stream **3.2.2**, Culori
**4.0.2**, ipaddr.js **2.5.0**; scripts disabled during installation.

Actual verification (commands and per-source evidence also in VERIFICATION.md):

- `docker compose exec -T -e APP_ENV=test app php bin/phpunit`: **112 tests / 589 assertions**,
  exit 0, including real package/font round-trips, rejected corrupted replacement preserving
  the valid registry, static-font queued payloads and clean-store read-only prepare/apply.
- `docker compose run --rm --no-deps browser npx playwright test --reporter=list`: complete
  **28-flow** suite passed after image recreation. After the final composition controls,
  `... test tests/Browser/fonts.spec.js tests/Browser/composition.spec.js --reporter=list`:
  **5 passed**, including original composition save-race/keyboard workflow and the new
  font/theme preservation case. Final cache-cleared catalogue measurement: **1 passed**.
  The current suite has 29 flows; no unexecuted full 29-flow result is claimed.
- `browser node tests/FontConsumer/fontfaces.mjs`: **42 families / 104 groups / 208 decoded
  faces**, pass. `browser node tests/FontConsumer/showcases.mjs`: all **25** pass.
  `bash tests/FontConsumer/run.sh`: fresh compile/install/offline browser/restart, **exit 0**.
- `styleguide:fonts:sync --collection=popular` repeat: **42 unchanged**; dry-run all42 valid.
  `styleguide:fonts:verify`: 42/208 exact bytes, errors=[]. Theme collect/dry-run all25
  unchanged plus two explicit pending-source reasons. Import preparation makes no font
  storage writes. Managed changed inputs cannot overwrite an existing immutable version.
- Compose config, Composer validate --strict, container/Twig/YAML lint, identical schema
  copies and git diff checks pass. Maintenance npm audit: **0 vulnerabilities**. Read-only
  final baseline hashes: all original 13 themes / 268 items / 268 drafts unchanged.
  Temporary browser credentials were removed. The final standalone consumer repeat began
  with no account file and successfully provisioned/cleaned its own temporary credentials;
  its exit trap stops its own service on failure while retaining artifacts. Restricted
  worker networks are unchanged.

Failed checks corrected: Latin-ext prefix matching initially selected the shorter Latin
prefix; metadata ingestionRevision2 fixes range identification while retaining valid bytes
and immutable history. Empty static axes became arrays in JSONB worker payloads; normalized
objects now pass real render/build. AssetMapper initially excluded WOFF2; its exclusions
now retain fonts and production consumer paths resolve. Bare theme ZIPs now have an
importable manifest; read-only import preparation no longer writes font assets. Fresh
Stimulus CDN acquisition failed TLS, so the fixture verifies/reuses exact cached3.2.2
bytes. Concurrent showcase/resource-limit tests caused temporary worker_unavailable;
sequential final captures pass. Temporary invalid test environment/template paths were
corrected and passing commands above rerun. The final extra baseline query initially used
id for draft; corrected to item_id, with no data mutations, and all hashes match.

Remaining limits: verified English/French Latin coverage only; other scripts/axes need the
same manifest/validation path. Public source evidence can change or become inaccessible;
Linear/Twitch remain pending with actionable reasons. Alternative fonts, derived semantic
values and unsupported gradients/shadows are explicit. Selected-family exports preserve
all real editable faces but exclude unrelated families. Chromium/state/palette checks do
not certify all accessibility or browsers. ZIP is required for envelopes exceeding the
shared bounded JSON/YAML byte profile. A new production disaster-recovery run and remote
CI were not executed for this extension; older M08 evidence is not relabelled as new.
No branch push, external deployment or publication occurred.

Exact next step: review /fonts and /themes at http://localhost:8095 with an existing editor/
admin account; use Duplicate/Edit for personal website palettes. Reuse `make fonts-sync`,
`make themes-collect` and `make font-consumer` for future reviewed updates; retry pending
references only when their access/TLS conditions change. No required implementation gate
for the available font/theme collection remains unfinished.


## Local UI location follow-up — 2026-10-27

The running local production at 8096 was restarted from the earlier immutable M08 image;
read-only inspection confirms it has neither FontController nor the website source manifest.
The updated development app at 8095 had been removed/stopped while its four data volumes
remained. Started `docker compose up -d --wait app database render-worker build-worker
preview-consumer` with those existing volumes. All five services become healthy;
`styleguide:fonts:verify` confirms 42 families / 208 faces / 6,713,012 bytes/errors=[] and the
repository confirms 25 managed website presets plus an independent editable copy. `/fonts`
and `/themes` resolve at 8095 and correctly redirect anonymous visitors to `/login`.
The user's account exists only in production, so development login creation must use
plain `docker compose exec app php bin/console styleguide:user:create --admin`. No user
credentials were copied or changed; no production image/data was updated. DEVELOPMENT.md
now explicitly distinguishes the two instances, source/image contents and account scopes.

## M10 — Personal application appearance — 2026-10-27

Implemented `codex-apply-themes-to-application.md` on
`feature/m10-application-appearance`, adapting the existing application. Application-wide
Apply theme now saves the current user's explicit family and immutable revision
snapshots. Header moon/sun, system mode, requested/effective mode explanations and a
fixed legible Restore default are integrated. Runtime token CSS works for newly created
themes without adding names to compiled Tailwind/daisyUI or modifying AssetMapper.

The existing theme editor provides paired/single creation, family rename, variant
selection preserving unsaved inputs, duplicate complete families, real opposite-mode
starting palettes, Save, Save & Apply, Try, Revert and retry after partial save/apply
failure. Ordinary Save leaves applied snapshots stable; another account's captured
revision never advances automatically. Managed/built-in originals remain read-only.
Trials are tab-local, debounced and owner-bound, preserve the original rollback target,
never write preferences and end on navigation/reload. Invalid tokens retain the last
valid trial and entered draft text.

Additive migration `Version20261027143000` ran successfully in development/test and the
fresh production fixture. It adds ThemeFamily, AppearanceSnapshot and AppearancePreference;
only explicitly managed source provenance is paired. Existing theme documents, component
releases and renderer/export settings remain untouched. Built-in light/dark defaults are
real complete palettes and initialize without overwriting user name collisions. Portable
theme schemaVersion 1 remains unchanged; family/preference metadata stays in the database.

Personalized Twig SSR includes both mode styles and a local pre-paint system bootstrap.
Authenticated preferences take precedence over the validated signed guest mode cookie;
no localStorage override exists. Generated CSS has private content-addressed endpoints,
hash validation and SRI. Trial access is owner-only, expires after one hour and is bounded
to 32 recent entries; session candidates expire after five minutes with eight client slots.
The single appearance controller preloads non-empty CSS, fences stale sequences, handles
optimistic conflicts, bounds requests and reconciles/rolls back lost success responses.
Missing assets and font decode failures show honest recovery/fallback status. Turbo/native
history lifecycle and cleanup are covered; iframe sandbox/CSP permissions are unchanged.

Changed areas: migration/entities; focused src/Appearance services; appearance controller,
Twig extension/lazy runtime and private HTML response listener; existing ThemeWriter,
ThemeApiController/ThemeController, input/form and shared ThemeCssGenerator; layout,
gallery/detail/editor templates and existing editor Stimulus; new appearance controller/
bootstrap, semantic studio styles; production Docker resources copy; isolated production
Compose/fixture and tests; APPEARANCE/ARCHITECTURE/CONTRACTS/SECURITY/DEVELOPMENT/MILESTONES/
VERIFICATION documentation. No dependencies or lockfiles changed for M10. Installed
versions remain FrankenPHP1.13.4/PHP8.5.12/Symfony8.1.9/Twig3.30.0/Stimulus3.2.2/
Node24.22.1/Tailwind4.3.3/daisyUI5.7.50/Playwright1.64.1.

Completed verification:

- Full PHPUnit: **120 tests / 694 assertions**, exit0.
- Complete development browser suite: **41 passed / 5.0m**, exit0, with separate artifacts
  at `var/appearance-verification/development-results`. All12 appearance and29 original
  application flows pass together.
- `make check`: Compose, strict Composer, container, 26 YAML and 21 Twig files pass;
  Doctrine mapping/schema synchronized; `git diff --check` passes.
- Real immutable production: **12 appearance browser tests passed / 44.4s**, exit0.
  Fixture `var/appearance-20261027155720-7821`, tag `local-20261027155721-805d8e0c`.
  Runtime-created pair/opposite variant, real computed tokens and local FontFaces work
  after build. Manifest is read-only and unchanged before/after:
  `2697be0747faf6901e441dd5f4d6bf4e9cb354a0604a0172622f61e0282e8416`.
  Offline Inter/Lora/JetBrains Mono imports verify3 families/12faces/511,916bytes.
  Fixture accounts disabled, own services stopped, volumes/evidence retained.
- Fresh consumer repeat: `bash tests/FontConsumer/run.sh`, exit0, fixture
  `var/font-consumer-20261027T160404Z-13295`. Real composed layout, collection/theme/static
  export, local variable/italic/font licences and exact bytes/metadata/checksums survive
  install/reimport; reinstall changed=0; production AssetMapper compiled. Offline consumer
  and file snapshot decode fonts, run Stimulus/modal controls and issue zero studio or
  external runtime requests at mobile/desktop widths, then pass again after restart.
  Own consumer stopped, fixture retained; original consumer8110 stays running.
- All original **47 themes / 308 components / 308 drafts / 1 renderer preset** retain
  their complete pre-change row hashes. All **42 fonts / 208 faces / 6,713,012 bytes**
  verify without errors. Original production8096 and consumer8110 are unchanged.
- Production light/dark/mobile screenshots reviewed; Restore default stays reachable
  and real keyboard focus is visible. Source fonts and renderer settings evidence is
  retained alongside screenshots and logs under `var/appearance-verification`.

Corrections made during verification: lazy Twig runtime preserves unrelated font tests;
Symfony8 Choice uses named choices; JSON variants stay iterable; theme creation flash
survives API redirect; actual debounce uses deterministic browser clock; isolated
production browser networking and separate trace paths prevent fixture collisions;
empty stylesheet responses cannot claim success and all initial/dynamic sheets use SRI;
SVG visibility uses the attribute; the mobile focus check accepts the real solid >=2px
daisyUI outline. Earlier failed runs are retained as failed evidence, not relabelled.

All twelve documented acceptance checks have actual passing development/production,
PHPUnit and consumer evidence. Temporary development browser accounts were removed.
Original row hashes still match after consumer verification/account cleanup. No required
M10 gate remains unfinished. Chromium is verified; no full accessibility certification
or other-browser claim is made. No external deployment, publication or push occurred.

Exact next step: open http://localhost:8095/themes and choose Apply theme, then use the
header moon/sun or Appearance menu. Duplicate a managed/default family to customize it;
use Try on application, Revert and Save & Apply in the existing editor. Restore default
is fixed at the bottom-right. Production8096 remains the older image until a separately
authorized release. Commands, migration/configuration and twelve-check mapping are in
docs/styleguide/APPEARANCE.md and VERIFICATION.md.

## M11 — Theme detail component showcase — 2026-10-27

Implemented on `feature/m11-theme-component-showcase` from the clean existing application.
Theme detail contains65 server-rendered Twig panels across actions/forms/navigation/data/
feedback/layout/mockups, with category/search controls and existing family light/dark
links. All requested component groups have working examples, semantic colours and relevant
styles/sizes/states. Existing Apply/Edit/Duplicate/export and header appearance controls
keep their previous meanings. Viewing/interacting does not save preferences or theme data.

Reuse: ThemeCssGenerator::applicationCss, DocumentAssembler font embedding and unchanged
opaque srcdoc/CSP; existing portable tabs/dialog Stimulus controllers. No model/migration,
second generator/editor, persistent catalogue sample or new dependency was introduced.
Repository-owned Twig partials/macros render all markup; only local behaviours need JS.
The shared assembler has an optional validated hash scope; old callers emit unchanged
root markup. CSS and bundled Stimulus build through make assets and are copied into the
actual production image before AssetMapper compile. New saved themes work without runtime
Tailwind jobs or manifest writes.

Interactions include native controls/disclosures, keyboard tab switching, real native
dialogs, swap states, local selections/slider/file output, focus-trapped drawer with
Escape/backdrop/Close, timed/dismissible toast, carousel, dock, countdown, tooltips and
modern decorative components. Inputs/groups/ARIA IDs are unique. No example submits,
uploads or calls application APIs. All frame HTTP requests are absent; it cannot read
parent DOM. Font, heading/code roles and effective native colour scheme remain independent
from the application's saved family/mode.

Changed files: ThemeController and detail template; new ThemeComponentShowcase service
and nine Twig partial/macro files; DocumentAssembler optional scope; isolated showcase
CSS and fixture Stimulus bootstrap/controller; package scripts, Docker copies, AssetMapper
source exclusions and ignored generated files; Makefile and existing isolated production
test driver; new functional/browser coverage and existing preview readiness test; docs
THEME_SHOWCASE/DEVELOPMENT/ARCHITECTURE/SECURITY/MILESTONES/VERIFICATION and this status.
Versions unchanged: FrankenPHP1.13.4/PHP8.5.12/Symfony8.1.9/Twig3.30.0/Stimulus3.2.2/
Node24.22.1/Tailwind4.3.3/daisyUI5.7.50/esbuild0.28.2/Playwright1.64.1.

Completed gates:

- `make assets`: actual static studio/showcase CSS and isolated Stimulus bundle compiled.
  Browser CSSOM checks every rendered class has a compiled/generated rule; all65 panels
  inherit the selected root tokens, not the studio's current appearance.
- Full PHPUnit **122 tests / 724 assertions**, exit0. Private reader access, full SSR
  inventory, no persistence changes, unique groups/IDs and existing security/package/
  font/renderer/installer/export checks all pass.
- `make check`: Compose, Composer strict, container, **26 YAML / 30 Twig** pass; Doctrine
  schema/mapping synced; git diff whitespace checks pass.
- New development browser spec **5 passed / 1.3m**, with actual contrast families and
  light/dark variants, loaded Inter472italic/Lora631/JetBrainsMono400, computed colours,
  interactions, no HTTP/upload/pref writes, every category at375/768px and tooltip focus.
- Real immutable production **17 browser flows passed / 4.4m**: all12 existing appearance
  and5 new showcase flows. Fixture `var/appearance-20261027172638-15809`, immutable tag
  `local-20261027172638-c0124396`. New themes after compilation render65 examples; manifest
  read-only with identical before/after SHA256:
  `41ff52657f613a2a832c0cf68d20edf9b56db2535286ac754016086acd0b762b`.
  Offline font registration verifies3 families/12faces/511,916bytes. Accounts disabled,
  own fixture services stopped; existing production8096 and consumer8110 preserved.
- Read-only reader survey:8 existing website variants (Airbnb; Atlassian dark/light;
  Discord; Dropbox; Figma; GitHub dark/light), each65 matching component scopes and real
  font decoding, unchanged reader preference. Screenshots/metadata retained.
- All original **284 themes / 430 items / 430 drafts / 1 renderer preset / 1 personal
  appearance preference** retain their complete pre-change row hashes. Font verification
  still **42 families / 208 faces / 6,713,012bytes**, errors=[]. Evidence under
  `var/component-showcase-verification`, including the immutable production manifest.

Corrected during checks: reusable Twig macro scope; explicit select label associations;
native swap hit areas/focus; drawer focus after CSS transitions and backdrop default
checkbox cancellation; mobile intrinsic form widths and tooltip overflow. Focused tests
explicitly reset mode and the read-only survey uses a separate reader to avoid comparing
an editor preference concurrently changed by acceptance tests. First full browser run:
45 passed, one old five-second preview expectation timed out while the worker was still
building. The check now waits for the real bounded current state and retains its actual
class, scheme and persistence assertions; its sequential builds have a matching deadline.

Final development browser evidence: initial full run **45 passed / 1 failed / 14.5m**;
the corrected renderer property/persistence flow rerun **1 passed / 33.1s**, exit0, retaining
the actual worker and class/mode/reload assertions. All46 distinct flows have passing
evidence across these runs; no second complete46-flow passing run is claimed. The first
failure and corrected result are retained separately. All17 production flows and5 new
showcase flows pass. Temporary development accounts were removed after verification.
Original row hashes were rechecked after cleanup and still all match; the42/208 font
registry verifies again without errors. No task-owned browser/production fixture remains
running, and temporary development credential files are absent.

Deliberate omissions: external-library calendar adapters, full-page megamenu and a second
theme-controller. Chromium/native CSS cosmetics are the verified scope; no full WCAG or
all-browser certification. There is no remaining implementation gap for the requested
component groups. No external deployment, branch push or package publication occurred.

Exact next step: open http://localhost:8095/themes, open a theme and browse Component
showcase. Use category/search and Showcase light/dark to inspect a variant without
changing your applied application preference. Coverage, limits, commands and evidence:
docs/styleguide/THEME_SHOWCASE.md and VERIFICATION.md.
