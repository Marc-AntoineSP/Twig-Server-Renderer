# Version-one contracts

Schemas in agent-kit/schemas are normative structural contracts. This document adds
cross-file and operational rules that JSON Schema cannot express. Unknown format versions
are rejected with a clear upgrade message. JSON is canonical; YAML is an import/export
presentation of the same values. YAML-specific types or object instantiation are forbidden.

## Component/block/layout package

manifest.json identifies name namespace/slug, exact SemVer, kind, logical entry, props
schema path, defaults, compatibility, exact dependencies, controllers, safelist and files.
Every payload file appears exactly once with SHA-256 and bytes. The manifest itself is
excluded from its own file list. The registry/collection records the archive checksum.
No nonmanifest files may hide outside the inventory. Paths are case-sensitive portable
relative POSIX paths; apply the stronger validation in SECURITY.md.

Source paths are consumer destinations. An entry templates/catalogue/awui/alert/component.html.twig
has Twig logical name catalogue/awui/alert/component.html.twig. Package metadata under
meta/ is kept in the installer's metadata directory, not copied over host files.
All package-owned templates/controllers/assets must be beneath their namespace/slug
prefix. The final file name may vary, but packages cannot claim another package's tree.
Controller identifiers must agree with the registered consumer path and be unique across
the dependency closure. Asset files must match both extension and expected content type.

Version-one dependency resolution uses exact versions. Reject a graph requiring two
different versions of the same name because source paths/controller IDs would collide.
On publish, validate every declared dependency and every include/reference. Templates
may include only existing owned templates or templates in their dependency closure.
The importer never discovers dependencies by fetching arbitrary include strings.

Props schemas use JSON Schema draft 2020-12 but only this bounded subset in v1: object,
string, number, integer, boolean, array, null; properties, required, additionalProperties,
enum, const, default, min/max, min/maxLength, min/maxItems, items and local $defs/$ref.
Disable remote refs. Limit depth/size and reject unknown features until supported.
Defaults and every example must validate. The UI supports common scalar fields and arrays;
for nested unsupported UI shapes, offer validated raw JSON rather than inventing controls.
HTML text is escaped. Validate URL props at the boundary; HTML escaping alone does not
make javascript: links safe. Example packages do not require raw HTML props.

## Composition

composition.json contains schemaVersion and root. Nodes are container (allowed tag,
literal classes, children), reference (name, exact version, props), or text (value).
References may target components, blocks or layouts; enforce depth/cycle/size limits
across both the tree and the dependency graph. Class lists are literal and recorded for
the CSS scan. Props are JSON values, never executable Twig expressions.
Compile text and JSON props into Twig using a real serializer/AST strategy; do not concatenate
unescaped user text into Twig source. A text value containing '{{' must stay literal text.
Generated templates are part of the published package; composition.json is the editable
source. In source mode, templates are authoritative and the visual builder is disabled.

## Theme

theme.json contains a named semantic palette, geometry, typography and font definitions.
Colours initially use validated #RRGGBB hex for exact portable round-trips. Add OKLCH
later only with a parser and stable serialization. This is a deliberate v1 input-format
choice, not a daisyUI limitation.
All 20 semantic colour tokens are required, including content counterparts. Geometry
is bounded numeric data converted to rem/px by the generator, not interpolated free-form CSS.
System font families use selected IDs. Uploaded WOFF2 fonts require package-local files,
integrity hashes, a family label and redistributable licence metadata.

The theme generator emits:

- app.css: complete Tailwind source entry for a fresh project, including plugin setup,
  explicit source paths, the theme plugin body and typography bridge.
- theme.fragment.css: only the named daisyUI theme configuration.
- typography.css: per-theme variables + the shared Tailwind font bridge and defaults.
- preview.css: compiled standalone output for shipped examples, not all possible classes.
- theme.json: authoritative reimportable data, plus font assets/licences if used.

theme.fragment.css assumes the host already loads Tailwind and the daisyUI plugin.
Never blindly import a second complete Tailwind root into an existing app.css.
The installer adds a managed fragment import and explicit source registration, once.
When arbitrary existing CSS is imported, explain unsupported syntax. Exact round-trip
is guaranteed for theme.json and this application's own generated CSS format only.
Generated CSS carries a format marker and a base64 canonical JSON comment for round-trip;
the importer also compares parsed known CSS declarations with regenerated output. If they
disagree after manual CSS edits, report the conflict; never silently ignore edited CSS.

### Font-library extension of schemaVersion 1

Existing system-font documents and their generated CSS remain valid. Typography can
also select `codeFontId`, per-role integer weights and normal/italic styles. Local font
definitions contain a family/category, source/version, licence path/hash and a bounded
`faces` array. Each face records its content-addressed `fonts/<sha256>.woff2` path,
SHA-256, bytes, real weight range, style, subset, Unicode range and axis bounds/defaults.
Empty axes remain JSON objects through queued previews. Only active local families are
declared in a theme; unused system definitions remain valid for older documents.

Each declared local asset/licence must exist beneath the owning theme's
`assets/styles/catalogue/<namespace>/<slug>/` tree and match its metadata and package
inventory. ZIP files hold raw WOFF2; JSONB source maps and JSON/YAML package envelopes use
an explicit canonical `base64:` encoding for WOFF2 only. Inventories/checksums always
describe the decoded file bytes. Binary assets require a theme package. WOFF2 is bounded
to 1 MiB per file and 16 MiB declared decompressed size; a package allows 8 MiB of fonts
in addition to the existing 2 MiB text profile. Archive/envelope inputs and consumer
closure payloads remain bounded to 10 MiB. Large envelopes must use ZIP when base64
expansion exceeds that limit. Traversal, symlink, collision and ownership rules still apply.

Theme bundles use the shared portable package validator and importer. All selected font
files, licences, generated source CSS and compiled preview CSS are inventoried. The
optional `geometry.highContrastBorders` boolean generates explicit content/error field
borders; its absence preserves earlier generated CSS. Optional bounded provenance records
the collection, managed status, exact reference surface/mode, dated source hashes, font
substitutions and per-value `observed`, `adapted` or `derived` classifications. Public
visibility of a reference does not grant redistribution rights for its branded assets.

## HTTP API (to implement)

The application appearance extension does not change schemaVersion 1 package/theme
documents or their inventories. Family assignment and personal selection are application
database metadata. A family references distinct saved Theme variants; an applied
AppearanceSnapshot identifies a specific theme revision and its validated generated CSS.
Package releases, renderer presets and user preferences retain independent identities.

`GET /api/v1/appearance` reads the current user's saved selection (or the validated guest
mode). CSRF-protected `POST .../prepare` validates/captures a family or mode candidate;
`POST .../commit` requires its session/account/client-bound token, sequence and expected
preference revision. CSS must load before the client commits. `POST .../trial` validates
unsaved tokens for an editor-owned expiring stylesheet and never commits a preference.
The default family supports anonymous login appearance; catalogue families require a
signed-in reader. Saved CSS routes authorize before ETag/cache handling; trial CSS also
requires its owner and is not cacheable. See APPEARANCE.md for bounds and lifecycle.

`POST /appearance/reset` is the CSRF-protected SSR fallback for the fixed built-in
default/system selection. It accepts no theme/owner/redirect selection; guests can
only reset their existing mode cookie. Normal JS reset retains prepare/load/commit.

Use a versioned /api/v1 prefix. Session-authenticated edit endpoints require CSRF;
registry download endpoints accept scoped Bearer tokens. JSON errors contain code,
message, fieldErrors and requestId; never a stack trace in production.

| Method/path | Request/response and important behaviour |
| --- | --- |
| POST /api/v1/items | kind/name/title; 201 with id and draft revision |
| PUT /api/v1/items/{id}/draft | sourceMap/metadata + If-Match revision; 200, 412 if stale |
| POST /api/v1/preview-jobs | item/draft payload, props, preset, sequence; 202 id |
| GET /api/v1/preview-jobs/{id} | queued/running/succeeded/failed/expired + sequence |
| GET /api/v1/preview-jobs/{id}/document | owner-authorized JSON {html, sequence}; no-store |
| POST /api/v1/items/{id}/releases | version + expected draft revision; 201 or validation error |
| POST /api/v1/imports | multipart ZIP/JSON/YAML, mode=validate/apply; structured plan/result |
| POST /api/v1/exports | release/preset/format; job or ready download reference |
| GET /api/v1/registry/{namespace}/{slug} | kind + immutable versions + hashes |
| GET /api/v1/registry/{namespace}/{slug}/{version}/manifest | manifest + ETag |
| GET /api/v1/registry/{namespace}/{slug}/{version}/archive | immutable ZIP bytes |

Public/private registry is a deployment choice; default private. Authorize every request
before cache/ETag handling. Never mark token-protected registry downloads public cacheable.
Use 400 malformed, 401 unauthenticated, 403 forbidden, 404 missing, 409 version/dependency
conflict, 412 revision mismatch, 413 too large, 422 semantic invalidity, 429 limits.

## Commands (to implement)

Studio commands:

    styleguide:user:create --admin
    styleguide:import FILE --dry-run
    styleguide:import FILE
    styleguide:export NAME@VERSION --format=source --output=PATH
    styleguide:theme:import FILE --dry-run
    styleguide:theme:export NAME@VERSION --output=PATH
    styleguide:gc --dry-run

Consumer commands supplied by the installer bundle:

    styleguide:registry:add NAME URL
    styleguide:require NAME@VERSION --registry=NAME [--dry-run]
    styleguide:install FILE [--dry-run]
    styleguide:theme:install NAME@VERSION --registry=NAME [--dry-run]
    styleguide:update NAME@VERSION [--dry-run]
    styleguide:remove NAME [--dry-run]
    styleguide:doctor

Registry credentials come from a per-user private auth file or environment variables,
not committed project configuration. Reject plain HTTP outside explicitly configured
local development. Restrict redirects to configured origins and never forward tokens
to a new origin. Stream downloads under limits and verify their archive hash.

Installation steps: resolve closure, verify compatibility and hashes, validate paths,
compare destinations with styleguide.lock.json, show plan, stage changes, take rollback
copies, write files atomically, update lockfile last, clean temporary data.
Use a project installation lock to serialize concurrent installs. Journal mutations so
crash recovery can roll back a partial operation; a series of atomic renames is not a
whole-project atomic transaction. Never report success after a partial write.
Unmodified previously owned files may update. A changed file produces a conflict and
aborts the transaction; provide a staged candidate/diff for manual resolution. There is
no silent --force overwrite in v1. Remove only unchanged files with no remaining dependents.
On first install, an existing byte-identical but unowned file is still a collision unless
explicit adoption is implemented and recorded.

styleguide.lock.json records format version, registry alias, exact package versions,
archive hash, dependencies and installed per-file hashes. Never put tokens in it.
Cross-package file ownership is forbidden. Metadata files are stored below
.styleguide/packages/{namespace}/{slug}/{version}/; renderable sources use their declared
destinations. The installer records any managed CSS/import configuration edits separately.

The consumer must already have compatible Twig, AssetMapper, StimulusBundle, Tailwind
and daisyUI. doctor detects missing setup and prints concrete commands/configuration.
Do not install random npm dependencies or run arbitrary scripts automatically.
For an already configured compatible consumer, require must be one command after registry
setup and must produce a usable include/controller without extra manual copy operations.

## Composer MCP and stable node identity

Composition schemaVersion1 nodes accept optional unique `n_` +32lowercasehex IDs.
Older documents remain valid. Legacy studio reads assign deterministic composition/path
IDs; only an explicit save persists them. New nodes and entire duplicated subtrees receive
fresh IDs; moves/property updates retain IDs. The 64KiB/500node/20level bounds still apply.
Portable validators in this repository share the same optional-ID schema; older external
validators must support that extension to import newly saved composition metadata.

The sole MCP surface is the explicit compose_ allowlist documented in COMPOSE_MCP.md.
Existing-draft writes require expectedRevision and operationId. Canonical identical retries
replay a durable actor/operation/target receipt after current authorization; mismatched
payloads conflict. Transactions cover validation, row-locked save, history and receipt.
Batch aliases are local `$name` references to earlier adds/duplicates, max50 commands;
one failed command aborts the whole batch. Restore creates a new revision, never rewinds
version numbers. Manual working trees remain unsaved until their normal explicit Save.

Previews capture an exact requested current saved revision and are actor-owned. Later
saves cannot change their captured source payload. Status/HTML/URL identify previewRevision
separately from the current draft revision, with visualInspectionAvailable=false.
