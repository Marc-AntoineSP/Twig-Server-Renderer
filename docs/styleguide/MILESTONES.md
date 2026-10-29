# Implementation milestones

Run in order; later milestones depend on earlier contracts and tests. Each gate must be evidenced.

## M00 — Bootstrap and repository audit

### Build

Inventory the existing Symfony app, instructions, Docker, frontend and dirty worktree. Preserve working features. Resolve Symfony 8/PHP 8.5 compatibility and the FrankenPHP 1.13 Trixie tag. Adapt reference Docker/Compose/Caddy/Makefile; create a Symfony skeleton only if absent. Add PostgreSQL, AssetMapper, StimulusBundle, Twig, Doctrine, security, validation, console, UID and test dependencies. Add fixed Node tooling, locks and a first studio stylesheet. Build a home page and a health endpoint.

### Completion gate

A fresh clone boots using documented Docker commands. Verify actual PHP/FrankenPHP versions, required extensions, DB connection, migrations, Twig page, Tailwind/daisyUI styling and one Stimulus action. Run docker compose config, build, readiness and HTTP smoke checks. Record image digests and lockfiles. No local PHP/Node prerequisite.

## M01 — Catalogue and authentication

### Build

Implement private login/roles and interactive admin creation. Add CatalogueItem, Draft and Release metadata with migrations and source storage. Add searchable/paginated catalogue, detail/create/edit forms, draft save with optimistic revision checking, and example fixtures. Implement structural plus semantic manifest/props validation. Publishing may remain disabled until render/build validation exists.

### Completion gate

Create, edit, reload and find a component after container restart. Unauthorized edits fail; CSRF is enforced. Conflicting saves return 412 and preserve data. Validate duplicate names, unsafe paths, invalid props/defaults and immutable version uniqueness. PHPUnit covers actual persistence and permissions.

## M02 — Isolated rendering and compilation

### Build

Implement finite template loading, dependency graph validation, dedicated Twig sandbox in a bounded subprocess, and a separate fixed Node compiler. Add worker containers with internal-only endpoints, no app/database secrets and explicit caps. Build a preview document with embedded assets and sandboxed srcdoc. Use the included alert as the initial end-to-end fixture.

### Completion gate

Alert renders and dismisses inside the iframe. A new literal responsive utility appears after editing. Unknown/app template includes, PHP call paths, loops exceeding budgets and forbidden compiler imports fail cleanly. Browser tests prove no access to parent DOM/storage and validate message source/channel checks. Workers survive failed jobs.

## M03 — Live editor and renderer controls

### Build

Implement Twig/JS/props/example tabs, unsaved indicators, 300 ms preview debounce, pending/error/stale states and sequence handling. Add job polling and cancellation/obsolescence handling. Add viewport width, zoom, root text/font controls and reset. Persist source edits separately from transient previews. Syntax highlighting is optional after the full editing flow works.

### Completion gate

Type Twig and Stimulus changes and see current previews without reload. Invalid source shows useful location and leaves a visibly stale last-good preview. Slower old jobs cannot replace newer output. Reload restores saved source/preset. At 375/768/1280 widths responsive layout behaves correctly. Tab/keyboard access and iframe reset work.

## M04 — Theme laboratory and exportable tokens

### Build

Implement typed theme persistence and immutable theme releases, all semantic/content colours, geometry, per-theme typography, font registry and renderer presets. Start with system fonts, then bounded licensed WOFF2 assets if supported. Add two-preset comparison, canonical JSON/YAML import/export, generated CSS source and compiled CSS outputs.

### Completion gate

Change colours/fonts/text/radius and save/reload them. Two themes can be compared without altering studio styles. Export app.css plus fragment/typography/compiled CSS; reimport theme.json exactly. Generated CSS round-trips; inconsistent edited CSS is reported. No remote font/CDN dependency. Invalid CSS/token injection is rejected.

## M05 — Blocks and layout composition

### Build

Implement the bounded composition tree, node selection/reordering/nesting and per-reference props. Reference immutable releases and resolve nested dependencies. Compile safe Twig from the graph; distinguish composition/source ownership. Support component -> block -> layout authoring and an explicit duplicate-and-convert to source action.

### Completion gate

Compose several instances with distinct props, nest a block in a layout, reorder/remove nodes and persist. Preview/export preserves order and appearance. Detect cycles, excessive depth/nodes, incompatible duplicate versions and unknown references. Literal text containing Twig delimiters remains literal. Controls work with a keyboard.

## M06 — Imports, exports and registry

### Build

Implement immutable releases, source/resolved collection/static/theme exports, archive and per-file checksums, scoped read tokens and registry endpoints. Implement local ZIP/manifest/YAML/theme imports with validate/dry-run and transactional apply. Produce clear export instructions, licences and complete dependency closures. Use the schema and path rules consistently.

### Completion gate

Export a layout with dependencies and theme, import into a clean studio and compare normalized data and rendering. A static download runs with no studio requests. Test traversal/duplicate entries/symlinks/ZIP bombs/missing files/checksum mismatch and auth. Publication fails when validation/build fails. Source and compiled CSS are labelled correctly.

## M07 — Symfony consumer installer bundle

### Build

Build packages/catalogue-installer-bundle with the documented registry, require, install, theme, update, remove and doctor commands. Test via a local Composer path repository; do not pretend the package is already on Packagist. Resolve pinned dependencies, validate compatibility, stage/journal writes, integrate CSS/Stimulus, and maintain styleguide.lock.json.

### Completion gate

In a fresh compatible Symfony consumer, install the alert and a composed layout by command; Twig renders and Stimulus works. Tailwind includes required classes in production build. Reinstall is idempotent. Modified files and unowned collisions abort without partial writes. Interrupt/recover a staged install. Update/remove respect dependents and ownership; no network calls remain in installed UI.

## M08 — Production deployment and recovery

### Build

Build immutable production app/worker images with locked dependencies, compiled studio assets, least privilege, configured limits, private networks, health checks, secrets and HTTPS/proxy support. Add CI checks, migration/release procedure, cleanup quotas, backup/restore and operational documentation. Finish all acceptance scenarios.

### Completion gate

Deploy into a clean Docker environment and exercise create -> edit -> preview -> compose -> theme -> export -> install. Restart services without losing data. Restore DB/blobs into fresh storage and repeat export/install. Confirm no debug/profiler/default credentials/exposed DB or worker ports. Verify resource caps, graceful shutdown and secret redaction.

## M10 — Personal application appearance

This requested extension follows the completed font library/website themes work. Reuse
the existing theme editor, generator, fonts and persistence. Add explicit light/dark
families, per-user immutable revision selections, system-aware header controls and a
fixed Restore default control. Apply generated, validated runtime CSS to the shell;
preserve independent component previews. Extend create/save/duplicate/variant workflows
with temporary trials, exact rollback, Save & Apply and honest partial-failure recovery.

### Completion gate

Run all twelve checks in `codex-apply-themes-to-application.md`, including reordered
requests, two accounts, first-paint system mode, real fonts, keyboard/mobile controls,
private assets/CSRF and existing export/install round-trips. Prove new families and
variants work after production AssetMapper compilation with an unchanged read-only
manifest and no redeployment. Preserve original row hashes and record actual commands,
results, corrected failures and limitations in VERIFICATION.md and agent-kit/STATUS.md.
See [APPEARANCE.md](APPEARANCE.md) for the implemented workflow and storage contract.

## M11 — Theme detail component showcase

Extend the existing detail page with repository-owned Twig partials/macros for the useful
daisyUI 5.7.50 components. Reuse existing family variants, validated token CSS, fonts and
the isolated document assembler. Keep Apply/Edit/export and application appearance
independent. Provide responsive category/search navigation, real local interactions and
unique input/ARIA relationships without uploads, submissions or persistence writes.

### Completion gate

Verify all requested action, form, navigation, display, feedback, layout and mockup groups
with meaningful semantic variations. Check actual production CSS for every rendered
class, real local font loading, distinct palettes/both family modes, keyboard focus,
dialogs/drawers/toasts, responsive 375/768px layouts, preview isolation and unchanged user
preferences/data. Run the relevant existing PHPUnit/browser regressions and immutable
production build. Record omissions, corrected failures and actual results in
THEME_SHOWCASE.md, VERIFICATION.md and agent-kit/STATUS.md. No theme-model migration or
new generator is part of this milestone.

## M12 — Full-width inline theme reference

Make the trusted component catalogue the primary detail-page content, with a 300px
metadata/action sidebar and normal document scrolling. Extract/reuse Twig partials and
share the application Stimulus instance. Keep user/imported code in the existing sandbox.
Reuse validated token CSS and registered font bytes under a bounded showcase selector,
including namespaced font faces; never load a second global reset or change preferences.

### Completion gate

Verify all 65 panels, category search/anchors, keyboard and overlay focus, mobile details,
desktop/tablet/mobile widths, contrasting palettes/both modes, real local fonts and
application independence. Exercise sidebar Apply/Edit/Duplicate, every draft/release
output and private package action; preserve edit/create layouts and sandbox isolation.
Check compiled class coverage and new post-build themes in immutable production assets.
Run existing contracts, PHPUnit and relevant browser regressions; preserve original row
hashes and record actual checks/limitations in STATUS.md and VERIFICATION.md.

## M13 — Authenticated catalogue-composer MCP

Audit manual builder semantics and reuse shared tree commands, validation, immutable
references, draft persistence and sandboxed previews. Add the Symfony MCP bundle HTTP
endpoint with an explicit composer tool registry, scoped expiring/revocable credentials,
maintained OAuth/PKCE/discovery, optimistic concurrency, atomic batches/idempotency,
stable IDs and recoverable history. Keep manual unsaved working trees and live revision
notices; exclude source editing, publishing and unrelated administration.

### Completion gate

Actual SDK HTTP discovery/edit/preview/history smoke; auth/revocation/scope/isolation,
invalid tree/reference/cycle and atomic rollback/retry tests; browser vs MCP conflicts
and manual builder regression; Docker/migrations/production renderer. Document the
operation map, exact pinned libraries/config/startup commands and local Codex/ChatGPT
setup. Distinguish verified Codex/client connections from credential-dependent external
setup. Preserve original data and sandbox. See COMPOSE_MCP.md.

M13 completed2026-10-27:135PHPUnit/1138assertions, real official SDK + Codex0.164.0 HTTP
round trips, concurrent revision/retry checks, manual/MCP browser flows and3/3immutable
production acceptance flows. ChatGPT account linking remains credential-dependent setup,
not a claimed client verification. See VERIFICATION.md and STATUS.md for measured evidence.

## M14 — Profile and browser MCP setup

Add an authenticated personal profile linked from navigation. Edit display name/email and
password without changing roles or other accounts. Reuse composer credentials and the
maintained OAuth client manager for browser issuance/revocation and admin client setup.
Provide copy/download client configuration, actual browser MCP discovery/read-only checks,
and OAuth readiness feedback with accurate external-connection status. Protect sensitive
forms/one-time secrets, preserve existing catalogue/preferences and rendering isolation.

### Completion gate

Real profile validation/persistence/login continuity, CSRF/ownership/role checks, token
issuance/hash-only storage/revocation and HTTP MCP verification from the profile, OAuth
client registration/disable, responsive accessible flows and immutable production assets.
Run existing authentication/MCP/composer regressions; record measured checks and any
account-dependent client setup in STATUS.md and VERIFICATION.md.

M14 completed2026-10-27:141PHPUnit/1245assertions,3development and6immutable-production
browser flows, actual PAT/OAuth MCP checks from/profile and unchanged asset/content hashes.
External ChatGPT account linking remains explicitly outside local verification. See
PROFILE.md, VERIFICATION.md and STATUS.md.

## M15 — Personal catalogue favorites

Persist per-user stars on stable theme variant and component/block/layout IDs.
Integrate accessible asynchronous controls, server-filtered lists with preserved
queries/pagination, eligible composition references and scoped MCP search. Reuse
current authentication/CSRF, shared catalogue access, Twig/Stimulus/icon conventions,
theme appearance and rendering isolation. Preserve existing data and manual actions.

### Completion gate

Real persistence, isolation, repeated add/remove, CSRF, deletion/disabled access,
revision preservation, combined filters/pagination and MCP namespace restrictions.
Browser checks for keyboard/pending/failure, multiple sessions, theme modes, responsive
list/detail/pickers and Apply independence; existing authentication/manual composer
regressions. Verify new data with immutable production assets and record original
data hashes, changed files, migration and measured results. See FAVORITES.md.

M15 completed2026-10-28:146PHPUnit/1375assertions; all58development flows verified
across the full run and3focused reruns;7/7immutable-production browser flows/54.2s,
official SDK smoke, unchanged original row hashes and font registry. Migration applied
in development/test/isolated production; existing production was not redeployed.

## Improvement prompt 12 — Isolated acceptance and targeted regressions

Disposable, run-owned database/blob/runtime/workers; deterministic bounded fixtures;
authenticated target proof before browser mutations; exact teardown and retained-run
commands. Preserve Chromium coverage and add focused Firefox/WebKit keyboard and
accessibility checks, twelve reviewed visual baselines, and a manual verification list.

Completed 2026-10-28: fresh owned lifecycle builds, lints, **148 PHPUnit tests / 1382
assertions and 71 browser tests** pass. Nine axe reports have no violations; their
gradient/background-image contrast findings remain explicitly incomplete. Twelve visual
comparisons also pass in a second fresh environment; deliberate pixel regression,
wrong ownership proofs, failure/SIGTERM teardown and immutable production checks pass.
Human screen-reader checks remain unrun. Legacy consumer/restore topologies are guarded
before mutations and require a separate migration. See BROWSER_TESTS.md and STATUS.md.

## Improvement prompt 01 — Useful workspace homepage

Replace stale bootstrap readiness with permission-aware tasks, bounded Recently updated
draft/composition/theme summaries and compact personal item/theme-variant favorites.
Keep the existing shared-reader catalogue, editor destinations and independent empty
states; filter accessible targets and personal favorites before limiting results.

Completed 2026-10-28: Docker builds/lints, **151 PHPUnit tests / 1446 assertions**,
**79 browser tests** across the existing three-engine suite, twelve unchanged visual
comparisons, and eight additional empty-workspace role/width/mode checks pass. Sixteen
homepage axe scans have no violations; editor-button contrast incompletes remain for
manual assessment. No migrations, dependency/configuration changes or working data
mutations. See DEVELOPMENT.md, BROWSER_TESTS.md and STATUS.md for exact evidence/limits.

## Improvement prompt 02 — Compact navigation and reserved recovery

Use native mobile disclosures and inline desktop navigation, concise context, personal
account actions and visible mode control. Keep recovery in a safe reserved strip and
inside native modal content, with distinct trial/reset semantics and a real no-JS reset
fallback. Preserve source isolation, the theme showcase/sidebar and existing permissions.

Completed 2026-10-28: **154 PHPUnit tests / 1502 assertions**, **89 browser tests** across
three engines, twelve deliberately updated/reviewed visual comparisons and **6 immutable
production flows** pass. A strengthened white-on-white saved/trial recovery fixture also
passes independently. Measured mobile header is 111px total (56px controls, 54px recovery,
border) at both 320px and 390px, without horizontal overflow. Six shell axe scans have
no violations; the long-email contrast incomplete remains for manual review. No migration
or new dependency; the fixed CSRF-protected guest reset route adds one access rule.
See APPEARANCE.md, BROWSER_TESTS.md and STATUS.md for commands, evidence and remaining checks.

## Improvement prompt 03 — Theme action clarity

Separate read-only draft showcase browsing, personal application appearance and the
existing published-theme component editor workflow. Identify draft revisions and exact
published versions/modes, retain POST/CSRF/role boundaries and support draft-only states.
Completed 2026-10-28: Docker builds/lints, **157 PHPUnit tests / 1568 assertions**,
**96 browser tests** across three engines, twelve visual comparisons (four reviewed
updated theme-detail baselines) and **20 immutable-production flows** pass. Seven new
browser flows prove exact release selection after a concurrent publication, draft-only
variant browsing, independent appearance/preset state and reader denial. Six new axe
reports have no violations; contrast incompletes and human screen-reader checks remain
for manual review. No migration, dependency update or operator setting; rebuilt images
now include Symfony's translated messages. The action map is in THEME_SHOWCASE.md;
commands, cleanup audit and limitations are in STATUS.md.


## Improvement prompt 04 — Editing protection and saved history

Shared navigation/unload protection and authoritative saved snapshots across source,
composition and theme editors. Expose the existing authorized composition history with
bounded differences and restore as a new revision. Keep current conflict/reference
validation and MCP receipts; do not add another history store or full Undo/Redo.
Completed 2026-10-29: Docker builds/lints, **160 PHPUnit tests / 1676 assertions**,
**110 browser tests** across Chromium/Firefox/WebKit, twelve visual comparisons
(four reviewed updated composer baselines) and **14 final immutable-production flows**
pass. Earlier production coverage also passes the official MCP SDK smoke and shared
manual/MCP restore operations. Four history panel axe scans have no violations;
contrast incompletes and human screen-reader checks remain for manual review.
All seven owned browser runs and both production fixtures are cleaned. No migration,
dependency update or operator setting is required. See EDITING.md for usage and
STATUS.md for exact commands, resolved failures, runtime versions and remaining limits.


## Improvement prompt 05 — Discovery and favorites feedback

Shared GET filter layout and progressive server-rendered search for catalogue, themes
and fonts. Keep URL/history state, exact combined filtering before pagination, latest
responses, confirmed favorites-only totals/pages and an explicit idempotent Undo.
Preserve personal authorization, licensed lazy font specimens and composer-picker scope.
Completed 2026-10-29: Docker build/lint, **163 PHPUnit tests /1739 assertions**,
**129 browser tests** across three engines, twelve visual comparisons (four reviewed
catalogue updates) and **31 final immutable-production discovery/appearance flows** pass.
Nineteen new browser flows cover URLs/Back/Forward, combined filters, IME, response
ordering, no-JavaScript forms, last-page/empty reconciliation and explicit Undo.
A real opening-fade modal focus defect found during verification is fixed and proven by
an old-shell failure plus nine passing cross-engine repetitions. Twelve final discovery
axe scans have zero violations; gradient contrast and human screen-reader checks remain
for manual assessment. No migration, dependency update or operator setting is needed.
Usage is in DISCOVERY.md/FAVORITES.md; STATUS.md records commands, failures and cleanup.


## Improvement prompt 06 — Catalogue detail previews

Make exact published releases and saved draft revisions visually inspectable from the
detail page, with reader-safe explicit loading, actual examples/themes/properties,
bounded viewports and secondary source/download/release disclosures. Reuse the existing
owner-only job lifecycle, workers and opaque iframe; cache complete inputs per owner.
Keep listing thumbnails consistent and explicitly requested, with honest empty states.
Completed 2026-10-29: Docker build/lint, **166 PHPUnit tests /1791 assertions**,
**143 browser tests** across three engines, twelve visual comparisons (four reviewed
listing updates) and **16 immutable-production scenarios** pass. Twelve responsive
component/block/layout screenshots were reviewed. Twelve browser and twelve production
application-control axe reports have zero violations; contrast incompletes and human
screen-reader checks remain for manual review. A real worker-outage test proves private
cache reuse, changed-input misses and owner isolation. All three browser runs and both
production fixtures are cleaned. No migration, dependency update or operator setting is
needed. See CATALOGUE_PREVIEWS.md for usage and STATUS.md for commands, corrected failures,
versions, changed files and remaining limitations.


## Improvement prompt 07 — Searchable composer reference picker

Replace latest200 plus unbounded favorite-release preloading with authorized, eligible
immutable-release search. Keep exact versions, current pins, unsaved props/revisions,
parent/cycle validation and manual/MCP composition semantics.
Completed 2026-10-29: Docker build/lint, **169 PHPUnit tests /1857 assertions**,
**155 browser tests** (147 Chromium,4 Firefox,4 WebKit), twelve visual comparisons
(four reviewed composer updates) and **14 immutable-production scenarios** pass.
Twelve new picker cases cover old nonfavorites, many favorites/versions, deliberate
replacement/defaults, stale responses/IME, failures, cancellation, native keyboard/focus,
no-JavaScript browsing and100-character identifiers at390/1440 in both modes. Four final
browser and four production picker axe audits have zero violations; color-contrast
incompletes and human screen-reader checks remain manual. The existing cross-engine
critical search audit now waits for its exact Turbo submission;12 critical cases and8
consecutive Firefox repetitions pass.

Representative composer payload changes346743 to96258 bytes,491 to0 embedded reference
choices and100 to14 SQL queries. Search returns20 bounded summaries in6617 bytes/4 SQL
queries. These are captured requests, not an elapsed-time claim. Three browser runs and
three production fixtures are cleaned. No migration, dependency or operator setting
change is required. REFERENCE_PICKER.md documents usage; STATUS.md records actual
commands, corrected failures, the unrelated intermittent theme-import check, versions,
changed files and remaining limits. No later prompt starts automatically.
