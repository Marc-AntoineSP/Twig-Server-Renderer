# Implementation status

Last updated: 2026-10-29. Scope: M00–M08, font/theme library and M10–M15 complete, including local immutable production verification; no remote deployment or push.

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
| Font/theme library | Complete |42families/208faces;25presets/18references; real imports, exports and retained source evidence |
| M10 Application appearance | Complete | Saved preferences, light/dark, trials and new themes with immutable production assets |
| M11 Component reference | Complete |65panels/7categories; catalogue native fragments preserve opaque sandbox isolation |
| M12 Inline detail layout | Complete |123PHPUnit/752assertions;20production flows; all50 development flows verified across full/focused runs;24existing-theme responsive renders |
| M13 Composer MCP | Complete |135PHPUnit/1138assertions; officialSDK + actualCodex round trips; concurrent writes;3production browser flows; ChatGPT account setup documented |
| M14 Profile and browser setup | Complete |141PHPUnit/1245assertions;3development +6production browser flows; live PAT/OAuth checks; original account/data hashes preserved |
| M15 Personal favorites | Complete |146PHPUnit/1375assertions;58development flows verified across full/focused runs;7production flows; stable variant/item identity and unchanged original data |
| Improvement prompt 12 | Complete for requested scope |148PHPUnit/1382assertions;71browser tests;3engines;12visual baselines;owned-resource rejection/failure/interruption/teardown gates;6immutable-production flows |
| Improvement prompt 01 | Complete for requested scope |151PHPUnit/1446assertions;79browser tests;8 additional empty-workspace UI checks;bounded personal projections;role/ordering/deletion gates |
| Improvement prompt 02 | Complete for requested scope |154PHPUnit/1502assertions;89browser tests;6immutable-production flows;111px mobile shell including safe recovery;12updated/reviewed visual baselines |
| Improvement prompt 03 | Complete for requested scope |157PHPUnit/1568assertions;96browser tests across three engines;20immutable-production flows;read-only draft browsing and exact published-release selection;4reviewed theme-detail baselines |
| Improvement prompt 04 | Complete for requested scope |160PHPUnit/1676assertions;110browser tests across three engines;14final immutable-production flows;shared editing protection and saved-history restoration;4reviewed composer baselines |
| Improvement prompt 05 | Complete for requested scope |163PHPUnit/1739assertions;129browser tests across three engines;31final immutable-production flows;shared discovery GET/URL state, confirmed favorites refresh/Undo and fade-safe modal focus;4reviewed catalogue baselines |

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

## M11 follow-up — Preview links showing the studio login — 2026-10-27

Reproduced the user's exact `/catalogue/01M5Y9QKN2PE245NAPSBB32363/edit` report.
The sample's native `href="#overview"` resolved against the inherited editor URL;
navigation inside the opaque iframe requested that protected route and then `/login`.
This displayed the studio's normal login template inside the preview. It was not a
requirement to authenticate for component actions. Before/after evidence is retained in
`var/preview-navigation-verification`.

The shared DocumentAssembler now emits a trusted `<base href="about:srcdoc">` and permits
only the about base scheme in CSP. Native fragments stay in the current document even
when preview scripts are disabled; no user HTML, href attributes, templates, theme data
or renderer preferences are rewritten. HTTP(S) base injection remains blocked. Existing
sandbox flags, nonce scripts, parent DOM/storage isolation and denied network/form/frame
capabilities remain. Preview policy digest is `sandbox-v2-local-fragments`. Restarted only
the development preview-consumer so the persistent PHP process loads the new assembler.
The existing application at8095 is updated; production8096 was not changed.

Actual verification:

- The exact user component's Overview/Activity/Settings links work by pointer and Enter
  with scripts on/off: frame URL stays about:srcdoc#..., zero iframe HTTP requests,
  fresh authenticated studio request still200, original draft byte/data comparison equal.
  No user account/password or component source was changed.
- New focused development regressions: **2 passed / 45.9s**, for catalogue native
  fragments and every showcase link/cross-category navigation. Includes Unicode/missing
  targets, preserved local input, no iframe reload/login/request and unchanged preferences.
- Full PHPUnit **123 tests / 736 assertions**, exit0; new assembly contract preserves
  original markup/SVG/JS-visible hrefs and network restrictions. Existing contracts,
  renderer, fonts, packaging and isolation checks all pass.
- `make preview-navigation-production`: **12 browser flows passed / 3.2m**, exit0,
  against real immutable APP_ENV=prod/APP_DEBUG=0 images. Auth/login/logout; all5 catalogue
  preview flows (including parent DOM/storage/message isolation); all6 showcase flows.
  Browser verifies HTTP(S) base injection is blocked and fragment links retain the opaque
  sandbox with scripts enabled or disabled. Fixture `var/appearance-20261027182113-25049`,
  tag `local-20261027182114-9d211253`; AssetMapper manifest read-only and identical before/
  after: `41ff52657f613a2a832c0cf68d20edf9b56db2535286ac754016086acd0b762b`.
  Three offline font families verify12faces/511,916bytes/errors=[]. Own production test
  accounts disabled and fixture stopped, volumes/evidence retained.
- `make check`: Compose/Composer strict/container/26YAML/30Twig all pass; diff checks pass.

Changed: DocumentAssembler, PreviewJobs policy; a focused integration test and browser
regressions; whitelisted isolated production test driver/Makefile target; relevant
architecture/development/security/showcase/verification/status documentation. No new
dependency, migration, data-model change, external deployment, publication or push.

Exact next step: reload the user's editor or use Build preview to replace the already
navigated iframe. Local anchors now remain in the preview; there is no need to sign in
inside it. The original component and saved application preferences remain intact.
Temporary development browser accounts and their credential file were removed after
verification; no task-owned browser/production fixture remains running.

## M12 — Full-width inline theme detail reference — completed 2026-10-27

All detail pages now have a300px desktop sidebar and flexible full-width showcase, using
normal document scrolling. A detail-only base main-class block preserves create/edit and
other route layouts. The sidebar owns all existing theme metadata, Apply/Edit/Duplicate,
variants, provenance, contrast, draft downloads, immutable releases and package controls.
Native Theme details disclosure starts collapsed on mobile and opens on desktop; a
breakpoint collapse restores focus. Long sources/identifiers wrap and tables scroll locally.

All65 trusted panels reuse existing Twig partials directly in the one main element:
no detail iframe, nested document, height cap, scroll panel, resize messages or preview
job. User/imported Twig/JS remains in the original opaque sandbox and its about:srcdoc
fragment policy is unchanged. The existing generator shares validated declarations with
a fixed showcase root/hash scope; registered font bytes use ThemeFontAssets embedding
and namespaced face names. Geometry/type/spacing, colours and scheme stay local without
a second reset or changes to saved appearance. The reader-protected hashed CSS link has
SRI/private caching. The existing Stimulus instance registers the trusted/portable
controllers once. Native overlays inherit the owning theme; demos are root-scoped,
fragment links preserve local state, drawers trap/restore focus and toasts expire.

Completed checks:

- make assets/check/diff: pass;26YAML/31Twig. Full PHPUnit:123tests/752assertions, exit0.
- Full development browser run:49passed/1old30s renderer-flow timeout,11.4m. Its specific
  sequential-build deadline is now90s; focused rerun passes1/1 in15.5s with every original
  feature assertion. All50 distinct flows have passing evidence across those runs; no
  second all50 passing command is claimed. All8 new detail workflows pass together.
- make theme-showcase-production:20browser flows pass/3.4m, exit0, real immutable image
  local-20261027192214-db878b0a, fixture var/appearance-20261027192213-13661. Actual JSON/ZIP
  downloads, all draft/release exports, private package publication, legacy sandbox
  selection, Apply/edit/duplicate/create, keyboard overlays, layout, complete CSS classes,
  decoded fonts and new post-build themes pass. Read-only manifest before=after:
  2ff923422b68587eed6d82d934c8b9db5c0c139d99e0eb5cf9eeedac102d631c.
- Reader survey:8existing website variants/24renders at1440/768/375, including the exact
  YouTube reference, both modes and multiple fonts, no iframe/overflow or preference
  change. Desktop dark/light and mobile screenshots visually reviewed.
- Application CSS is byte-identical to HEAD for all474 then-current themes. Every
  original406themes/461items/461drafts/97releases/1renderer preset/299snapshots retain
  their full row hashes after cleanup. The original admin preference changed through7
  successful non-test Apply requests during the work (19:19–19:25UTC); request history
  is retained and the latest choice is preserved, not reset. Fonts verify42families/
  208faces/6,713,012bytes/errors=[]. Test accounts disabled/removed and credentials cleaned;
  task-owned fixtures stopped. Original development8095, production8096 and consumer8110
  remain running. Evidence:var/theme-layout-verification and the production fixture.

Corrected failures: unsupported cache assertion; former iframe selectors; drawer Tab
loop; Turbo fragment reload; filtered-height assertion; overall renderer-test deadline.
The first overlapping production/browser/survey attempt caused CPU contention, one real
bounded worker build failure and slow appearance assertions. It is retained as failed
19/20 production evidence; the final sequential20/20 run passes without weakened feature
assertions or changed worker resource limits. Full details: docs/styleguide/VERIFICATION.md.

Changed files: detail/base/provenance/sidebar and catalogue/macros/layout/action Twig;
ThemeController, ThemeComponentShowcase, shared ThemeCssGenerator; theme_details controller,
scoped showcase layout CSS, studio/ESM/bootstrap/build/Docker rules; focused functional,
showcase and existing header/renderer-deadline browser checks; architecture/security/
milestone/showcase/verification/status docs. Exact inventory: THEME_SHOWCASE.md.
Versions remain Symfony8.1.9, PHP8.5.12, FrankenPHP1.13.4/Trixie, Tailwind4.3.3,
daisyUI5.7.50, Stimulus3.2.2. No new dependency, lockfile, model or migration change.

Remaining limitations: Chromium is verified; no full cross-browser/WCAG certification.
Existing external calendar adapters/full-page megamenu/second theme-controller omissions
remain. No required M12 work remains. No agent edit to original content or personal preferences, external deployment,
external publication or branch push. Exact next step: reload
http://localhost:8095/themes/01M5Y4RDN0XQMGW1HAJ6WZ4ZGE (all theme detail routes use this layout).

## M13 — Authenticated catalogue composition MCP — completed 2026-10-27

The actual composer audit/operation map and full setup/contracts/file inventory are in
COMPOSE_MCP.md. Shared CompositionTree/CompositionWorkspace services now serve manual
commands/Save and an explicit26tool Symfony MCP Streamable HTTP endpoint at/mcp/compose.
The browser retains its unsaved working tree until Save; MCP saves each command/batch
immediately through that same validation/persistence path. Stable optional node IDs,
row-locked optimistic revisions, transactional actor/operation/target retry receipts,
atomic aliases/batches and recoverable before/after snapshots preserve edits. External
revision notices never overwrite unsaved work. Immutable reference/props validation,
manual publishing/source conversion, CSRF, fonts/themes and isolated workers remain.

Installed/locked Symfony MCP0.13.0, official PHP SDK0.8.1, Omouren MCP OAuth1.2 and League
bundle1.2.3/server9.4.1: compatible released graph,19new packages, no existing version
updates/removals. Additive migration Version20261027203258 is installed in dev/test;
original tables/data are unchanged. Private persistent3072bit RSA/encryption keys are
initialized without exposing/overwriting key bytes; files600/directory700. Local PATs
have256bit entropy/hash-only storage/scopes/catalogue patterns/expiry/revocation. Maintained
OAuth supports predefined public/confidential clients, real studio login, bound CSRF
consent, S256, exact redirects/resources/issuer, signed tokens/refresh/revocation and current
user/client checks. JWT subjects are stable User IDs. Every MCP request is authenticated;
session IDs are actor/credential-scoped, not authorization. Production Compose defaults off.

Completed gates and evidence under var/mcp-verification and the isolated production fixture:

- make assets/check;32YAML/34Twig; **135PHPUnit/1,138assertions**, exit0 (final-verified.log).
  Tests include reference/property/tree restrictions, failed batches/retry key mismatch,
  stale browser/AI writes, history/restore, scopes, expired/revoked/disabled credentials,
  cross-actor sessions/previews, captured revisions, cancellation and actual OAuth flows.
- Official SDK HTTP client negotiated2025-11-25 and discovered26tools. It searched real
  releases, created/edited a dedicated layout, replayed a batch, moved/nested/duplicated/
  removed nodes, selected existing ThemeRelease01M5ZE9SBNGCKK9JEA7MJMM7ZV, rendered exact
  revision8 and restored1as9. Actual HTML/CSP verified; visualInspectionAvailable=false.
- Actual installed **Codex0.164.0** MCP client discovered26tools and created/added/read
  saved revision2. No LLM/model turn or global config change. Dedicated credential revoked,
  actor disabled, private credential file removed. ChatGPT account connection is unverified.
- Two real concurrent HTTP sessions prove exactly one winner/one412 for competing writes,
  and one exactly-once receipt replay for identical simultaneous operations.
- Development manual+MCP browser2/2pass, plus focused responsive CSS1/1. Final immutable
  production **3/3flows/33.5s**, exit0, plus official SDK smoke. Fixture
  var/appearance-20261027213428-25078, image local-20261027213428-1a0494f3. Shared drafts open
  and remain editable, original manual actions/source conversion/renderer interactions
  work. Captured preview3 remains3afterdraft4 and is owner-only; disabled JS yields an
  empty sandbox. Actual generated responsive classes work without redeployment.
- Read-only production AssetMapper before=afterSHA256
  9d58c205240700b95cfbc043828078c90c25e029ac31521a204a4b7bdf890d58. Font fixture3families/
  12faces/511,916bytes validates. Feature-off MCP/OAuth/discovery404; ordinary login200.
  Schema/mapping valid; storage114releases/errors=[]. Runtime Composer audit clean.
- All original534themes/284families/496items/496drafts/110catalogue releases/100theme
  releases/1renderer preset/375snapshots/1personal preference retain complete row hashes.
  Fonts still42families/208faces/6,713,012bytes/errors=[]. Test drafts are separately named;
  no real catalogue content was deleted. Browser accounts/files removed, test PATs revoked,
  actors disabled and task-owned production containers stopped.8095/8096/8110 preserved.

Corrected checks: DQL search, SDK JSON object/list preservation, JSONB receipt types,
Symfony8.1 voter signature, bound consent and scope replay checks, canonical resource
pollution rejection, expired/disabled credentials and original login target handling.
A queued-only assertion was corrected because test rendering can finish immediately.
A responsive assertion used the exact breakpoint without accounting for the frame border;
the1280pxviewport verifies3actual columns. A misplaced new assertion was removed. The
read-only font fixture now mounts a separate writable report file; final production has
no PHP error-level log, with informational library/Doctrine deprecations retained. Details
and failed-run evidence are recorded in VERIFICATION.md; final checks pass.

Remaining limits: user-supplied ChatGPT account/workspace/HTTPS or tunnel setup, no OIDC
UserInfo/domain restrictions, no screenshot worker, no source authoring/publishing/admin
tools, no pre-integration unpublished history. New node IDs count toward64KiB; old external
validators need the optional-ID extension. Complete Composer audit still reports the
pre-existing dev-only PHPUnit13.4.3 local PHPT/shared-temp advisory; runtime dependencies
are clean and were not silently suppressed. No external deployment/publication/push.

Exact next step: open http://127.0.0.1:8095, issue a scoped token for your existing editor
with styleguide:mcp:token:issue, merge docs/styleguide/examples/codex-compose-mcp.toml into
your Codex config, set STYLEGUIDE_COMPOSE_MCP_TOKEN in its process environment and reconnect.
See COMPOSE_MCP.md for exact commands/revocation, the sample pricing-layout prompt and
clearly separated credential-dependent ChatGPT connection instructions. All local M13
implementation and acceptance gates are complete.

## M14 — Profile and browser MCP setup — completed2026-10-27

Open **http://localhost:8095/profile**, or select **Profile** in the navigation. Account
name/email/password, personal MCP credentials, copy/download Codex settings, real browser
connection tests and administrator ChatGPT client controls now work through the UI.
Console commands remain optional operator tools. PROFILE.md documents the workflow,
permissions and precise external-connection boundaries; COMPOSE_MCP.md links to it first.

AccountProfile and ComposeConnections reuse User, existing hashers, ComposeTokens and
League ClientManager. CSRF/self-ownership/roles protect all writes. Profile fingerprints
and row locks prevent stale overwrites; email/password identity changes verify the current
password with5checks/5minlimit. Current sessions remain coherent, old sessions expire and
password changes revoke this actor's MCP/OAuth access/refresh/code grants, preserving other
actors. Shared OAuth administration requires ROLE_ADMIN. Secrets are shown once, scrubbed
on navigation, absent from lists/URLs/storage/config downloads and excluded from profiler capture.
Actual cookie-free same-origin MCP calls prove discovery/read-only capabilities without
changing catalogue data. Local OAuth readiness and external account linking are distinct.

Migration Version20261027215409 adds only nullable display_name VARCHAR100 to studio_user,
installed in dev/test. Development preview-consumer restarted to load User metadata; no
original production deployment was replaced. No dependency/lock/version changes.

Completed gates:

- make assets/check;33YAML/36Twig; **141PHPUnit/1245assertions**, exit0,1m23.7s.
  Actual validation, persistence/login continuity, password/old-session/grant revocation,
  ownership/roles/CSRF, metadata fingerprints, throttling, secret lifecycle and MCP/OAuth
  regressions pass. Evidence:var/profile-verification/verified-checks.log.
- Development browser3/3pass/17.8s. Actual account save, unsaved fragment navigation,
  credential scopes/revocation, config download, live PAT/OAuth MCP checks and cookie
  omission pass.1440/768/375px, mobile light and desktop dark reviewed, no document overflow.
- Immutable production **6/6flows/45.6s**, exit0, plus official SDK26tool preview/history
  smoke. Fixture var/appearance-20261027223038-13445, image local-20261027223039-4d2896b4.
  Includes all3new profile flows and original authentication/manual composer/MCP conflict/
  captured-preview flows. Admin-created client performs real local code/PKCE/token/JWT
  validation and disable/enable from the profile. External ChatGPT callback is not visited.
- Manifest read-only and before=after:
  6e8185df5895c5426a17e0b68472712d8cafece6c79b2c01261aee176cbe2049. New saved account/token/
  client changes need no production asset rebuild/redeployment. Fixture fonts3/12/511,916
  bytes verify. Schema/mapping valid; source/font/export/worker isolation tests remain green.
- Original7accounts' pre-existing fields,534themes/284families/512items/512drafts/114releases/
  100theme releases/1renderer preset/375snapshots/1personal preference/6MCPcredentials retain
  hashes. Fonts42families/208faces/6,713,012bytes/errors=[]. Browser actors/files removed;
  test OAuth clients disabled and production test accounts disabled/containers stopped.
  Existing8095/8096/8110 services preserved. No original account, content or preference edit.

Corrected failures: form-helper checkbox merging vs native submission; POST-URL reload405
(real browser/first production5/6) fixed with canonical profile history and safe GET return
routes; native fragment links preserve unsaved fields. Same-origin MCP requests from
validated Docker hosts work while hostile hosts/origins remain rejected. Final6/6production
and141/1245PHPUnit gates pass; failed-run evidence is retained in VERIFICATION.md.

Changed: User/ComposeToken, ProfileController, two Profile services, profile Twig partials,
profile_mcp Stimulus, base navigation, private-profiler/Origin guards, rate limiter/migration,
ProfileTest/profile.spec.js, Makefile/isolated production driver and relevant docs/status.
Stack remains PHP8.5.12/Symfony8.1.9/FrankenPHP1.13.4/Trixie/Tailwind4.3.3/daisyUI5.7.50/
Stimulus3.2.2. Existing dev PHPUnit advisory is recorded; no new dependency was introduced.

Remaining boundaries: user must complete configuration in the external client and actual
ChatGPT account linking; reachable HTTPS/OAuth/tunnel/workspace access remains user-supplied.
Profile cannot override deployment-level MCP disable/origin/ingress policy or write files
on a client computer. Browser checks are labelled accordingly. No external publication,
deployment or push. No required M14 implementation or local acceptance gate remains.

Exact next step: open /profile, save personal details if desired, expand Create a personal
MCP credential, create/copy it, click Test MCP connection, then copy/download the client
settings. Administrators can register the exact ChatGPT callback in the next section.

## Requested MCP draft exercise — 2026-10-28

Used the existing styleguide_compose endpoint through the installed Codex0.164.0 MCP
client, with no model turn, configuration edit, publication, deployment or push.
Discovered26tools and10released studio-showcase component types; inspected all10props
schemas. Created layout `codex/codex-2026-10-28`, title `codex-2026-10-28`, item
`01M60K5SG4JFE87J100G61ZMV1`. Two typed batches saved revision3 with29nodes and14component
instances: navigation, alert, stats,3cards, tabs, progress, fields, accordion, dialog and
3button variants. A bounded responsive container holds the content with mobile gutters
and responsive1/2/3column grids. Existing references remain pinned to1.0.0.

Actual checks: compose_validate returned valid=true/no warnings at revision3; compose_get
confirmed14reference nodes. compose_render_preview/get_preview succeeded for that exact
revision at1280px using existing light theme release01M5ZE9SBNGCKK9JEA7MJMM7ZV. Preview
job01M60KGVD6WYPBJJ09YH7HMY2A returned136975bytes of HTML. Parsed its actual style output
and verified max-w-7xl, responsive gutters and md/xl/lg grid selectors plus document CSP.
Final compose_search_catalogue confirmed releases=[]; the layout remains unpublished.
No browser/screenshot check was run; visualInspectionAvailable=false. No dependencies or
application source changed; only this status file changed in the repository.

Initial native MCP startup failed because the configured bearer-token environment setting
names an unset variable. A temporary process-only credential override connected successfully;
the saved client setting remains unchanged. The first sandboxed client exited; the approved
launch outside that filesystem sandbox worked. An oversized terminal batch was rejected
before execution, so two smaller batches were used. No credential values were saved in
task files. Command: python3 /tmp/styleguide-compose-20261028-client.py. Check evidence:
/tmp/styleguide-compose-20261028-verification.json. Temporary client has been stopped.

Exact next step: open the authenticated composer and captured preview:
http://127.0.0.1:8095/catalogue/01M60K5SG4JFE87J100G61ZMV1/compose
http://127.0.0.1:8095/catalogue/composition-previews/01M60KGVD6WYPBJJ09YH7HMY2A

## M15 final handoff — Personal favorites (2026-10-28)

Completed personal theme-variant/component/block/layout favorites on the existing shared
reader catalogue. Stable Theme/CatalogueItem FKs preserve favorites across edits/releases;
unique owner/target indexes, exclusive-target CHECK and cascades provide database integrity.
Explicit authenticated CSRF PUT/DELETE are idempotent. Batched request-local status maps
avoid per-card favorite queries. Published theme package cards reuse their typed variant.

Shared Twig/SVG stars and AssetMapper Stimulus cover listings/details/editors/composer,
pressed labels, keyboard focus, pending requests, real server-state reconciliation after
failures and duplicate-button synchronization. Catalogue/theme URL filters compose with
existing search/tags/kind/mode/sort before12-item pagination; list context survives detail,
edit and save. Clear-filter and distinct empty states are integrated. Theme search is now
server-side instead of hiding an entire unpaginated gallery in JavaScript. Apply remains
independent. Composer favorites narrow published immutable choices, include older preferred
releases, preserve current nonfavorite references and cannot bypass shared tree validation.
MCP search supports favoritesOnly plus favorite status under its existing actor/scope rules.

Final measured gates:
- `make assets`, `make check`, schema/mapping and diff checks pass;33YAML/41Twig valid.
- `make test`:146tests/1375assertions, exit0,83.988s, including persistence/isolation,
  repeated operations/removal, CSRF/reader/anonymous/deletion/disabled account, revision
  identity, combined filters/pagination/return links and authenticated MCP restrictions.
- All58development browser flows verified: full run55pass/3fail, then3/3focused rerun
  in43.5s. Original failures: concurrent compiler budget, dialog test timeout and old
  exact-URL assertion that did not allow the preserved-filter query. No budget changes.
  All4new favorites flows pass, including lost committed response, mobile/light/dark,
  multiple sessions, all reference kinds, Apply independence and sandbox preservation.
- Final immutable production7/7browser flows in54.2s plus real official SDK HTTP smoke,
  using new post-build data with no asset redeployment. Fixture:
  var/appearance-20261028075717-30570, image:local-20261028075717-5f95203c.
  Manifest is read-only/unchanged:d3d982375e35556ab9bcdb00574a644326cd7a9621c0d76f90b420c4a8633927.
- Original7accounts/534themes/284families/513items/513drafts/115releases/100theme releases/
  1renderer preset/375snapshots/1preference/7MCPcredentials/4OAuth clients retain complete
  row hashes (changedOriginalRows=[]), including the pre-existing Codex composition.
  Fonts42families/208faces/6,713,012bytes/errors=[]. Browser test actors removed, new test
  OAuth client disabled; isolated production actors disabled/containers stopped. Existing
  development8095, production8096 and consumer8110 remain available.

Migration Version20261028072749 applied in development/test/isolated production. Other
installations: normal authorized code update, then doctrine:migrations:migrate. Exact
commands, full changed-file list, APIs, behavior and limitations:docs/styleguide/FAVORITES.md.
Measured evidence/failure history:VERIFICATION.md and var/favorites-verification. Files
include Favorite/Favorites/API/Twig extension/migration, search DTOs/services/controllers,
shared stars/filter/pagination partials, listing/detail/editor/composer integration,
MCP application/generated schemas and generator, functional/browser tests, acceptance
Make target/driver and architecture/milestone/verification/status docs. No dependencies,
lockfiles, original templates in the catalogue, fonts or imported themes were rewritten.
Stack remains PHP8.5.12/Symfony8.1.9/FrankenPHP1.13.4/Trixie/Tailwind4.3.3/daisyUI5.7.50/
Stimulus3.2.2. The untracked .codex/config.toml was preserved.

Environment failure resolved for verification: jsDelivr/Packagist certificate trust in
this local build environment required the host public CA bundle, staged in the ignored
verification directory and mounted as a BuildKit build secret with TLS verification on.
Acceptance driver supports APPEARANCE_COMPOSE_OVERRIDE for that local overlay. No system
trust-store/repository Dockerfile or running original deployment change. See verification
failure record for the initial rejected outside-context mount and earlier failed build.

Remaining behavior: filtered cards leave the list on search/refresh after their stars
update; current references remain visible outside picker filters. No MCP favorite mutation
tool was requested. Existing production8096 was not redeployed. No required local M15
implementation or acceptance gate remains. No publication, branch push or external deploy.

Exact next step: open http://localhost:8095/catalogue or /themes, use a title's star, then
check Favorites only and Find items/Find themes. In a composition, use Favorites only above
the reference insertion picker. Development already has the migration and rebuilt CSS.

## Improvement prompt 12 — Isolated browser acceptance — completed 2026-10-28

Read both selected prompts/12-isolated-browser-tests.md and its supplied prompt pack;
all three packaged Markdown checksums pass. Verified the review against this checkout:
Make browser provisioned users in the configured development app, helpers persisted
catalogue fixtures there, cleanup removed only accounts, and Playwright used Chromium
with a working-app URL fallback. Existing production appearance tooling supplied the
local isolation precedent. The user's twelve staged prompt files remain separate.

Implemented a standalone Docker acceptance topology with unique 96-bit run identities,
private PostgreSQL/blob/runtime volumes, sessions/cache/OAuth keys, app-side consumers,
internal renderer/compiler networks and null mail. No writable checkout or published
host port is used. Empty-store initialization binds the private receipt to PostgreSQL
metadata and blob sentinels; account/seed commands and a fresh HTTP HMAC challenge fail
closed when either store/identity is wrong. Normal browser checks never use working
credentials or catalogue data. No application migration is required.

Four random-password actors (including a separate visual editor), four stable catalogue
items, three immutable item releases, two theme releases, paired variants and private
favorites are bounded seeds. Display timestamps are fixed. Five pinned Fontsource
families/22 faces/661852 bytes support existing real-font tests. The working-theme
reference in fonts.spec.js now selects the owned released theme. CRLF normalization
occurs only in image copies of repository fixtures whose byte hashes require LF.

Make commands cover start/seed/run/check/cleanup, full browser, three-engine smoke,
visual comparisons and deliberate baseline updates. Per-run lifecycle locks prevent
concurrent operations on one fixture; independent projects coexist. Normal failure,
SIGINT/SIGTERM and explicit cleanup use exact receipts/Docker labels, mounts and networks.
Worker image deletion uses exact tags plus project/service labels; shared tool caches
are preserved. Retained mode prints the exact cleanup command. Credentials are removed,
traces stay disabled, one-time profile/read-token fields are scrubbed, and bounded text
artifacts redact secrets. Production appearance/profile tooling uses the same proof,
private acquisition inputs, immutable assets, scoped teardown and internal networks.

Completed checks (actual Docker/browser execution):

- `python3 tests/BrowserHarness/run.py run --with-checks`: exit 0 in fresh project
  browser-aec5b542047eb7a17155b62f. Locked image/CSS/JS builds, migrations, strict Composer,
  container, 34 YAML and 41 Twig checks pass. **148 PHPUnit / 1382 assertions**, 89.9s;
  **71 Playwright tests**, 5.2min: 65 Chromium plus three Firefox and three WebKit.
  Normal teardown completed. Evidence: var/browser-verification/complete-gate.log.
- Nine axe reports: zero automated violations. Reviewed the retained incomplete
  color-contrast findings: daisyUI background images/gradients prevent deriving the
  effective background (3 listing, 13 composer, 3 sidebar node occurrences per engine).
  They are not suppressed or certified as compliant. Reports remain under the fresh
  project's artifacts/invocation-*/results; preview frames and the separately themed
  demonstration are explicitly outside application-control audits.
- Twelve deterministic Chromium/Linux PNGs cover listing/composer/theme detail at
  390/1440px in light/dark. `test --update-baselines -- tests/Browser/visual.spec.js
  --project=chromium` deliberately generated candidates; spot-reviewed narrow/wide,
  light/dark images. A second fresh project browser-5099ed1dcdcc08f74f336fab ran the
  same four cases/12 comparisons without updates: **4/4 pass, 14.6s**.
- `test --visual-probe -- tests/Browser/visual.spec.js --project=chromium --grep
  '1440px light'`: deliberate heading-size regression was rejected by pixel comparison;
  all baseline hashes stayed unchanged. The command's expected-failure gate passes.
- `test -- tests/Browser/favorites.spec.js --project=chromium --grep 'theme variant
  stars' --repeat-each=3`: **3/3 pass, 13.0s** after explicit initial mode and waiting
  for Turbo's new variant DOM before opening its mobile disclosure. Keyboard focus,
  cancellation and accessibility checks pass in all three engines; no human keyboard
  or screen-reader session is claimed.
- `make profile-production`: **6/6 pass, 45.1s**, plus official SDK smoke, valid
  schema/mapping, font verification and identical before/after read-only AssetMapper
  manifest. Fixture appearance-20261028180136-2582 was removed. Final production cleanup
  changes were rechecked with `bash tests/AppearanceProduction/run.sh
  tests/Browser/authentication.spec.js`: **1/1 pass, 2.2s**, immutable checks and teardown.
- `python3 tests/BrowserHarness/safety.py <owned-run> <second-owned-run>`: repeated seed
  leaves row counts unchanged; wrong run, database and blob proofs reject account cleanup
  or browser startup before catalogue mutation. Two live projects have disjoint storage/
  networks and no host ports; default development configuration is also disjoint.
- `python3 tests/BrowserHarness/lifecycle.py`: real failed-test and SIGTERM commands
  leave no matching containers, volumes, networks or private credentials. Serial
  teardown/image-ID comparison followed by another live project's authentication test
  proves shared images remain usable. Syntax checks and git diff --check pass.
- Final scoped inventory: all 17 recorded browser runs and five production fixtures
  have no runtime containers/volumes/networks or private credentials. Old exact owned
  worker tags were removed after label checks; reusable tool/immutable image caches and
  sanitized evidence remain. The working Compose stack was not running before/after;
  it was not started or modified to prove preservation. No working-row hash comparison
  or three-simultaneous-stack runtime test is claimed.

Observed tool versions: FrankenPHP1.13.4/PHP8.5.12/Symfony8.1.9; Node24.22.1;
Playwright1.64.1 with Chromium156.0.8078.4, Firefox157.0 and WebKit27.2;
new exact @axe-core/playwright/axe-core4.14.0 recorded in package.json/package-lock.json.
Tailwind4.3.3/daisyUI5.7.50/Stimulus3.2.2 and existing application/worker locks remain.

Changed files: Makefile, Compose browser/appearance configuration, FrankenPHP test image
stage, package manifest/lock, Playwright config, .gitattributes/.gitignore and existing CI;
BrowserRunGuard/BrowserRunController, BrowserAccountsCommand/BrowserSeedCommand and health
access rule; tests/BrowserHarness lifecycle/proof/fixture/production tools, functional
BrowserSafetyTest, critical/visual suites and twelve baselines. Existing helpers/fonts/
favorites/profile/registry tests now remove working-state assumptions, await actual DOM
state and scrub secrets. Consumer/font-consumer/production-recovery drivers receive a
pre-mutation ownership guard. Updated DEVELOPMENT, BROWSER_TESTS, ARCHITECTURE, SECURITY,
MILESTONES and this handoff.

Failed checks resolved during implementation: inherited HTTP healthcheck on the queue
consumer; debug-off AssetMapper URLs without compilation; missing test .env/schema copies;
CRLF fixture checksum drift; WebKit rejecting single-label session cookies (owned studio.test
alias); missing Manrope/working GitHub theme and offline artifact directory; reduced-motion
setting interfering with the existing timer test; favorites contaminating visual choices;
mobile Turbo timing and inherited appearance mode. Earlier full run had 62/71 passes,
then 70/71 before those focused fixes; the final fresh complete gate passes 71/71.
A cleanup experiment with Compose --rmi local also removed a shared app tag; the final
implementation removes only labelled per-run worker tags. An image-ID check overlapping
another image build was rerun serially and passed. No such experiment remains in the
normal commands, and none accessed working catalogue data.

Remaining limits: human screen-reader/interactive keyboard checks are unrun; use the
checklist in BROWSER_TESTS.md. Axe gradient contrast findings require manual assessment;
automation does not certify accessibility. The legacy consumer/font-consumer and full
backup/restore driver topologies still require migration to owned resources; they now
fail before mutations on an unowned studio. CI uses the owned full PHP/browser gate and
immutable profile/compose/authentication harness; fresh full consumer/restore acceptance
was not rerun. Docker/host SIGKILL or daemon loss requires retrying the exact printed
cleanup command after recovery. No remote publication, deployment or push occurred.

Exact next step: use `make browser` (or `make browser-smoke`/`make browser-visual`) as the
safe foundation for prompts01–11. Baseline changes require deliberate `make browser-baselines`
and PNG review; migrate legacy consumer/recovery topology as a separate scoped task.

## Improvement prompt 01 — Workspace homepage (2026-10-28)

Scope: `prompts/01-workspace-homepage.md` and its matching prompt pack. All three
packaged SHA256SUMS entries verified. The requested shorthand `1-workspace-homage`
resolves to that existing prompt. Starting source still contained Docker/PostgreSQL/
AssetMapper readiness and the obsolete claim that rendering/publishing awaited workers.
There is no existing infrastructure administration page to move those messages into;
development setup remains documented in DEVELOPMENT.md.

Implemented a short task area, Recently updated and Your favorites using the shared
shell/card conventions. Editors can create an item/theme, import, and resume existing
source/composition/theme editors; readers get detail views and browse actions. Managed
and built-in themes always open their detail view. Cards show title, stable name/kind,
composition/variant label, draft revision, latest release or Unreleased, and UTC time.
Recent work and favorites have independent permitted empty states. Favorite management
continues through the existing catalogue/theme stars; no extra homepage mutation API.

WorkspaceRepository returns two SQL projections, each limited to six scalar summaries.
It never hydrates source/theme documents or the complete favorite set. An inner draft
join and supported kinds exclude missing/unsupported catalogue targets before LIMIT;
the favorite owner predicate also precedes LIMIT. Recent timestamps use the greatest
metadata/draft update, followed by descending stable ID. Typed theme variants use their
own update/revision/release and family label. Built-in defaults stay out of recent work
but may appear in personal favorites. Existing enabled readers share all catalogue
drafts/themes; updated_by is attribution, not a new per-author privacy rule. Authentication,
personal favorite ownership, private/no-store HTML and anonymous login redirects remain.

Changed files: HomeController, new WorkspaceRepository, studio/home and new workspace
card partial; BootstrapTest and new WorkspaceTest; browser bootstrap/catalogue checks
and new workspace.spec.js; DEVELOPMENT.md, BROWSER_TESTS.md, MILESTONES.md and this handoff.
No dependencies, migrations, configuration, worker policy or navigation-shell changes.
The twelve user-staged prompt files remain unchanged and excluded from implementation.

Checks so far: locked Docker app/runner and CSS/AssetMapper builds; strict Composer,
container, 34 YAML and 42 Twig checks pass. Fresh owned run
`browser-8cca47a42ec08e3a3b07cf67` passes **151 PHPUnit tests / 1446 assertions** in
91.4 seconds. Coverage includes real timestamp/tie ordering and six-row limits, an older
favorite beyond the recent limit, other-user exclusion, missing draft/deleted targets,
real release summaries, exact light/dark identities, permitted destinations, escaped
titles, anonymous/reader access, and transactional independent empty states. Truncation
in that empty-state test requires the isolated `_test` database and rolls back.

Failed checks resolved: the first PHP run reached 151 tests/1439 assertions with one
test-fixture error from a detached User after HTTP requests; the test now reacquires its
managed reference. An inherited bootstrap CSS assertion targeted the plain appearance
recovery control; it now checks the actual daisyUI Create item action. No product CSS or
recovery behavior was changed to satisfy that assertion. The first retained run
`browser-9cac59ba87b01ad8487868a1` was fully cleaned with its exact harness command.
The first complete browser attempt passed 74/79: that CSS assertion plus four editor
workspace cases failed. Existing favorite regression tests intentionally leave more
than six newer editor favorites, pushing old seeds outside the bounded homepage. The
new workspace cases now use the harness's separate editor actor, restore temporary
favorite changes, and retain the real six-item ordering. After rebuilding the runner,
a fresh normal build/run verifies the corrected tests; no product behavior was changed
for this fixture assumption. The first complete run was also fully cleaned.

Final fresh browser run `browser-d12e7ec3de55a482821e37a3`: **79/79 pass in 5.4 minutes**
(73 Chromium, 3 Firefox, 3 WebKit), including all eight populated workspace role/width/
mode checks, real create/resume/save, and all twelve existing pixel comparisons. Existing
visual baselines remain unchanged. After the normal suite, a one-off owned-store review
stopped only this run's queue consumer, reverified database/blob ownership in PHP, emptied
only its disposable catalogue with TRUNCATE CASCADE, and checked eight empty workspace
combinations (reader/editor × 390/1440px × light/dark). All eight passed actual layout,
keyboard focus/Enter, permitted destination and axe checks. PHPUnit empty fixtures remain
transactional; this separate UI review's whole environment is removed afterward.

Sixteen homepage axe reports have **zero violations**. The eight reader reports have
no incomplete findings. Four populated editor reports retain color-contrast incomplete
groups for nine gradient/background-image buttons each; four empty editor reports retain
the same finding for two buttons each. No rules were disabled. Full-page captures retain
the real shell/recovery control and are not pixel baselines. Reviewed populated narrow/
wide and empty mobile/desktop captures in light/dark, with additional editor dark capture;
automated checks exercised keyboard access, not a human screen-reader/keyboard session.

Exact commands used (all framework/build/browser execution in Docker):

```sh
# From the matching pack directory: all three entries OK.
sha256sum --check SHA256SUMS
# First complete run: lints + 151 PHP passes; 74/79 browser before fixture corrections.
python3 tests/BrowserHarness/run.py run --with-checks --keep
# Final fresh build/browser suite after those test corrections: 79/79.
python3 tests/BrowserHarness/run.py run --keep
# Local retained one-off script, not a new default test-harness mode:
python3 var/browser-verification/workspace-empty.py browser-d12e7ec3de55a482821e37a3
make browser-cleanup RUN=browser-9cac59ba87b01ad8487868a1
make browser-cleanup RUN=browser-8cca47a42ec08e3a3b07cf67
make browser-cleanup RUN=browser-d12e7ec3de55a482821e37a3
git diff --check
```

Current measured versions: FrankenPHP 1.13.4 / PHP 8.5.12 / Caddy 2.11.7, Symfony
8.1.9, PHPUnit 13.4.3, Node 24.22.1 and Chromium 156.0.8078.4. The existing pinned
Playwright 1.64.1, Tailwind 4.3.3, daisyUI 5.7.50 and Stimulus 3.2.2 locks are unchanged.

Evidence: `var/browser-verification/workspace-{initial,complete,final-browser,empty}.log`,
matching cleanup logs and the retained one-off empty-review Python/JS scripts. Final
populated captures/reports are under
`var/browser-runs/browser-d12e7ec3de55a482821e37a3/artifacts/invocation-098c4ff3/results/workspace-*`;
empty captures/reports are under that run's `artifacts/workspace-empty/`. All are ignored
review evidence; no credentials belong in artifacts. No working catalogue was used,
seeded or cleaned. No remote publication, deployment, push or production-stack mutation.

Remaining limits: human screen-reader checks and manual assessment of axe's button
contrast findings remain unrun. Shared navigation and the floating appearance recovery
control are unchanged (prompt 02 scope). Fresh immutable-production/profile acceptance
was not repeated for this homepage change; compiled Docker/AssetMapper browser assets
and the complete existing PHP/browser gates were exercised. No migration/configuration
or new dependency is needed. Exact next step: review `/` with normal reader/editor
accounts, or select the next numbered prompt explicitly; do not run fixture commands
against the working studio. Cleanup/resource audit is recorded immediately below.

Final resource audit: all three task runs have zero containers (including stopped),
volumes, networks, private files and per-run worker tags, with CLEANED receipts present.
Reusable app/runner images and build cache remain. No containers are running on this
host, matching the starting inventory. Evidence: `var/browser-verification/workspace-resource-audit.json`.
The staged prompt diff still hashes to
`d7f37c445e44f29e12983531103fd1d80acbeab737b1c0aab86787c5e95429c1`.

## Improvement prompt 02 — Navigation and appearance recovery (2026-10-28)

Scope: matching prompt02 and pack, all three SHA256SUMS entries verified. Starting
owned run `browser-fc8953cd47462bcd510f8ebf` measured header heights of **229px at
320px**, **193px at 390px**, and **65px at 1440px**. Recovery was fixed, z-index
2147483000 and 41px tall. A seeded catalogue thumbnail additionally forced 794px
document width on both narrow viewports. These are fresh runtime measurements,
retained under its `artifacts/navigation-before/`, not the historical review dimensions.

Implementation: one compact mobile header with ordinary native Navigation, Appearance
and Account disclosures, concise section context and visible mode toggle. Desktop links
remain inline. Email/Profile/Sign out/admin Read tokens move into Account. Active page
links use aria-current=page, parent sections use location, with an underline. Labels
and long emails have bounded wrapping/truncation. Native disclosures work without JS;
Shell Stimulus adds expanded-state reflection, Escape/restore, outside click/focus
dismissal and Turbo cleanup. Navigation has no focus trap.

Recovery occupies a reserved strip below the navigation row, with hardcoded safe colors, fonts,
focus and 44px controls. Trial and error states gain explicit safe explanations, keeping
Revert trial separate from resetting the saved family/system preference. The same
trusted form is added inside native modal/dialog drawer content, respecting top-layer
inertness. Only modal dialogs get Tab boundary containment, and close restores their
opener (or the disclosure summary when the original menu action is hidden). Main content
is an isolated document layer; shell disclosures sit above it; dialogs use the native
top layer. There is no recovery z-index escalation or fixed content overlay. The theme
showcase and sidebar layout stay intact. A minimal catalogue card min-width correction
removes the thumbnail-driven overflow found in the baseline.

JavaScript reset retains prepare/load/commit and existing trial semantics. A narrowly
scoped CSRF-protected POST /appearance/reset is a real no-JS fallback through the same
AppearanceManager and optimistic revision checks. It can select only built-in default/
system for the current account, or the existing validated guest mode cookie. It accepts
no owner/theme/redirect selection, and redirects to Workspace/Sign in. No migrations,
new dependencies or changes to source/preview isolation. Native skip links bypass Turbo
so reaching recovery cannot initiate a visit while an editor is active.

Initial verification: Docker app/runner/CSS/AssetMapper builds, strict Composer,
container, 34 YAML and 44 Twig checks; **154 PHPUnit tests / 1502 assertions** pass.
Six width/mode shell cases and no-JS navigation/reset/logout pass, as do the strengthened
three-engine critical modal checks after focus fixes. The complete regression run
and deliberate visual-baseline review results follow below.

Resolved failed checks: the first PHP run had six failures from old nav email selectors,
a generic _token collision, inert template status markup and a new home-link assertion.
Account selectors are updated, the reset field is uniquely named, and a live role is
assigned only after modal cloning. Browser audits caught compact-brand/translated-label
name mismatches; accessible names now derive from visible text/labelled spans. A test
outside-click point was under the open panel; it now uses the visible search field.
Old appearance color checks needed the pointer moved off the hovered Apply button and
the mode badge's existing color utilities retained. Modal Tab/close focus needed actual
boundary handling and restoration after the browser's close work. New hit-area assertions
wait for modal animation; touch checks exclude hidden trial controls; Turbo assertions
wait for the rendered destination, not just its URL. No checks were disabled to hide
these findings. Temporary logs/artifacts remain under var/browser-verification/navigation-*.

Final geometry at **320/390/1440px**: **56px navigation row + 54px recovery strip,
111px total including header border**, static positioning, and document scroll width
exactly equal to viewport width. Menus now open below the complete header/recovery strip
so they cannot cover reset, including when trial/error content expands it. The account
container's unnecessary generic aria-label and the text-glyph navigation icon were also
replaced with valid native naming/SVG markup after axe's incomplete findings were reviewed.

Final fresh run `browser-a7a032554515b48f9cc05683` passes strict Composer, container,
34 YAML / 44 Twig lint, **154 PHPUnit tests / 1502 assertions (96.6s)** and **89/89
Playwright tests (6.2m)**: 83 Chromium, 3 Firefox, 3 WebKit. This includes all ten shell
flows, both changed showcase focus/recovery paths, all existing application/source/
composition tests and twelve visual comparisons. After that full run, the unreadable
fixture was strengthened to force the light variant and assert actual white-on-white
body colors after Save & Apply, rather than possibly selecting the readable paired dark
variant. It also checks reset leaves the saved theme colors unchanged. Only that test
fixture changed; rebuilding the runner and rerunning the focused case passes **1/1 in
4.9s**. Application code is unchanged from the passing full gate.

The first broad attempt was **83/89**, with four intentionally stale visual cases and
two showcase assertions still expecting the old floating recovery / old last drawer
control. Its anchored grep did not exclude the visual cases because Playwright matches
full titles. Those assertions now use the recovery skip link and the new final modal
control. A fresh focused shell/showcase run passes **18/18**. Deliberate baseline
generation then passes **4/4** and writes all twelve PNGs. All twelve were inspected
(listing/composer/theme detail × 390/1440px × light/dark), with the full-width showcase
and left sidebar retained. The subsequent independent fresh full run compares them
successfully. Ordinary test commands still cannot update baselines.

Six final shell axe reports have **zero violations**. Their single incomplete finding
is contrast for the artificially long email in the open account panel; it remains in
the reports for manual assessment. Earlier generic-role/name mismatches were fixed;
no axe rules or screenshot masks were disabled. Keyboard, touch, Escape, outside click,
back/forward DOM restoration, 200% text reflow and Chromium 2× pinch zoom were automated.
This is not a human screen-reader session, browser-UI zoom certification or a claim of
application-wide WCAG conformance. W3C target-size/focus and APG disclosure references
were checked and are linked in APPEARANCE.md; 44px is the product target.

`make profile-production` additionally passes **6/6 in 39.4s**, the official SDK smoke,
font/schema checks and unchanged read-only AssetMapper manifest after post-build theme
creation/application, in isolated `appearance-20261028203941-27933`. An earlier production
run also passed 6/6 in 40.0s. Both projects' runtime volumes/networks/credentials were
removed by their normal traps; immutable build images/evidence remain. No working
production deployment, remote publication or branch push occurred.

Exact principal commands:

```sh
# In the matching prompt-pack directory: three checksum entries OK.
sha256sum --check SHA256SUMS
make browser-start
python3 tests/BrowserHarness/run.py run --with-checks --keep -- tests/Browser/shell.spec.js tests/Browser/appearance.spec.js tests/Browser/critical.spec.js tests/Browser/authentication.spec.js tests/Browser/profile.spec.js
python3 tests/BrowserHarness/run.py run --keep -- tests/Browser/shell.spec.js tests/Browser/theme_components.spec.js --project=chromium
python3 tests/BrowserHarness/run.py test --run-id browser-1f233dd10586498f498c44d2 --update-baselines -- tests/Browser/visual.spec.js --project=chromium
python3 tests/BrowserHarness/run.py run --with-checks --keep
make browser-run RUN=browser-a7a032554515b48f9cc05683 BROWSER_ARGS='tests/Browser/shell.spec.js --project=chromium --grep unreadable'
make profile-production
make browser-cleanup RUN=browser-a7a032554515b48f9cc05683
git diff --check
```

Changed files: base and appearance controls/reset/nav-link Twig partials, one catalogue
card class; Shell/Appearance Stimulus, shell.css and studio.input.css; AppearanceController
and the exact guest reset access rule in security.yaml; new functional ShellTest/browser
shell.spec.js, existing authentication/appearance/profile/critical/showcase checks and
twelve PNG baselines; APPEARANCE, PROFILE, DEVELOPMENT, ARCHITECTURE, CONTRACTS, SECURITY,
BROWSER_TESTS, MILESTONES and this handoff. No operator environment variables, new
dependency or database migration are needed. The checked-in route/access rule supplies
the guest fallback; this is not a new guest catalogue permission.

Measured toolchain: FrankenPHP 1.13.4 / PHP 8.5.12 / Caddy 2.11.7, Symfony 8.1.9,
PHPUnit 13.4.3, Node 24.22.1; Chromium 156.0.8078.4, Firefox 157.0, WebKit 27.2.
Existing Playwright 1.64.1, Tailwind 4.3.3, daisyUI 5.7.50 and Stimulus 3.2.2 locks remain.
Logs: `var/browser-verification/navigation-{baseline,initial,focused,focused-final,dialog-gate,complete,review,baselines,measurements,final,unreadable-final,production-final}.log`
and corresponding cleanup logs. Final browser evidence is under
`var/browser-runs/browser-a7a032554515b48f9cc05683/artifacts/`; reviewed shell/zoom/touch
captures are also under `browser-1f233dd10586498f498c44d2/artifacts/invocation-b7445957/`.

Remaining checks: human screen-reader use, manual assessment of the retained long-email
contrast finding, and real browser-UI zoom beyond the measured text-reflow/pinch paths.
Ordinary navigation and the fallback reset are verified with JavaScript disabled; other
appearance actions retain their existing JS requirement. The no-JS strip explains that
reset navigates to Workspace/Sign in and edits should be saved first. Exact next step:
review the shell with normal accounts or explicitly select the next numbered prompt.
Cleanup audit follows below. The twelve user-staged prompt files remain unchanged.

Final cleanup audit: **all eight browser runs and both production fixtures** have zero
containers (including stopped), volumes, networks and private credential files. Browser
CLEANED receipts exist and their per-run worker tags are removed. Reusable locked
app/runner/toolchain images and build cache remain. No containers are running on the host.
Evidence: `var/browser-verification/navigation-resource-audit.json`. No working catalogue
was used for fixtures or cleanup. The staged prompt diff retains SHA-256
`d7f37c445e44f29e12983531103fd1d80acbeab737b1c0aab86787c5e95429c1`.


## Improvement prompt 03 — Theme action clarity (2026-10-28)

Scope: selected prompt03 and matching pack; all three checksum entries verified.
Fresh owned baseline `browser-a151d1fbaf2cf6fd3a8151a0` confirms the detail action
**Apply to showcase** posts to `/themes/{id}/apply`, saves the selected immutable
release in the current user's sample renderer preset and opens `/catalogue/{id}/edit`.
The application appearance API state remains identical. This reproduces the supplied
source observation against the current checkout. The first ad-hoc probe closed the
already-open desktop disclosure and timed out; correcting that probe passed.
Evidence: `var/browser-verification/theme-actions-baseline.log`.

Implemented one shared translated gallery/sidebar action partial: **Apply to application**,
**Browse component showcase** (the existing read-only draft detail + fragment), and
**Preview published theme in component editor** (the existing CSRF/editor-only POST).
Draft swatches/inline examples identify revision, name and mode; published previews
identify the immutable name/version/mode and explain the separate saved renderer effect.
New forms pin their displayed release ID and the server checks its Theme identity.
Older POST clients without `releaseId` still select the latest publication; ties use ID.
A bounded gallery batch selects releases for the visible twelve cards. No migration,
new dependency, new endpoint or appearance/preset storage redesign is introduced.
Draft-only variants retain browsing, explain the unavailable preview and give a permitted
Edit/publish or Duplicate/Edit next step. Missing/cross-theme releases return a usable
422 page without writes. The full action/permission/state map is in THEME_SHOWCASE.md.

First fresh check `browser-5fab23519ca4a8610daa7951` built/linted successfully but its
157 PHP tests had two failures and one error (1511 assertions). All three new tests saw
translation keys: Docker copied no translation catalogue. Both self-contained app stages
now copy `translations/`; this is required for the existing Symfony translation mechanism
in immutable images. No browser test ran after that failed PHP gate. Verification reruns
and production checks follow below. Baseline runtime resources have been cleaned.


Fresh rerun `browser-4084262b22099df87cdbeb6c`: Docker/CSS/AssetMapper builds, strict
Composer, container, 34 YAML and 46 Twig checks pass; **157 PHPUnit tests / 1568
assertions (105.7s)** pass, including read-only database snapshots, pinned old release
after a newer publication, legacy selection, independent accounts, CSRF/GET/reader
rejection and missing/wrong-theme 422 states. Focused browser verification is running.

`make theme-showcase-production`: **20/20 Chromium flows (1.3m)** pass in owned
`appearance-20261028211403-3001`, including changed labels, actual published-editor
selection, new post-build families, trial/save/recovery, inline examples and exports.
Mapping/schema checks pass. The read-only AssetMapper manifest remains unchanged.
Its normal cleanup removed runtime containers, volumes, networks and credentials.
No working production instance was deployed or modified. Log:
`var/browser-verification/theme-actions-production.log`.


Focused browser result: **14/15** pass (1.0m). All six 320/390/1440 light/dark action
flows and eight existing showcase workflows pass. The new final reader assertion
incorrectly expected a readable empty renderer preset; that API is editor-only. The
assertion now checks 403 and unchanged editor selection. Only this test changed; the
rebuilt runner's exact-release flow passes **1/1 (8.6s)**, including publication after
form display, actual older palette in the opaque iframe, reload persistence, draft-only
opposite variant, browsing without writes, Apply independence and reader denials.
Six axe reports have zero violations; gradient primary controls and short release-count
badges retain color-contrast incompletes for manual assessment. No rules were disabled.

The translation catalogue's explicit Docker YAML lint passes (1 file). Runtime confirms
PHP 8.5.12 and Symfony 8.1.9. An ad-hoc version probe initially omitted the Composer
autoloader; `php bin/console --version` corrected that tooling-only error.

Deliberate visual generation passes **4/4 (15.8s)**. All four changed theme-detail PNGs
were inspected at 390/1440 in light/dark; the full-width showcase and left sidebar remain.
Two additional focused screenshots (320 light, 1440 dark) show wrapped labels and visible
keyboard focus. An explicit unrelated-baseline byte check caught a composer-390-dark
candidate differing by seven pixels at a rounded corner (x24-26,y511-513). The original
was restored; all eight listing/composer source baselines remain byte-identical to HEAD.
The candidate/original and pixel analysis remain in var/browser-verification; no new
masks/tolerances were added. A new independent full run with the reviewed theme baselines
and original other baselines is now running.


Final independent run `browser-fc8ae20140a0cfcc2501a85d` is **exit 0**: locked Docker/
CSS/AssetMapper builds, strict Composer, container, 34 YAML / 46 Twig lint, **157 PHPUnit
tests / 1568 assertions (98.7s)** and **96/96 browser tests (6.5m)**. Browser split:
90 Chromium, 3 Firefox, 3 WebKit. All seven new action flows, existing appearance/source/
authentication/favorites/workspace/composer/showcase workflows and all twelve visual
comparisons pass. The eight unrelated original baselines pass without modification.
No application code changed after the passing immutable-production gate. Only the reader
assertion correction and reviewed theme PNGs preceded the independent final run.

Exact principal shell commands (from repository root unless noted):

```sh
# In "prompt pack/03-theme-action-clarity/03-theme-action-clarity":
sha256sum --check SHA256SUMS
python3 tests/BrowserHarness/run.py start --keep
python3 tests/BrowserHarness/run.py run --with-checks --keep -- tests/Browser/theme_actions.spec.js tests/Browser/theme_components.spec.js --project=chromium
make theme-showcase-production
python3 tests/BrowserHarness/run.py run --with-checks --keep
make browser-cleanup RUN=browser-a151d1fbaf2cf6fd3a8151a0
make browser-cleanup RUN=browser-5fab23519ca4a8610daa7951
make browser-cleanup RUN=browser-4084262b22099df87cdbeb6c
git diff --check
```

The retained review operations used the same Run helper with its lifecycle lock held
and ownership verified; exact build/lint/test calls were:

```python
r = Run('browser-4084262b22099df87cdbeb6c')
r.compose('--profile', 'tools', 'build', 'browser')
r.app('php', 'bin/console', 'lint:yaml', 'translations')
r.test(['tests/Browser/theme_actions.spec.js', '--project=chromium', '--grep', 'exact published release'])
r.test(['tests/Browser/visual.spec.js', '--project=chromium'], update_baselines=True)
```

The final version capture/cleanup also runs through the verified, locked Run helper:
`r.app('frankenphp', 'version')`, a runner Node/browser version query, then `r.cleanup()`.
Logs are `var/browser-verification/theme-actions-{baseline-start,baseline,focused,
focused-final,production,reviewed-gate,review-final,final,final-cleanup}.log`; artifacts
are under each corresponding `var/browser-runs/{runId}/artifacts/invocation-*/`.

Changed files (28): ThemeController, ThemeRelease's repository mapping,
ThemeReleaseRepository, ThemeCatalogue and ThemeComponentShowcase; shared theme actions,
gallery/sidebar/inline header and unavailable-page Twig; messages.en.yaml and the two
Docker translation COPY instructions; new ThemeActionsTest/theme_actions.spec.js,
renamed selectors in appearance/favorites/theme_components, four theme-detail PNGs;
APPEARANCE, THEME_SHOWCASE, DEVELOPMENT, BROWSER_TESTS, CONTRACTS, MILESTONES and this status.
No migration, dependency update or operator environment setting is needed. Rebuilt
application images must include the translation catalogue; both app image stages now do.

Remaining limits: only the configured English message catalogue is supplied; human
screen-reader checks and manual contrast assessment remain unrun. Six new automated
application-control audits have zero violations, with gradient/short-badge contrast
incompletes retained for review; they do not certify application-wide accessibility.
No working catalogue was used for fixtures/cleanup, no real user content changed, and
no deployment, remote publication or branch push occurred. Exact next step: review the
clarified theme actions, or explicitly select the next numbered prompt. Final owned-
resource cleanup audit follows below; user-staged prompt files remain unchanged.


Final cleanup audit: **all four browser runs and the production fixture** have zero
containers (including stopped), volumes, networks and private credential files. All
four browser CLEANED receipts exist and their per-run worker image tags are removed.
Reusable locked app/runner/production images and build cache remain. Evidence:
`var/browser-verification/theme-actions-resource-audit.json`. All six final theme-action
axe reports retain zero violations. The staged prompt diff retains SHA-256
`d7f37c445e44f29e12983531103fd1d80acbeab737b1c0aab86787c5e95429c1`.

Measured runtime: FrankenPHP 1.13.4 / PHP 8.5.12 / Caddy 2.11.7, Symfony 8.1.9,
PHPUnit 13.4.3, Node 24.22.1; Chromium 156.0.8078.4, Firefox 157.0, WebKit 27.2.
Existing locked Playwright 1.64.1, Tailwind 4.3.3, daisyUI 5.7.50 and Stimulus 3.2.2
remain unchanged. Final cleanup/version command exited 0. The only remaining staged
user changes are the twelve numbered prompts, excluded from the implementation commit.


## Improvement prompt 04 — Editing protection and composition history (2026-10-29)

Scope: selected prompt04 and matching pack; all three packaged checksum entries pass.
Fresh owned baseline `browser-d544eb808ccd93b93acd000a` reproduces source header
navigation discarding dirty content without a prompt. Composer/theme header navigation
prompts correctly, but browser Back bypasses both prompts and leaves the editor. Evidence:
`var/browser-verification/editing-baseline.log`. Normal browser unload protection already
existed; it is preserved. Turbo 8.0.23's versioned History source and official events
reference confirm restoration visits bypass before-visit. No historical screenshot is
claimed as a fresh test. All tests use verified owned database/blob/runtime resources.

Implemented shared lifecycle guard + native modal confirmation, browser unload and
pinned Turbo restoration traversal handling without browser storage/history pushes.
Source saves now enhance the existing revision/CSRF-protected API; no-JS SSR form stays.
All editors compare document/metadata snapshots, retain newer changes during pending
saves and expose dirty/saving/saved/failure/conflict state. Theme family labels and
inactive variants are included. Composer now has bounded saved-history/difference pages
and restore-as-new-head via CompositionHistory shared with MCP, retaining current
reference permissions, row locks, revision checks and MCP replay receipts. No second
history store or migration is introduced. Completed verification is recorded below.

Initial Docker build/container/34 YAML/48 Twig checks passed. The first PHP run had
3 failures (157 tests/1552 assertions): composer pages attempted to generate an invalid
numeric history route with a symbolic __REVISION__ placeholder. The client now appends
the numeric revision to the real history base URL; the route remains strictly numeric.
No browser test ran after that failed gate. Updated fixtures use the accessible Keep
editing/discard dialog for in-app confirmation; native unload tests remain native.


The first two new-flow runs each passed 10/13 and exposed the same three editor Back →
canceled reload failures. Capturing popstate alone did not precede Turbo's existing
Window target listener, so Turbo could queue a restoration before the confirmation.
The bounded field-preservation helper alone was insufficient. The final guard registers
before the pinned Turbo 8.0.23 history callback by removing/readding that same callback;
it neither replaces Turbo's implementation nor pushes history entries. The retained
trace `editing-back-reload-probe-final.log` established the ordering defect. The corrected
run passes **22/22 focused browser checks**, followed by **3/3 explicit canceled-Back
checks in Chromium, Firefox and WebKit**. Revisit, forward, native reload/close dismissal,
failed saves, real delayed-save responses and later conflicts are exercised.

A separate broader gate passes **160 PHPUnit tests / 1676 assertions (137s)** and
**29/29 browser tests (3.0m)**. The final independent build passes strict Composer,
container, **34 YAML / 48 Twig** checks and **160 PHPUnit tests / 1676 assertions
(102.9s)**. Its first full browser attempt passed 109/110: an older catalogue stale-tab
assertion still awaited the SSR POST after source saves moved to the existing PUT API.
The test now awaits the real PUT response, checks HTTP 412, retained text and conflict
state; its focused **3/3** catalogue run passes. The complete browser rerun passes **110/110 (7.3m)**: 104 Chromium, 3 Firefox and
3 WebKit, including all twelve visual comparisons. No application code changed for
that test correction.

The final immutable-production gate passes **14/14 new editing/history flows (56.2s)**
against fixture `appearance-20261029081533-9769`, including a save response arriving after
new invalid defaults or advanced source JSON. Its read-only AssetMapper manifest remains
unchanged; schema and font verification pass. The earlier production gate passed
**16/16 flows (1.4m)** including existing composition, MCP and authentication coverage,
with the official MCP SDK smoke enabled. Both fixture projects are cleaned. These are
local disposable production images, not a remote deployment or publication.

Visual review deliberately updates only the four composer baselines for the Saved
history action (390/1440, light/dark). All four updated PNGs and all four history panel
captures were inspected. Unrelated listing/theme baseline source bytes remain unchanged;
the review gate passes 4/4 visual tests. Four new history axe reports contain zero
violations; final color-contrast incompletes remain (2 nodes at 390px light, 5 per
wide report; none at 390px dark). Human screen-reader checks and manual contrast
assessment remain unrun.
Evidence: `var/browser-verification/editing-history-accessibility-audit.json` and the
per-invocation `history-accessibility.json`/PNG files. No masks, looser thresholds or
accessibility-rule exclusions were introduced.

Exact principal commands (repository root unless noted):

```sh
# In "prompt pack/04-editing-protection-and-history/04-editing-protection-and-history":
sha256sum --check SHA256SUMS
python3 tests/BrowserHarness/run.py start --keep
python3 tests/BrowserHarness/run.py run --with-checks --keep -- tests/Browser/editor.spec.js tests/Browser/composition.spec.js tests/Browser/critical.spec.js tests/Browser/appearance.spec.js tests/Browser/compose_mcp.spec.js
python3 tests/BrowserHarness/run.py run --keep -- tests/Browser/editing_protection.spec.js tests/Browser/composition_history.spec.js tests/Browser/critical.spec.js
bash tests/AppearanceProduction/run.sh tests/Browser/editing_protection.spec.js tests/Browser/composition_history.spec.js tests/Browser/composition.spec.js tests/Browser/compose_mcp.spec.js tests/Browser/authentication.spec.js
bash tests/AppearanceProduction/run.sh tests/Browser/editing_protection.spec.js tests/Browser/composition_history.spec.js
python3 tests/BrowserHarness/run.py run --with-checks --keep
make browser-cleanup RUN=browser-d544eb808ccd93b93acd000a
make browser-cleanup RUN=browser-3ad52b20f9534e1b80c946a3
make browser-cleanup RUN=browser-7ff43a3cd7614736b1c8d1ac
make browser-cleanup RUN=browser-1eb521c43c73986f7aa3a930
make browser-cleanup RUN=browser-a30a564ff9d67295d7a19726
make browser-cleanup RUN=browser-ae50c3a8ba51a2800a23ea8a
```

Retained review operations use Run with its lifecycle lock held and ownership verified:

```python
r = Run('browser-ae50c3a8ba51a2800a23ea8a')
r.compose('--profile', 'tools', 'build', 'browser')
r.test(['tests/Browser/critical.spec.js', '--grep', 'immutable reference selection'])
r.test(['tests/Browser/visual.spec.js', '--project=chromium'], update_baselines=True)
# Final test correction and complete browser rerun, with unchanged application image:
r = Run('browser-b123139347445232fe62ab49')
r.compose('--profile', 'tools', 'build', 'browser')
r.test(['tests/Browser/catalogue.spec.js', '--project=chromium'])
r.test([])
```

Logs: `var/browser-verification/editing-{baseline,initial,focused,new-flows,
protection-rerun,guard-final,review,production,production-final,final,
final-browser-rerun}.log`; sanitized artifacts are under the corresponding
`var/browser-runs/{runId}/artifacts/invocation-*/` directories. The final browser run is
`browser-b123139347445232fe62ab49`. Earlier failed runs are retained as evidence rather
than reported as passing. All mutations/cleanup target verified owned resources; the
working catalogue and unrelated Docker projects are untouched.

Changed files (35): shared `assets/editing_guard.js`, three editor controllers,
confirmation/history Twig partials and their base/editor forms, appearance reset's
in-place exemption, CompositionHistory/CompositionRestoreInput, CompositionController
and the shared MCP adapter. Verification changes: CompositionHistoryTest, two new
browser specs, catalogue/critical/compose_mcp assertions, production spec allowlist and
four composer PNGs. Documentation: EDITING, ARCHITECTURE, CONTRACTS, SECURITY, COMPOSE_MCP,
DEVELOPMENT, BROWSER_TESTS, MILESTONES and this status. No migration, dependency/lockfile
change or operator environment setting is needed. Rebuild the application image for
the PHP/Twig/AssetMapper changes.

Remaining implementation limits: native unload prompts follow browser policy; the
restoration guard is coupled to pinned Turbo 8.0.23 and must be checked on upgrade.
Only captured historical saves are recoverable. Current reference permissions and
availability apply to inspection/restoration; missing/pruned history is explained,
not reconstructed. Browser lost-response retries may conflict and require deliberate
reconciliation; existing MCP durable retry receipts are preserved. Full Undo/Redo,
shortcuts and persistent browser recovery are outside this task. Exact next step:
review the editing/history flows or explicitly select the next numbered prompt.


Final cleanup/version capture uses the verified, locked final Run helper:

```python
r.app('frankenphp', 'version')
r.app('php', 'bin/console', '--version')
version_query = "import {chromium,firefox,webkit} from 'playwright'; console.log('Node '+process.version); for (const [name,type] of Object.entries({chromium,firefox,webkit})) {const browser=await type.launch(); console.log(name+' '+browser.version()); await browser.close();}"
r.compose('run', '--rm', '--no-deps', 'browser', 'node', '--input-type=module', '-e', version_query)
# Always in finally:
r.cleanup()
```

This command exited 0; exact log:
`var/browser-verification/editing-final-cleanup.log`. Measured runtime: FrankenPHP
1.13.4 / PHP 8.5.12 / Caddy 2.11.7, Symfony 8.1.9, Node 24.22.1, Chromium 156.0.8078.4,
Firefox 157.0 and WebKit 27.2; test runner is PHPUnit 13.4.3. Existing locks remain
Playwright 1.64.1, Tailwind 4.3.3, daisyUI 5.7.50, Stimulus 3.2.2 and Turbo 8.0.23.

Final owned-resource audit: **all seven browser runs and both production fixtures**
have zero containers (including stopped), volumes, networks and private credential
files. All seven CLEANED receipts exist; per-run browser worker image tags are removed.
Reusable app/browser/production images and build cache remain. Evidence:
`var/browser-verification/editing-resource-audit.json`. Final passing browser artifacts
are `browser-b123139347445232fe62ab49/artifacts/invocation-a3205161/`; four new history
axe reports contain zero violations. The only deliberately changed image baselines
are the four composer PNGs. No migration, push, remote deployment, real-user content
mutation or remote package publication occurred.

Final Git checks: `git diff --check` passes. The twelve previously staged numbered
prompts are excluded from the implementation commit and retain staged-diff SHA-256
`d7f37c445e44f29e12983531103fd1d80acbeab737b1c0aab86787c5e95429c1`.


## Improvement prompt 05 — Discovery and favorites feedback (2026-10-29)

Selected prompt05 and matching pack only; all three packaged checksums pass. Branch on
arrival: feature/m19-discovery-and-favorites-feedback, based on completed prompt04.
The twelve staged numbered prompt additions are preserved and excluded from this work.
Fresh baseline uses verified owned run `browser-0c3621c3d37a97d71a84d48a`: after a real
DELETE, favorites-only catalogue still shows one card/count1; `/fonts?q=lora&category=serif`
loads empty controls/all five families, typing does not update that URL, and selecting a
theme mode does not submit. Evidence: `discovery-baseline.log` and
`discovery-baseline-final.log` in var/browser-verification. The first probe stopped on
an overly strict selector for the old implicit Category label; the corrected probe
completed successfully. No working catalogue or historical screenshot is used as fresh
runtime evidence.

Implementation: a small shared Twig listing layout, one lifecycle-scoped Stimulus
controller and normal GET forms; Turbo Frames reuse the same authorized full SSR
responses as direct visits. Fields remain outside the refreshed frame. Search uses a
350ms debounce with IME composition handling; selections, chips and pagination submit
promptly. Typing replaces history; explicit form/filter/page changes advance; confirmed
favorite reconciliation replaces. Per-request aborts and bounded echoed request receipts
reject obsolete responses and queued renders. Recoverable failures keep the previous
list and offer actual Retry results. Canonical page clamping redirects once on the
server. Shared controls include active chips/Clear all and counts. Catalogue adds an
exact comma-separated AND-tags filter; font search/category/page are validated URL data
and SSR-filter before twelve-family pagination. Font specimens stay independent/lazy,
using the unchanged licensed local registry.

Confirmed favorite removals retain existing persistence/API semantics. Favorites-only
refreshes update cards/counts/pages together. One bounded 15-second Undo notice calls
explicit PUT, reconciles interrupted writes via GET, and refreshes under current filters;
it never blindly toggles or inserts a card locally. Focus follows removed results or the
summary; focused Undo notices do not expire. Regular cards retain updated stars. No new
favorite store, migration, dependency or composer-picker change.

Initial build/strict Composer/container/YAML/Twig checks pass. Initial PHP run:
160 tests /1667 assertions, two failures from old count selectors now pointing at the
new empty live-status node; assertions now target the visible summary. A separate
browser attempt passed12/16 and exposed chip accessible names missing the visible colon,
a duplicate font search/form label, and an audit started during a WebKit promoted-frame
visit. Chip names now include visible text, landmarks have distinct domain labels and
critical audits await navigation completion. No axe rule or screenshot threshold is
relaxed. New functional/browser gates are running; this milestone is not yet complete.


Subsequent functional checks exposed assertion issues rather than mismatched result
sets: randomized masked CSRF tokens prevent whole-HTML equality, select values require
select assertions, and the framework adds cache directives beyond private/no-store.
Tests now compare result identities and required cache semantics. Targeted revision run
passes2/3 PHP tests (45 assertions), then9/10 selected browser checks (38s); its remaining
no-JS Back failure is browser-native form restoration retaining an edited select even
when the URL is restored. A controlled before/after probe proves autocomplete=off fixes
that behavior. The shared GET form now applies it. Source controls, totals and URLs are
restored without adding browser storage.

The resulting full focused gate passes **163 PHPUnit tests /1739 assertions (101.4s)**
and **39/39 browser tests (2.4m)**, including existing favorites/fonts/catalogue and all
three critical engines. Initial immutable-production gate passes **24/24 flows (1.3m)**
(new discovery + existing favorites/authentication); the latest receipt-only guard
refinement passes a second **19/19** production discovery run. Both verify schema/fonts
and unchanged read-only AssetMapper assets. These local projects are automatically
cleaned, with no working catalogue or remote publication/deployment involved.

Visual review caught excessive mobile toolbar height. The shared layout now uses two
narrow columns, complete visible controls, tighter input spacing and full-width actions;
desktop retains four columns. Long active chips wrap within the viewport. This compact
revision is being checked in a fresh owned browser run and immutable-production image.
Only four catalogue PNG baselines will change; the eight unrelated composer/theme source
baselines are preserved byte for byte. Final independent full-suite evidence and cleanup
follow below when finished. Earlier failed attempts are retained, not counted as passing.


Final compact layout passes **28/28 browser tests (1.7m)** across three engines and
**19/19 immutable-production discovery flows (1.0m)**. The baseline update gate passes
4/4 tests (16.1s); all four final catalogue PNGs and all twelve discovery review captures
were inspected. Mobile controls use two columns without hiding controls; the result
summary and first card now appear earlier. The eight composer/theme baseline source
files match HEAD byte for byte. No masks or relaxed visual thresholds were introduced.
Twelve new discovery axe reports have zero violations. Their pre-annotation incompletes
identify gradient-button contrast and an existing named generic theme-swatch div; the
latter now has role=group and is undergoing a fresh final production audit. Manual
contrast and human screen-reader assessment remain unrun.

The independent final build passes **163 PHPUnit tests /1739 assertions (106.5s)**,
strict Composer, container, 34 YAML and 49 Twig checks. Its full browser suite is running.
This image was built just before the single role=group annotation on the existing theme
swatches; the separate final production gate checks that annotation. PHP behavior,
filtering, transport logic, test code and rendered layout are unchanged by it.

Exact principal commands (repository root unless noted):

```sh
# In "prompt pack/05-discovery-and-favorites-feedback/05-discovery-and-favorites-feedback":
sha256sum --check SHA256SUMS
python3 tests/BrowserHarness/run.py start --keep
python3 tests/BrowserHarness/run.py run --with-checks --keep -- tests/Browser/catalogue.spec.js tests/Browser/favorites.spec.js tests/Browser/fonts.spec.js tests/Browser/critical.spec.js
python3 tests/BrowserHarness/run.py run --with-checks --keep -- tests/Browser/discovery.spec.js tests/Browser/catalogue.spec.js tests/Browser/favorites.spec.js tests/Browser/fonts.spec.js tests/Browser/critical.spec.js
python3 tests/BrowserHarness/run.py run --keep -- tests/Browser/discovery.spec.js tests/Browser/critical.spec.js
bash tests/AppearanceProduction/run.sh tests/Browser/discovery.spec.js tests/Browser/favorites.spec.js tests/Browser/authentication.spec.js
bash tests/AppearanceProduction/run.sh tests/Browser/discovery.spec.js
python3 tests/BrowserHarness/run.py run --with-checks --keep
make browser-cleanup RUN=browser-0c3621c3d37a97d71a84d48a
make browser-cleanup RUN=browser-19c56b7f75f8bcd5b3309116
make browser-cleanup RUN=browser-70a2f7ae9f60f740d40242df
make browser-cleanup RUN=browser-2f7c2dd4b23ace9f652ade64
make browser-cleanup RUN=browser-e07d2288062dd0f9fd352936
make browser-cleanup RUN=browser-62c7db8a365c2eff84436da3
```

Retained diagnostic/review operations run under the exact Run lifecycle lock with
ownership verified. The targeted PHP gate creates/migrates that run's test database,
then invokes `r.compose('run','--rm','--no-deps','-e','APP_ENV=test','app','php',
'bin/phpunit','--filter','DiscoveryTest')`. The selected browser revision gate is
`r.test(['tests/Browser/discovery.spec.js','--project=chromium','--grep',
'1440px light|composition input|confirmed removal|regular cards|no-JavaScript'])`.
The final baseline review uses `Run('browser-62c7db8a365c2eff84436da3')` and
`r.test(['tests/Browser/visual.spec.js','--project=chromium'], update_baselines=True)`;
eight unrelated original PNGs are retained even if a candidate differs.

Logs are `var/browser-verification/discovery-{baseline-start,baseline,baseline-final,
initial,browser-initial,focused,new-browser,revision-start,revision-checks,
native-history-probe,focused-final,review,production,production-final,compact,
production-compact,compact-review,final,production-accessibility}.log`.
Sanitized browser artifacts remain under each owned run's artifacts/invocation-*/.
The final full run is `browser-0adc5919f0416dba971be28a`. The first three production
projects are appearance-20261029094624-8072, appearance-20261029094910-11502 and
appearance-20261029095327-16288; each completed its automatic cleanup. Final cleanup
and audit evidence follows below. No working catalogue or unrelated project is used.

Changed files: shared discovery Twig layout/controller and favorite request helper;
existing catalogue/theme/font listings and favorite button/filter/pagination/empty
partials; removal of obsolete local font/theme filters; ListingResponse, FontSearchQuery,
FontLibrary, SearchQuery and catalogue repository/three listing controllers; DiscoveryTest
and discovery browser spec, relevant existing assertions/production allowlist and four
catalogue PNGs. Documentation: DISCOVERY, FAVORITES, ARCHITECTURE, CONTRACTS, SECURITY,
DEVELOPMENT, BROWSER_TESTS, MILESTONES and this status. No migration, dependency/lockfile
update or operator setting is required. Rebuild the application image for Twig/PHP/
AssetMapper changes. Full Undo/Redo, composer picker changes and catalogue performance
work remain scoped to their separately selected prompts.


Final production annotation gate also passes **19/19 (1.0m)** against
`appearance-20261029100013-20261`, with immutable/read-only AssetMapper, schema and font
checks passing. All twelve final discovery axe reports have zero violations; the named
swatch group finding is resolved, leaving only gradient-button contrast incompletes.
Evidence: `var/browser-verification/discovery-accessibility-audit.json`. This fourth
production fixture completed automatic cleanup. No accessibility rules were disabled;
manual contrast and human screen-reader checks remain explicitly unrun.


The first independent full browser attempt passes **128/129 (8.4m)**, including all
new discovery flows and twelve visual comparisons. One existing Chromium showcase
keyboard check fails focus wrapping after a separately focused recovery button and
page-level Tab. Nine isolated repetitions across Chromium/Firefox/WebKit pass (43.9s),
so a deterministic product failure was not reproduced. The test now sends the real Tab
through `recovery.press('Tab')`, keeping its origin and key action together; the same
Cancel-dialog focus assertion remains. No retry count, timeout or rule was weakened.
The retained final run rebuilds app/runner with the one-line swatch annotation and test
correction, recreates only its owned app/consumer, clears its own cache, lints all Twig,
then runs nine repetitions followed by the entire suite. Backend PHP is unchanged from
the passing163/1739 gate. Evidence: discovery-critical-repeat.log and
discovery-final-rerun.log. This replaces the earlier pre-annotation image for the final
browser gate; no additional migration or storage change is involved.


The atomic-key-action change alone is insufficient: the next repeated check still has
one Chromium failure (8/9 pass). Instrumented native-dialog probes reproduce it without
changing application behavior. During a failing keydown, the modal-box ancestor has
opacity0 from its opening fade while display/visibility remain visible. All controls
pass normal/CSS visibility but fail checkOpacity, so the shell's trap finds zero controls
and Tab reaches BODY. Evidence: discovery-dialog-focus-probe.log and the concise
discovery-dialog-visibility-probe.log. The shared shell now excludes hidden/disabled
controls while ignoring opacity for keyboard order. The existing critical test freezes
this real fade state for a deterministic regression and restores styling in finally;
no screenshot is captured with the test fade applied. The same focus assertion remains.
A fresh runner first proves the regression fails against the old shell, then rebuilds
only the owned app/consumer for nine corrected repetitions and the full suite.
The final production gate includes discovery plus the existing appearance workflows.


The first fade regression merely requested opacity0 while allowing CSS to interpolate;
it unexpectedly passed against the old shell and the verification driver stopped before
claiming success. The deterministic version disables transition and pins opacity0 for
its keyboard check, restoring both inline properties in finally. It **fails against the
old shell as expected**, then the corrected shell passes **9/9 repetitions (44.5s)** in
Chromium/Firefox/WebKit. The full suite is now running on the rebuilt final app. The
post-fix production gate passes **31/31 flows** (new discovery + existing appearance),
with unchanged read-only AssetMapper, schema and font checks. Its exact project is
`appearance-20261029101620-19507`; automatic cleanup completed. The final verification
log is `discovery-final-verified2.log`, and production is `discovery-production-verified.log`.
These supersede the failed/incomplete reruns recorded above.


Final rebuilt app/runner gate passes **129/129 browser tests (8.3m)**: 123 Chromium,
3 Firefox, 3 WebKit, including all nineteen new discovery flows, deterministic modal
fade regression and twelve visual comparisons. Final app contains the swatch group and
shell focus fix. No application or test code changed afterward. The prior PHP gate
passes **163 tests /1739 assertions**; final Twig lint passes49 templates. Backend PHP
has not changed since that successful full PHP run. All gates for this requested scope
are complete. The final full invocation and owned-resource audit are recorded below.

Final command sequence, under the verified lifecycle lock for
Run('browser-0adc5919f0416dba971be28a'):

```python
r.compose('--profile', 'tools', 'build', 'browser')
# Negative regression on old shell: the pinned fade check must fail.
r.test(['tests/Browser/critical.spec.js', '--project=chromium', '--grep', 'theme detail, disclosure'])
# After confirming the expected failure:
r.compose('--profile', 'tools', 'build', 'app')
r.compose('up', '-d', '--wait', 'app', 'preview-consumer')
r.app('php', 'bin/console', 'cache:clear')
r.app('php', 'bin/console', 'lint:twig', 'templates')
r.test(['tests/Browser/critical.spec.js', '--grep', 'theme detail, disclosure', '--repeat-each=3'])
r.test([])
```

The final production command is:
`bash tests/AppearanceProduction/run.sh tests/Browser/discovery.spec.js tests/Browser/appearance.spec.js`.
It passes31 flows (1.6m); no production SDK smoke is claimed for this task. Final
browser verification: `var/browser-verification/discovery-final-verified2.log`.
A retained earlier negative proof and aborted verification driver are intentional
failure evidence, not additional passing checks. No retry policy was enabled.

Final changed files (43) include the shared shell's opacity-independent modal focus
filter and its critical regression, in addition to the discovery files listed above.
No migration, dependency/lockfile change, new operator setting or composer-picker change
is required. Only the four catalogue/listing baselines changed; all eight unrelated
source PNGs remain byte-identical to HEAD. All twelve discovery screenshots and four
updated baselines were reviewed at390/1440 in light/dark. Final production axe scans
have zero violations; gradient-button contrast incompletes and human screen-reader
assessment remain for manual review. Full Undo/Redo and discovery performance are
separate work. Exact next step: review the completed discovery/favorites workflow, or
explicitly select the next numbered prompt. No push or remote deployment is authorized
or performed; all mutations/publication fixtures remained inside verified owned stores.


Final cleanup audit: **all seven browser runs and all five production fixtures** have
zero containers (including stopped), volumes, networks and private credential files.
All seven browser CLEANED receipts exist and per-run worker image tags are removed.
Reusable app/browser/production images and build cache remain. The audit is
`var/browser-verification/discovery-resource-audit.json`. The final passing browser
invocation is `browser-0adc5919f0416dba971be28a/artifacts/invocation-271aa5d6/`; its twelve
new discovery axe reports have zero violations, as do the twelve final production
reports recorded in discovery-accessibility-audit.json.

Final version capture and cleanup use the same verified, locked Run helper:

```python
r.app('frankenphp', 'version')
r.app('php', 'bin/console', '--version')
version_query = "import {chromium,firefox,webkit} from 'playwright'; console.log('Node '+process.version); for (const [name,type] of Object.entries({chromium,firefox,webkit})) {const browser=await type.launch(); console.log(name+' '+browser.version()); await browser.close();}"
r.compose('run', '--rm', '--no-deps', 'browser', 'node', '--input-type=module', '-e', version_query)
# In finally:
r.cleanup()
```

This command exited0. Evidence: discovery-final-cleanup.log. Measured runtime:
FrankenPHP1.13.4 / PHP8.5.12 / Caddy2.11.7, Symfony8.1.9, Node24.22.1,
Chromium156.0.8078.4, Firefox157.0, WebKit27.2; PHPUnit13.4.3 from the full PHP gate.
Existing locked Playwright1.64.1, Tailwind4.3.3, daisyUI5.7.50, Stimulus3.2.2 and
Turbo8.0.23 remain unchanged. No new migration or environment setting is needed.

Final Git checks: git diff --check passes. The twelve previously staged numbered
prompts remain excluded from the implementation commit with staged-diff SHA-256
`d7f37c445e44f29e12983531103fd1d80acbeab737b1c0aab86787c5e95429c1`.
Only this scoped implementation/test/documentation change is committed; no branch
push, remote publication, deployment or mutation of real user data occurred.

## Improvement prompt 06 — Catalogue detail previews (2026-10-29)

Selected `prompts/06-catalogue-detail-previews.md` and its nested prompt pack on
`m20-catalog-detail-previews`, starting from36248f3. Pack SHA256SUMS passes. The twelve
already staged numbered prompts remain separate. Fresh baseline in owned run
`browser-e71fdbfb959f24a325745f3e` confirms headings Item/Releases/Saved source files,
zero preview frames, no reader edit link and403 from unrestricted preview creation.
The historical review observation still applied; no working catalogue was used.

Implemented: preview-first component/block/layout details with explicit Load, exact
published release or editor-only saved draft revision, real examples/defaults,
immutable theme/query selection, transient schema/JSON properties and renderer
settings. Width/fit and320/560/800px outer height controls keep mobile/wide/tall content
bounded; strict current-frame/channel/schema resize validation remains capped4000px.
Source, downloads, exports, installation, publication and duplication remain reachable
in secondary disclosures, stacked below on mobile. The source pane reads the selected
archive/draft and escapes all text. Draft-only readers receive No published preview.
Missing examples use actual defaults; real invalid Twig reports its worker diagnostic.

CataloguePreviews reads integrity-checked immutable sources or captures an expected
saved revision. Its reader/session/CSRF endpoint accepts no source/manifest/theme code;
reader draft requests fail403 and stale draft captures412. It never saves renderer
presets, source or appearance. Existing editor creation still requires ROLE_EDITOR;
status/document/cancel require the job owner. Shared PreviewJobs/polling/cancellation,
finite dependency loader, isolated workers, fixed compiler and opaque iframe/CSP remain.
The assembler is unchanged. Cached successful body/CSS/JS reuse is owner/full-input
scoped, revision/release/example/theme specific, unexpired and reassembled with fresh
channel/nonce. Existing job/history/storage quotas apply. Extra source attributes are
rejected422 instead of an unhandled serializer500. Listing cards reserve16:9 regions,
mount no preview documents initially, and load only a requested cached, script-disabled
release document. No thumbnail-generation fleet, migration or dependency update.

Verification:

- Docker strict Composer/container,34 YAML and51 Twig lints; **166 PHPUnit tests /
  1791 assertions** pass (119.173s), including three new contract/permission/cache-key
  flows. Evidence: catalogue-preview-final-focused.log. The overall initial focused
  invocation later failed one new browser test callback; its PHP/lint gates passed.
- Final focused browser/visual review passes **15/15** (1.2m): eleven detail scenarios
  plus twelve visual comparisons in four cases. Four listing baselines deliberately
  updated and reviewed; all eight composer/theme source PNGs stay byte-identical to
  HEAD and were restored to the owned baseline directory before this normal comparison.
  Evidence: catalogue-preview-final-review.log.
- Immutable production passes **16/16** detail and existing preview cases (1.3m),
  schema sync, real fonts and unchanged read-only AssetMapper manifest. Command:
  `bash tests/AppearanceProduction/run.sh tests/Browser/catalogue_preview.spec.js tests/Browser/preview.spec.js`.
  Evidence: catalogue-preview-production-final.log; owned project
  appearance-20261029113540-23822 automatically cleaned.
- A real cold exact-release/theme preview followed by worker outage succeeds only for
  identical owner/inputs with a new channel; changed props and another owner fail
  through unavailable workers. Both workers restart in finally. First proof:
  catalogue-preview-cache-proof.log. The reusable guarded command is now
  `python3 tests/BrowserHarness/preview-cache.py --run-id <exact-owned-run-id>`.
- All twelve component/block/layout screenshots at390/1440 in light/dark were reviewed.
  Twelve browser and twelve production application-control axe reports have zero
  violations; color-contrast incompletes remain for manual assessment. Previews are
  excluded from these application-control audits. The added critical reader preview
  passes Chromium/Firefox/WebKit. No human screen-reader assessment is claimed.

Corrected verification failures: the first serializer-extra-field regression returned
500 and now422. Initial responsive checks found the mobile frame at849px and a missing
property-group role; the toolbar was shortened/reordered and the group labelled with
its role. The scroll region gained keyboard focus. A source-string CSP assertion was
corrected to inspect the decoded meta policy. Strict sandboxing blocks native form
submission before its submit handler; the demo fixture now uses local button actions
and separately proves a real submit attempt makes no application request. A Playwright
locator.evaluate callback mistakenly treated its element as the channel; corrected
without changing message assertions. The first production test assumed seeded theme
releases (15 pass/1 fail); it now creates/publishes its own theme and checks its actual
iframe data-theme. The first full browser suite had139 pass/4 reader workspace failures:
its old bare-URL assertion conflicts with intentionally pinned release/example queries.
All four reader cases now require the correct path plus exact shaped selection state;
editor destinations stay exact. No application change or retry-policy change was used
for those last test corrections.

Final full rerun passes **143/143 browser tests (9.3m)**:135 Chromium,4 Firefox,
4 WebKit, including all eleven new detail cases, three new critical-engine executions,
all four corrected reader workspace URL cases and twelve visual comparisons against
the four reviewed listing updates/eight preserved original baselines. No application
code changed after the successful166/1791 PHP gate. Evidence:
`var/browser-verification/catalogue-preview-full-verified.log`; passing invocation:
`browser-a6893a916562e176eb3ddd66/artifacts/invocation-160be80f/`.

The reusable cache proof also passes against that final run, with a unique cold props
value, exact release/theme and real workers. Same-owner reuse with a fresh channel
succeeds while both workers are stopped; changed props/another account fail specifically
with worker_unavailable. Both workers restart in finally. Command:
`python3 tests/BrowserHarness/preview-cache.py --run-id browser-a6893a916562e176eb3ddd66`.
Evidence: catalogue-preview-cache-final.log. Python compilation and git diff --check pass.

Actual final runtime: FrankenPHP1.13.4 / PHP8.5.12 / Caddy2.11.7, Symfony8.1.9,
Node24.22.1, Chromium156.0.8078.4, Firefox157.0, WebKit27.2; PHPUnit13.4.3 from the full
PHP gate. Current locks retain Playwright1.64.1, Tailwind4.3.3, daisyUI5.7.50,
Stimulus3.2.2 and Turbo8.0.23. No dependency locks, base-image pins, Docker topology,
operator environment settings or database schema were changed. The existing
security.yaml access rule now permits owner-authorized reader job lifecycle access;
unrestricted source-preview creation remains explicitly editor-only.

All three owned browser runs and both production fixtures are cleaned: zero containers
(including stopped), volumes, networks and private credential files. Browser CLEANED
receipts exist and exact owned worker image tags are removed. Reusable application/
browser/production build caches remain. Cleanup/version evidence:
catalogue-preview-final-cleanup.log; final resource audit:
catalogue-preview-resource-audit.json. No working catalogue data was mutated.
No push, remote publication or deployment occurred.

Exact final browser orchestration, under the verified run's lifecycle lock:

```python
r.compose('--profile', 'tools', 'build', 'browser')
r.test(['tests/Browser/catalogue_preview.spec.js', 'tests/Browser/visual.spec.js', '--project=chromium'])
# After updating the four reader workspace assertions, rebuild the test snapshot:
r.compose('--profile', 'tools', 'build', 'browser')
r.test([])
```

Initial backend gates were run by:
`python3 tests/BrowserHarness/run.py run --with-checks --keep -- tests/Browser/catalogue_preview.spec.js tests/Browser/release.spec.js tests/Browser/transfer.spec.js tests/Browser/critical.spec.js`.
Its browser callback failure is recorded above, superseded by the final passing full
run. Final version capture uses r.app('frankenphp','version'),
r.app('php','bin/console','--version') and a Docker Node process launching each bundled
engine; r.cleanup() runs in finally under the same verified lifecycle lock.

Remaining limits: preview loading requires JavaScript; there is no new automatic
thumbnail fleet, no full browser-process containment guarantee and no human
screen-reader/contrast certification. The existing sandbox keeps native forms/network/
parent access blocked; local demonstration buttons are tested separately. Color-contrast
incompletes require manual assessment. Package schemas, immutable release bytes,
source/composer editing ownership and personal appearance/presets remain independent.
Exact next step: review the completed prompt06 workflow or explicitly select another
numbered prompt; no further prompt is started automatically.

Changed files (39):

```text
agent-kit/STATUS.md
assets/controllers/catalogue_preview_controller.js
assets/controllers/preview_controller.js
assets/controllers/release_thumbnail_controller.js
config/packages/security.yaml
docs/styleguide/ARCHITECTURE.md
docs/styleguide/BROWSER_TESTS.md
docs/styleguide/CATALOGUE_PREVIEWS.md
docs/styleguide/CONTRACTS.md
docs/styleguide/MILESTONES.md
docs/styleguide/PRODUCT.md
docs/styleguide/SECURITY.md
src/Controller/CatalogueController.php
src/Controller/CataloguePreviewController.php
src/Controller/PreviewController.php
src/EventListener/ApiErrorListener.php
src/MessageHandler/RenderPreviewHandler.php
src/Rendering/CataloguePreviewInput.php
src/Rendering/CataloguePreviews.php
src/Rendering/PreviewJobs.php
src/Rendering/PreviewRenderer.php
src/Rendering/ReleaseThumbnails.php
src/Repository/CatalogueRepository.php
templates/catalogue/_preview.html.twig
templates/catalogue/_releases.html.twig
templates/catalogue/index.html.twig
templates/catalogue/show.html.twig
tests/AppearanceProduction/run.sh
tests/Browser/baselines/chromium/listing-1440-dark.png
tests/Browser/baselines/chromium/listing-1440-light.png
tests/Browser/baselines/chromium/listing-390-dark.png
tests/Browser/baselines/chromium/listing-390-light.png
tests/Browser/catalogue_preview.spec.js
tests/Browser/critical.spec.js
tests/Browser/release.spec.js
tests/Browser/transfer.spec.js
tests/Browser/workspace.spec.js
tests/BrowserHarness/preview-cache.py
tests/Functional/CataloguePreviewTest.php
```

Final Git checks: git diff --check passes; all twelve originally staged prompts retain
staged-diff SHA256 d7f37c445e44f29e12983531103fd1d80acbeab737b1c0aab86787c5e95429c1.
The scoped local implementation commit excludes them. No push is performed.


## 2026-10-29 — Improvement prompt 07: searchable composer references

Selected prompts/07-searchable-composer-reference-picker.md and its nested prompt pack;
README/PROMPT/SOURCE_REVIEW_POINT read and SHA256SUMS verified. Starting branch
feature/m21-searchable-composer-reference-picker, HEAD2022685. The twelve staged prompt
files are unrelated user input and remain outside this implementation commit. Their
staged-diff SHA256 remains
d7f37c445e44f29e12983531103fd1d80acbeab737b1c0aab86787c5e95429c1.

Current source confirmed the reported latest200 plus all favorite-item releases preload
and two client-side select lists. A verified disposable fixture adds82 valid archive-backed
items/491 releases/81 editor favorites, including250 versions of one item and an old
nonfavorite. Its archives pass package/schema/inventory validation; this load fixture does
not claim491 worker publications. Acceptance inserts and actually renders the old release,
and publishes its own two-version source through real workers.

ReferenceDiscovery now returns at most20 summaries from one recursive DBAL query. Text,
kind, exact version, per-actor favorites, self-reference, missing/transitive dependencies,
known graph depth/count/version/cycle compatibility all precede LIMIT. Stable sorting is
package name/C collation, descending numeric SemVer, release ID. Structural insertion and
replacement dry runs call the existing CompositionTree; apply/save still call the
authoritative CompositionWorkspace and complete source/archive/props validation. No
invented component-vs-block-vs-layout hierarchy was introduced. Session editors share
the catalogue under the existing voter; disabled accounts/readers are rejected. The
browser query explicitly rejects scoped MCP principals. MCP item/draft search has a
different contract and remains separate; no UI request uses MCP transport.

The composer initially embeds zero reference choices. The native search dialog shows
plain bounded title/kind/summary/name/version/favorite data, deliberate exact-version
selection and pinned previews. Cached thumbnails mount only on explicit request in the
existing script-disabled opaque iframe; there is no result metadata fanout or compilation
fleet. The selected-node inspector independently reads one authorized exact immutable
release and preserves its pin and props outside filters. Replacement preserves props by
default; replacing them with the chosen defaults requires an explicit checkbox. Invalid
props fail without replacing the tree. No prompt08 property-form redesign was started.

Search is debounced300ms with IME handling, AbortController and serial guards. Closing
during the exact-release read cancels pending insertion; a queued native close event
cannot clear a reopened dialog. Native modal keyboard order includes the appearance
recovery form, Escape restores the opener, and successful insertion focuses the tree node
after the shell's focus restoration. Search/page/preview requests preserve unsaved work,
saved revision and dirty state. A GET fallback browses saved-context references without
JavaScript; insertion retains the existing JavaScript requirement. Long100-character
package names wrap in results, preview links and tree rows. Usage: REFERENCE_PICKER.md.
Pattern source: https://www.w3.org/WAI/ARIA/apg/patterns/dialog-modal/.

Representative authenticated dev-profiler measurements (real requests; no speed claim):

| Surface | Before | After |
| --- | ---: | ---: |
| Composer HTML bytes |346743|96258|
| Preloaded reference choices |491|0|
| Actual embedded choices JSON bytes |107824|0|
| Composer SQL queries |100|14|
| Discovery JSON bytes / SQL / rows |not available|6617 /4 /20|

The old nonfavorite was absent from the initial preload and is present in the new first
query page. These are representative captures from the same retained load fixture, not
a timing benchmark: intervening acceptance flows added other catalogue/theme fixtures.
The functional test independently verifies adding all491 releases/81 favorites does not
grow initial composer HTML (allowance256 bytes). Final measurement evidence:
var/browser-verification/reference-picker-measure-final2.log; before evidence:
reference-picker-baseline2.log. The measurement helper requires verified owned DB/blob
sentinels and is neither a route nor a production command.

Completed backend gates: strict Composer validation, container/YAML/Twig lint, CSS/
AssetMapper and Docker builds, migrations and **169 PHPUnit tests /1857 assertions**.
Final backend run: reference-picker-final2.log. Three new functional cases cover heavy
favorites/old versions/summary bounds, graph conflicts/cycles/parent rejection, CSRF/query
bounds/stale revisions/readers/missing versions and real post-login account revocation
across query, exact read and mutation. Browser403 transport injection is labelled a
client error-state test; actual revoked authentication is covered by the PHP session test.

Corrected failures during verification: the standalone measurement helper initially
lacked Dotenv initialization/DEFAULT_URI; the new Symfony8 Choice constraint needed named
choices arguments. One assertion compared JSON slash escaping instead of decoded data.
The initial picker used Stimulus's reserved context property, preventing opening; renamed
searchContext. A real shell focus race after insertion was corrected by scheduled tree
focus. A helper confused closing animation visibility with the native dialog open state;
it now checks open. One scenario explicitly clears Favorites before choosing a known
nonfavorite. The real API revocation response is401, not an HTML login redirect. Repeating
a full suite on its retained _test database exposed earlier orphan/future-timestamp
fixtures in unrelated catalogue/workspace tests; resetting only this owned _test database
then rerunning the complete suite passed. No unrelated test framework or product behavior
was changed to hide those failures. Screenshot review caught and fixed long preview-link
clipping, with horizontal-overflow assertions added for dialog and inserted tree.

Exact final Docker orchestration, under the verified owned lifecycle lock:

```python
r = Run('browser-7d0701d88a183de0ba5ff0bf')
r.verify_resources()
r.compose('--profile','tools','build','app','browser')
r.compose('up','-d','--wait','app','preview-consumer')
r.app('php','bin/console','cache:clear')
r.app('php','bin/console','doctrine:database:drop','--env=test','--force','--if-exists')
r.check()
r.test(['tests/Browser/visual.spec.js','--project=chromium'], update_baselines=True)
# Restore all eight unrelated listing/theme source AND run baseline bytes before comparison.
r.test([])
```

Initial fresh automatic seed and checks were started with:
`python3 tests/BrowserHarness/run.py run --with-checks --keep -- tests/Browser/reference_picker.spec.js tests/Browser/composition.spec.js tests/Browser/favorites.spec.js tests/Browser/critical.spec.js`.
Its new session-revocation assertion failed as recorded above; the final clean full PHP
run supersedes that result. The final orchestration is retained in ignored
var/browser-verification/reference-picker-final.py and its final2.log. Four composer
baselines were deliberately regenerated and reviewed at390/1440 in both modes; all
eight unrelated baseline bytes match the original in both source and owned run.

Production command:
`bash tests/AppearanceProduction/run.sh tests/Browser/reference_picker.spec.js tests/Browser/composition.spec.js tests/Browser/compose_mcp.spec.js`.
It builds immutable APP_ENV=prod/debug0 images, verifies owned DB/blob sentinels, validates
schema, exercises actual workers, native browser flows and the SDK MCP smoke check,
then compares the compiled AssetMapper manifest and cleans its resources. The load-data
seed is maintenance-only, mounted read-only; no production test API or imported scripts
are added. The first production attempt had the Favorites test setup error (13/14 passed);
the second passed14/14. The final wrapping/overflow run also passed14/14, including
all four light/dark narrow/wide picker cases and the SDK smoke check (26 tools, real
previewVerified=true). Its four axe reports have zero violations and color-contrast
incompletes. Post-build new-family appearance checks passed, with the compiled
AssetMapper manifest read-only and unchanged. Final production evidence:
reference-picker-production-verified.log and
var/appearance-20261029135815-22052/{browser-results,mcp-sdk-smoke.json}.

Actual runtime captured in reference-picker-baseline-cleanup.log: FrankenPHP1.13.4,
PHP8.5.12, Caddy2.11.7, Symfony8.1.9, Node24.22.1, Chromium156.0.8078.4, Firefox157.0,
WebKit27.2. PHPUnit13.4.3 is recorded by the full suite. Existing locks retain
Playwright1.64.1, Tailwind4.3.3, daisyUI5.7.50, Stimulus3.2.2 and Turbo8.0.23.
No migration, dependency lock, image pin, Docker topology or operator setting changes.

The first complete browser invocation passed154/155: all147 Chromium and4 WebKit
flows, plus3 Firefox flows. The remaining Firefox catalogue audit observed Turbo's
transient html aria-busy=true after an explicit search resubmission; matching cards were
already present from automatic filtering, so they did not prove that submission had
finished. The existing pre-audit not-busy assertion could race the next lifecycle step.
The critical flow now waits for the exact turbo:submit-start/formSubmission and its
matching turbo:submit-end, then listing ready, before running unchanged axe rules. No
rule was disabled, DOM attribute removed or product behavior changed. All12 critical
flows across three engines passed after that test synchronization change.

The affected Firefox case also passed8 consecutive repetitions (57s). Exact follow-up
commands under the same verified lifecycle lock:

```python
r.compose('--profile','tools','build','browser')
r.test(['tests/Browser/critical.spec.js'])
r.test(['tests/Browser/critical.spec.js','--project=firefox',
        '--grep','critical: keyboard navigation','--repeat-each=8'])
r.test([])
```

Evidence: var/browser-verification/reference-picker-critical.log and the corresponding
owned-run invocation reports. The second full invocation passed154/155 with all
Firefox/WebKit cases passing, but an unrelated existing theme export/import round-trip
returned422 once. An initial three-repeat diagnostic passed2/3; a subsequent four-repeat
diagnostic passed4/4. Read-only re-export/revalidation of the exact failed theme returned
200 for both JSON and CSS. The initial standalone diagnostic's500 responses were caused
by echoing output before later KernelBrowser session starts; buffered diagnostics resolve
that tooling error. The original intermittent422 cause remains unproven. Temporary
assertion-message instrumentation was reverted; no theme implementation/test semantics
are changed by this prompt. See reference-picker-diagnostic-buffered.log and
reference-picker-theme-diagnostic.log.

A fresh final browser fixture was started with
`python3 tests/BrowserHarness/run.py run --keep` (no duplicate PHP gate; backend source
unchanged since the passing169-test run). It owns
browser-0413ce91afaaa49fe7eb7c77, with source/run copies of the reviewed four composer
baselines and unchanged eight other baselines. Evidence:
var/browser-verification/reference-picker-clean-browser.log. Final full-suite result
and resource audit follow.

Final fresh full browser result: **155/155 pass** (147 Chromium,4 Firefox,4 WebKit),
including all12 normal visual comparisons and all12 new reference-picker cases. Its four
picker application-control axe reports have zero violations, with color-contrast
incompletes retained. The four production picker reports also have zero violations.
Four composer baselines and four final-production picker screenshots were visually
reviewed at390/1440 light/dark, plus a narrow inserted100-character pin. The other eight
source AND final-run baseline files match their original bytes exactly.

Remaining limits: no human screen-reader or contrast certification; no-JavaScript
discovery browses saved context while insertion still needs the existing JS composer.
Offset pages are stable for an unchanged catalogue, not a concurrent-publication snapshot.
Metadata graph hints cannot replace archive/source/props validation on apply. The earlier
existing theme-import422 was not reproduced by four focused runs, read-only round-trip
or the final fresh full suite; its cause remains unproven and no theme fix is claimed.
The initial preload's separate theme choices remain outside this reference-discovery
change. Package schemas, immutable bytes, workers, app/preview separation, MCP scope
contracts, editing history/guard and renderer appearance remain intact.

No push, remote publication or deployment occurred. No working catalogue data was
mutated. Exact next step: review the completed prompt07 implementation or explicitly
select another numbered prompt; prompt08 is not started automatically.


All three owned browser runs and all three production fixtures are cleaned. The exact
resource audit reports zero containers (including stopped), volumes, networks, private
credential/fixture files and remaining owned worker image tags. Browser CLEANED receipts
exist; reusable app/browser/production build caches remain. Cleanup commands were
`python3 tests/BrowserHarness/run.py cleanup --run-id <exact-owned-id>`; production
cleanup ran in each fixture's trap and exact owned worker tags were removed separately.
Evidence: reference-picker-baseline-cleanup.log, reference-picker-second-cleanup.log,
reference-picker-final-cleanup.log, reference-picker-production-cleanup.json and
reference-picker-resource-audit.json under var/browser-verification.

Final Git diff/check and scoped local commit exclude the twelve staged prompt files;
their original fingerprint is retained. No schema, dependency or operator action remains.

Changed files (35):

```text
agent-kit/STATUS.md
assets/controllers/composition_controller.js
assets/controllers/reference_picker_controller.js
docs/styleguide/ARCHITECTURE.md
docs/styleguide/BROWSER_TESTS.md
docs/styleguide/CONTRACTS.md
docs/styleguide/DISCOVERY.md
docs/styleguide/FAVORITES.md
docs/styleguide/MILESTONES.md
docs/styleguide/PRODUCT.md
docs/styleguide/REFERENCE_PICKER.md
docs/styleguide/SECURITY.md
src/Command/BrowserSeedCommand.php
src/Composition/ReferenceDiscovery.php
src/Composition/ReferenceQuery.php
src/Composition/ReferenceSearchInput.php
src/Controller/CompositionController.php
src/Controller/ReferencePickerController.php
templates/composition/_reference_picker.html.twig
templates/composition/edit.html.twig
templates/composition/references.html.twig
tests/AppearanceProduction/run.sh
tests/Browser/baselines/chromium/composer-1440-dark.png
tests/Browser/baselines/chromium/composer-1440-light.png
tests/Browser/baselines/chromium/composer-390-dark.png
tests/Browser/baselines/chromium/composer-390-light.png
tests/Browser/composition.spec.js
tests/Browser/critical.spec.js
tests/Browser/favorites.spec.js
tests/Browser/helpers.js
tests/Browser/reference_picker.spec.js
tests/Fixtures/ReferenceCatalogue.php
tests/Functional/ReferenceDiscoveryTest.php
tests/ReferencePicker/measure.php
tests/ReferencePicker/seed.php
```
