# Architecture and decisions

## Personal favorites

The Favorites service resolves the authenticated account and stores stable Theme or
CatalogueItem references in personal_favorite. Unique owner/target indexes, an
exclusive-target CHECK and cascading foreign keys provide idempotency and cleanup.
Theme package cards resolve to the typed variant identity. Request-local batched
status maps avoid per-card favorite queries. SQL filtering precedes list pagination;
MCP retains namespace authorization before accessible pagination. Twig stars reuse
AssetMapper Stimulus with explicit CSRF-protected PUT/DELETE and read reconciliation.
Composer filtering only narrows immutable reference choices; shared commands still
validate actual edits. Appearance, draft/release data and the sandbox stay separate.
See FAVORITES.md for routes, migration, UI behavior and verification.

## Application shape

A Symfony modular monolith owns accounts, catalogue state, editing, themes, releases,
registry and exports. Two narrowly scoped worker services perform untrusted Twig
rendering and fixed-toolchain asset compilation. Neither worker mounts the application
source, its secrets, database credentials or Docker socket. Both are internal only.

| Boundary | Responsibility | Inputs | Outputs |
| --- | --- | --- | --- |
| Studio app | SSR UI, auth, validation, persistence, orchestration | Authenticated edits | Pages, job IDs, packages |
| Render worker | Twig with a dedicated sandbox and finite loader | Validated source map + bounded props | HTML or structured diagnostic |
| Build worker | Tailwind + fixed daisyUI, browser JS bundle | Source text + typed theme + declared controllers | CSS and JS or diagnostic |
| Browser iframe | Execute isolated preview behaviour | Complete preview document | Bounded resize/status messages |
| Installer bundle | Validate, plan and write consumer files | Locked release/collection | Local sources and lockfile |

Use ordinary Symfony services grouped by capability: Catalogue, Rendering, Theme,
Composition, Packaging, Registry. Keep controllers thin. Shared DTO/value objects are
useful where they enforce a contract; do not introduce an interface for every class.

## Data ownership

PostgreSQL is authoritative for mutable drafts and queryable catalogue metadata.
JSONB is acceptable here for source maps, typed theme documents and composition trees.
Large binary assets and immutable release archives live in an application-owned blob
directory outside public/. This is a local-volume implementation behind a small storage
interface; S3 is an optional later adapter, not a version-one infrastructure dependency.

| Record | Important fields / invariant |
| --- | --- |
| User | id, email unique, password hash, roles, enabled |
| CatalogueItem | id ULID, namespace/slug unique, kind, title, tags, description, editMode |
| Draft | itemId unique, revision int, manifestData/sourceMap JSONB, updatedBy/At |
| Release | itemId + SemVer unique, manifest JSONB, archive hash/key, createdAt; immutable |
| Theme | id, namespace/slug unique, draft revision, typed theme JSONB |
| ThemeRelease | themeId + version unique; immutable canonical document and files |
| RendererPreset | name, theme release(s), viewport, fonts, text/root size; JSONB |
| PreviewJob | id, owner, requestSequence, inputHash, status, expiry, resultKey/error |
| ApiToken | hashed token, owner, scopes, expiry/revocation; plaintext shown once |

Draft revision increments on each successful save using compare-and-swap. Composer
saves additionally capture typed snapshots in the existing CompositionRevision store.
CompositionHistory supplies bounded summaries/differences and restore through the same
authorized CompositionWorkspace path for browser and MCP; restoration creates a new head.
Source/theme revision numbers remain concurrency control, and no full Undo/Redo is added.
Store dependency references in release manifests; optionally index them in a small
ReleaseDependency table for reverse lookups. Release deletion is blocked while referenced.
Do not add workspace/tenant concepts until there is an actual requirement.

## Save, preview, publish

Save validates shape and source sizes, then writes a new draft revision transactionally.
It may save syntactically broken code for repair, with validity state; publishing cannot.
Preview reads the unsaved browser payload plus declared immutable dependencies. It does
not publish or modify another item. It validates props, resolves the graph, renders Twig,
builds CSS/JS and returns a complete document.
Publish requires valid source, schema/defaults/examples, resolved acyclic dependencies,
passing build, a new SemVer and complete package checksums. Write the blob to a temporary
key, atomically install it, then commit the release record; garbage-collect orphan blobs
after crashes. Never serve a release record before its blob exists.

## Preview pipeline

1. Studio validates CSRF, role, quotas and request sequence; creates a job with a TTL.
2. Resolve exact dependency versions and build a finite template map. Reject cycles,
   unknown templates, escaping paths, missing assets and duplicate controller identifiers.
3. Pass source map and arrays/scalars to the render worker. The worker launches a fresh
   bounded PHP subprocess for each render; its HTTP supervisor survives render failures.
4. Send source text, literal safelist and validated theme to the build worker. It generates
   its own Tailwind entry and Stimulus bootstrap. Imported config/plugins are never run.
5. Scan all Twig/HTML/JS and manifest safelist strings. Include dynamically edited source
   using explicit @source paths; never assume database content is discovered by Tailwind.
6. Assemble an HTML document with CSS, bundled JS and bounded embedded assets. The iframe
   uses srcdoc and sandbox="allow-scripts"; it never gets allow-same-origin.
7. Return job state/result only to its owner. Browser replaces srcdoc only for the latest
   sequence. Remove temporary directories and expired jobs/artifacts.

The srcdoc design deliberately avoids an authenticated preview route executing user JS.
All preview imports are bundled. Embed resources as data URLs or a carefully escaped
nonce-based payload; test closing script/style tags and Unicode during assembly.
If later requirements need a real origin, introduce a separate preview origin without
studio cookies before granting additional iframe permissions.

A polling endpoint is sufficient initially. Use a Symfony Messenger Doctrine queue for
preview orchestration if async handling is needed; the app-side consumer calls internal
workers and has DB access. Workers themselves do not. RabbitMQ is not mandatory here.
Use bounded worker concurrency and per-user pending-job limits. Dropping a browser request
does not cancel server work automatically; mark obsolete jobs and stop/discard their work.

## Build and cache semantics

Cache by canonical source bytes + sorted exact dependency hashes + theme tokens + props
when scanning rendered output + toolchain lock digest + renderer policy version.
Keep rendered HTML (props-sensitive), compiled CSS and compiled JS caches separate where
possible. A palette value change can reuse utilities; a new literal class cannot.
Cache entries contain no personal application data. Enforce bounded retention and disk quota.
The toolchain is installed during image build with lockfiles. No npm install per edit.

AssetMapper serves the studio's fixed JS and compiled CSS. Build the studio CSS into
assets/styles/app.css from assets/styles/studio.input.css, then asset-map:compile in the
production image. Dynamic preview artifacts are outside AssetMapper's deployment manifest.
Do not write new controllers into the deployed studio assets/ and expect production
AssetMapper to notice them; they belong only to preview bundles until exported.

## Portable templates and controllers

Use ordinary includes such as catalogue/awui/alert/component.html.twig. Both the renderer's
finite loader and the consumer's templates/ directory use that same logical path.
Templates cannot call app-specific path(), asset(), render(), services or PHP functions.
Host URLs are explicit validated props supplied by the receiving app. Preview uses sample
values only. Preserve HTML escaping; plain-text props are not implicitly safe HTML.

Controller path assets/controllers/catalogue/awui/alert_controller.js maps to identifier
catalogue--awui--alert in the Symfony Stimulus bridge. The preview bootstrap explicitly
registers the same identifier. Import @hotwired/stimulus only plus declared local modules
within the package closure. Use native DOM/Stimulus for behaviour; responsive layout uses CSS.
Controller connect/disconnect must clean up timers and listeners. Respect reduced motion.

## Theme model

A complete semantic palette is data. A design-system preset selects one or more named
themes plus typography. Generate daisyUI CSS plugin configuration and a separate typography
bridge from that data. Font identity, assets and licences travel with the package.
Initially use system fonts so previews and exports are immediately portable without a CDN.
Per-theme --sg-font-body/heading are ordinary CSS variables; Tailwind @theme inline maps
font-sans and font-heading to them. A global @theme declaration alone cannot select fonts
independently for each data-theme value.

The font-library extension retains this theme model. An application-owned, file-backed
registry separates family metadata from immutable WOFF2/licence blobs in the catalogue
volume. Acquisition stages pinned Fontsource packages and verifies Google Fonts family
and licence metadata; it runs only in Docker maintenance tooling. Ordinary theme JSONB
documents select bounded local face metadata and body/heading/code roles, so existing
system-font documents need no migration. Font bytes never enter the renderer/compiler
worker filesystem or grant it network access. The app embeds effective preview families
as data URLs; portable packages carry raw font files, relative CSS URLs and licences.

Website presets are managed entries in the existing Theme/ThemeRelease model. Provenance
is canonical theme data, including observed, adapted and derived values. Updates use
version/revision checks and preserve user copies. The gallery and shared showcase reuse
the existing renderer and immutable release selection; no second theme engine is added.

Personal application appearance adds explicit ThemeFamily light/dark relationships,
shared immutable AppearanceSnapshot records and one AppearancePreference per user. It
reuses Theme documents, their revision checks and the shared token/font CSS generator;
the appearance snapshot is separate from a published package release. A mode toggle uses
the selected family's captured variants, including historical applied revisions. Existing
unrelated themes become single-variant families; only explicit managed source provenance
is paired during migration. Theme documents and renderer presets are not rewritten.

Runtime CSS is generated from validated tokens into snapshot records and served through
authenticated content-addressed routes. Static utilities remain in the compiled asset
manifest, with a dark variant driven by root data-color-mode. The initial Twig document
includes both candidate mode styles and an early local bootstrap. One appearance
controller preloads CSS with integrity checks, sequences preference commits and owns editor
trials/rollback. Temporary trial styles are owner-scoped and bounded; they never update a
Theme or user preference. Full behavior, cache/auth rules and verification commands are
in APPEARANCE.md. User Twig/JavaScript continues to run only in the isolated renderer/frame.

## Distribution choice

Use one Composer installer bundle and a versioned HTTP catalogue API, not one Composer
package per component. This makes installation copy editable files and enables conflict
detection and offline ZIP import. Composer handles the installer itself; styleguide:require
handles components. A future Flex recipe may automate bootstrap integration, but v1 must
work through documented configuration and commands without a public Flex repository.

## Theme detail component reference

Theme detail renders repository-owned Twig partials/macros directly in the studio's
existing main element. Its full-width grid has a 300px information/action sidebar and a
flexible catalogue with normal document scrolling. A native mobile disclosure keeps
metadata short. Edit/create and other routes retain their original main-width block.

ThemeComponentShowcase shares the existing validated ThemeCssGenerator token logic and
ThemeFontAssets registered-byte embedding, under a fixed showcase selector and snapshot
hash. Font-face families are also namespaced, because face definitions have global scope.
A reader-protected, integrity-checked, content-hashed CSS route supplies only tokens,
role typography and local font bytes: no document resets, imports or global root rules.
The application utility CSS covers every repository-owned example. An ESM bundle reuses
the portable tabs/dialog controllers and registers them once with the application's
Stimulus instance. Root-scoped actions cannot intercept sidebar forms. Native modal
dialogs and drawers inherit their owning theme in the top layer; drawers trap/restore
focus and toasts expire or dismiss. No preview job, preference, theme or component is
written by viewing. New themes work after production asset compilation.

User-created/imported templates and JavaScript continue to use the isolated worker and
opaque iframe in catalogue/theme editors. Trusted application examples are the only
inline exception. Variant links retain existing family relationships and never change
the studio's saved appearance. See THEME_SHOWCASE.md for inventory and verification.

The shared preview assembler sets its base URL to `about:srcdoc`, under a matching
about-only base CSP directive. This makes native fragment links local for both catalogue
and theme previews, including script-disabled mode, without rewriting user markup or
granting same-origin access. The preview document policy digest is versioned when this
behaviour changes. A long-running development preview consumer must restart to load the
new PHP assembler; existing drafts/releases remain untouched.

## Catalogue composition MCP

CompositionTree now owns typed tree mutations formerly implemented only in Stimulus.
CompositionWorkspace shares reference/props validation, compilation, permissions, draft
saving and before/after recovery history between the browser and MCP. Browser command
requests transform an unsaved tree; its existing Save commits explicitly. Each MCP
mutation commits immediately, and a bounded batch commits once. Stable optional node IDs
extend the current composition contract; legacy reads assign IDs without writing data.

The existing app hosts one explicit 26-tool Symfony MCP server at /mcp/compose using the
official SDK's Streamable HTTP, negotiation, schemas and tool results. A small SDK-handler
adapter preserves JSON object/list distinction (especially empty props) before delegating
to SDK validation. Durable actor/operation/target receipts and draft row locks protect
retries and concurrent browser/AI edits. All adapters resolve identity per request.

Scoped expiring PATs serve local clients. Maintained League/Omouren OAuth libraries serve
predefined authorization-code/S256 clients with discovery/resource binding and normal
studio login plus request-bound consent. OAuth subjects are stable User IDs. Preview
bindings capture saved revisions and reuse the existing queued renderer/compiler/font
pipeline and opaque iframe. No AI provider or new runtime/screenshot service is added.
Full operation map, configuration, measured checks and account-dependent ChatGPT steps:
COMPOSE_MCP.md. No publishing, source authoring or administration tools are exposed.

## Browser acceptance resources

The shared shell uses native disclosure navigation/account/appearance controls, with
one lifecycle-scoped Stimulus enhancement for Escape, dismissal and Turbo cleanup.
Recovery occupies document flow and uses independent safe styling. Native modal dialogs
receive the same fixed recovery form inside their scrollable content, preserving the
browser's top-layer/inert boundary. A small SSR reset action reuses AppearanceManager
for the built-in default when JavaScript is unavailable; normal requests retain the
existing prepare/load/commit transaction. No appearance storage or preview boundary changes.

The standalone Compose browser topology builds self-contained app/runner images and
allocates unique database, blob and runtime volumes plus internal worker networks for
each invocation. It mounts no writable working checkout. Queue consumers use the same
owned database and blobs as the app; renderer/compiler keep their existing isolation.
An empty-store initialization binds a private run receipt to a database comment and
blob sentinel. BrowserRunGuard verifies both before account/seed commands and an HMAC
health challenge verifies the actual browser target before tests/login. This adds no
application migration, per-item privacy model or production authentication bypass.
See BROWSER_TESTS.md for lifecycle, deterministic fixtures, artifact policy and gates.

## Personal profile and browser MCP setup

/profile extends the existing cookie-authenticated studio with account details/password
forms, personal composer-token lifecycle, client configuration and read-only MCP checks.
AccountProfile reuses the existing User/hasher, locks profile updates, checks a fingerprint
and updates only permitted personal fields. Password/email identity checks keep the
current session coherent; password changes revoke that user's MCP/OAuth grants. A nullable
display name is the only schema addition. Existing roles, User IDs and catalogue links stay.

ComposeConnections adapts existing ComposeTokens and League ClientManager; it does not
create another token/authorization server. Personal token ownership is checked even for
admins. Only administrators manage shared predefined ChatGPT clients. Application-owned
Twig renders forms and metadata; one scoped Stimulus controller copies one-time secrets
and performs actual cookie-free same-origin MCP initialize/discovery/capabilities calls.
It also checks OAuth discovery consistency. No draft is changed by these diagnostics,
and external client account linking remains explicitly unverified. See PROFILE.md.


Discovery shares a small Twig layout, a focused Stimulus controller and ListingResponse
for catalogue/theme/font SSR. The same authorized result set supplies full visits and
Turbo Frames; filtering precedes pagination. Abort signals and bounded request receipts
reject obsolete results. Confirmed favorite events trigger complete result reconciliation
and explicit PUT Undo through the existing Favorites API. No new store or search engine
is added; see DISCOVERY.md. Composer release discovery is described below.


Catalogue detail now captures an exact release/archive or editor-only saved draft revision
through CataloguePreviews and reuses PreviewJobs, its workers and owner-scoped lifecycle.
Reader input cannot supply source or save presets. Successful unexpired body/CSS/JS can
be reused for the same actor and complete input hash, then assembled with a fresh frame
channel/nonce. Explicit loading and a bounded scroll region keep detail rendering finite;
listing cards fetch only a requested cached, script-disabled release document. Arbitrary
catalogue source remains confined to the opaque frame. See CATALOGUE_PREVIEWS.md.


## Searchable immutable composer references

ReferenceDiscovery serves bounded release summaries to the native search dialog and
a server-rendered GET fallback. A single recursive PostgreSQL query applies kind,
text, version, owner-scoped favorites, self-reference and known dependency graph
compatibility before LIMIT. CompositionTree supplies the structural insertion/replacement
dry run; CompositionWorkspace remains authoritative for application and save. Initial
composer HTML contains no reference catalogue. Only the selected exact release gets
a separate authorized schema/defaults read; result rows never fetch metadata in a loop.

The browser query deliberately requires an enabled session editor. MCP's voter-scoped
item/draft search has a different contract and remains separate. The picker never calls
MCP transport. Native modal lifecycle, request cancellation and response receipts preserve
unsaved work and focus. Preview links pin release IDs; thumbnails reuse the existing
explicit, cached, script-disabled iframe. See REFERENCE_PICKER.md.


## Composition property controls

`assets/property_fields.js` extracts the source preview's schema interpretation into a
shared control description/renderer for source, catalogue and composition previews. It
describes the existing bounded subset; PropsValidator remains authoritative. The focused
instance-props controller edits only the canonical selected node.props, with memory-only
invalid raw text/drafts/errors keyed by stable node ID and exact reference identity.
CompositionController retains commands, saves, debounce/epochs and sandboxed jobs.
Server errors use node IDs so repeated references remain distinguishable. Reference
replacement is atomic: keep all current values if valid, otherwise retain the old node
until an explicit correction or default reset. See INSTANCE_PROPERTIES.md.


The theme editor's font-pairing workspace proposes the existing nine role fields without
mutating its draft until explicit transfer. FontPairing selects installed real faces and
sample Unicode subsets through the existing registry/assembler; TypographySample is
trusted shared renderer markup. The same Contrast service supplies saved ratios and
300 ms draft diagnostics, with bounded editor/CSRF endpoints and no persistence. Draft
results, saved baselines, stale responses and invalid opaque-hex values are explicit.
See FONT_PAIRING_AND_CONTRAST.md for usage, guidance scope and fallback limits.


## Catalogue read measurements (improvement prompt 10)

Catalogue ID pagination now uses a bounded bulk hydration with explicit ID-order
restoration. Existing card/favorite batching remains shared; theme gallery family
metadata is one scalar projection after pagination. MCP keeps authorization before
accessible slicing, then enriches only the selected page with scalar draft fields and
ten releases per item. Visibility-sensitive candidate traversal is retained.
Initial appearance HTML contains its current family; the existing private endpoint
loads the other choices when opened. Unpaired themes use a transient read fallback.
Explicit writers and the bounded/restartable styleguide:themes:backfill-families command
perform attachment; GET lookups never flush missing-family repair. No schema, index,
cache or package format changes are introduced. See CATALOGUE_READ_PERFORMANCE.md for
actual five-run before/after data, plans, deployment order and isolated reproduction.
