# Architecture and decisions

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

Draft revision increments on each successful save using compare-and-swap. Undo/history
can be added later; a revision number is concurrency control, not an audit-log claim.
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

Theme detail renders repository-owned Twig partials/macros into a complete srcdoc
showcase, reusing ThemeCssGenerator::applicationCss, DocumentAssembler and its registered
font embedding. Static component utilities and the small Stimulus bundle are built in
the existing Docker asset pipeline and copied into production; the request only supplies
validated root tokens and embedded font bytes. It creates no catalogue item, revision,
preference or preview job and needs no worker compilation. The same opaque iframe/CSP
boundary contains all dialogs, drawers, toasts and example actions. Variant links use
existing family relationships and never change the studio's saved appearance. See
THEME_SHOWCASE.md for inventory and acceptance evidence.

The shared preview assembler sets its base URL to `about:srcdoc`, under a matching
about-only base CSP directive. This makes native fragment links local for both catalogue
and theme previews, including script-disabled mode, without rewriting user markup or
granting same-origin access. The preview document policy digest is versioned when this
behaviour changes. A long-running development preview consumer must restart to load the
new PHP assembler; existing drafts/releases remain untouched.
