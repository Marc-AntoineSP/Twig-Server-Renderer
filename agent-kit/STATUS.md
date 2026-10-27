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
| M06–M08 | Not started | Existing design/contracts only |

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
