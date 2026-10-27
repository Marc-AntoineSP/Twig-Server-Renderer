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
