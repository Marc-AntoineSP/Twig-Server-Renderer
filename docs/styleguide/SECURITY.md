# Required execution boundaries

This server edits executable template and browser code. Authenticated editors and imported
packages are not equivalent to trusted application source. The following controls are part
of the implementation, not optional deployment polish.

## Twig

Create a dedicated Twig environment with a finite ArrayLoader or equivalent constrained
loader. Enable strict variables and HTML autoescape. Pass only scalars/arrays with depth
and size limits. No Doctrine entities, Request, User, app global, filesystem loader or
studio extensions. Includes resolve only within the locked dependency closure.
Use the sandbox API available in the installed patched Twig version: current Twig 3.29+
documents Sandbox with a strict SecurityPolicy; older versions use globally enabled
SandboxExtension on a separate environment. Verify the installed API in M02 instead of
copying an obsolete snippet. Allow only the tags/filters/tests necessary for fixtures.
Reject arbitrary raw, source, include targets, constant, dynamic callables and app functions.
Never change the main application's Twig sandbox state around a render.

Sandbox policy does not impose CPU/memory limits or sanitize literal HTML/JS. Run rendering
in a subprocess with a wall-clock timeout, memory limit, output-byte cap and process cleanup.
Suggested initial bounds: 2 seconds CPU/wall budget adjusted for cold start, 128 MiB PHP
memory, 1 MiB HTML, 20 levels and 500 nodes in compositions. Treat these as configurable
starting values and measure them, not performance guarantees. Kill the child/process group
on timeout and retain a healthy supervisor. Do not rely on PHP max_execution_time alone.

## Browser

Preview lives in iframe srcdoc with sandbox="allow-scripts" only. No same-origin, forms,
popups, downloads or top-navigation permissions. The studio must never insert imported
HTML into its own DOM. Isolate CSS so preview tokens cannot restyle the editor.
Use a restrictive preview CSP: deny default network resources; allow only required embedded
CSS/JS/fonts/images. No unsafe-eval. All JS imports must be resolved at build time.
Meta CSP cannot implement every header directive; do not claim frame-ancestors works there.
Opaque sandbox prevents access to studio DOM/storage, but arbitrary browser code and
navigation are not a universal network-egress sandbox. Never send secrets or real user
data into it. Do not promise that an arbitrary JS loop cannot freeze a browser frame.

postMessage from an opaque iframe has origin "null"; that is not an authentication check.
Check event.source against the current iframe.contentWindow, a per-preview random channel
ID and a strict message schema. Accept only bounded size/status/error data; no commands,
HTML injection, navigation, storage access or fetch instructions from the iframe.
Show reset/disable-JS controls for broken previews.

## Compiler

Node compiler runs a fixed installed Tailwind/daisyUI/esbuild toolchain. Source is input,
not executable configuration. No uploaded tailwind.config.js, postcss plugins, @plugin,
@config, package.json scripts or npm install requests are accepted.
Generate CSS directives from validated tokens. Imported app.css is accepted only through
the limited parser for this application's own generated format, not evaluated as arbitrary
Tailwind configuration. Imported JS is parsed/bundled for the browser, never imported into
Node to inspect exports. Resolve imports through an explicit allowlist and package root.
Reject filesystem/network imports and unresolved dynamic imports.
Do not expose filesystem paths or secrets through compiler diagnostics.
Run as non-root, with a temporary per-job directory, no external network egress at runtime,
no Docker socket, and wall-time/memory/output caps. The internal worker network has no DB
route. Build-time package downloads are a separate operation from handling user content.

## Uploads, releases and installs

- Reject absolute paths, drive prefixes, backslashes, dot/dot-dot segments, empty segments,
  NULs, symlinks, hardlinks, duplicate entries and case-insensitive destination collisions.
- After joining a path, verify containment under the staging root. Never use raw ZIP
  extract-all. Count inflated bytes/files while streaming to defeat ZIP bombs.
- Starting caps: 10 MiB compressed, 50 MiB inflated, 500 files and 100:1 ratio. Font/assets
  may need a documented larger profile, never an unbounded bypass.
- Accept explicit source/asset extensions only; PHP, executable binaries and scripts for
  the host are rejected. SVG needs a dedicated sanitization policy or is rejected in v1.
- Verify per-file and archive SHA-256. Checksums provide integrity relative to a trusted
  registry; they are not author identity signatures. Use TLS and scoped read tokens.
- Block cyclic dependencies, conflicting required versions and controller collisions.
- YAML parsing must reject objects/custom tags, duplicate keys and unreasonable aliases.
- Always stage and validate before changing catalogue state or consumer files.
- The consumer installer never executes imported shell, PHP, Composer or npm hooks.
- Installed browser JS becomes trusted by the receiving application; preview isolation
  does not follow it there. Show package origin and changed code in dry-run/review output.

## Studio and registry

Use form login, password hashing, CSRF on mutations and login, login throttling, secure
host-only session cookies and role checks. Registration is not public. Seed an admin using
an interactive CLI command; no default password. Registry tokens are hashed, scoped,
revocable and do not appear in URLs, export files, logs or frontend JS.
Preview jobs and draft/export endpoints enforce owner/access checks. Production has no
profiler, debug exception page or public worker ports. Do not allow arbitrary remote import
URLs in v1; this avoids creating a generic SSRF fetcher.

## Font and design-reference maintenance

Online acquisition is restricted to the versioned maintenance manifests and never runs
from a public request or from the internal renderer/compiler networks. Font tarballs are
integrity-checked, streamed through a bounded archive reader and treated as data; their
scripts, plugins and configuration are never executed. Real font metadata and required
glyphs are validated before atomic registration. A failed source leaves the active family
and existing content-addressed assets intact. Font bytes and licences are not PHP sources.

The maintenance HTTPS client allows explicit hosts only, pins a resolved public address
to each connection, validates every redirect, rejects credentials/non-HTTPS/private,
link-local and metadata destinations, and bounds time, retries and response sizes. Website
collection caches responses, uses modest concurrency and honours robots restrictions.
An unavailable robots file is recorded; 4xx absence is distinguished from a resource
access denial. Site 401/403 responses and access challenges are never treated as evidence.
TLS verification remains enabled, including for currently unavailable Twitch sources.

Website scripts, when needed, run only inside the isolated maintenance Chromium process.
Its HTTP document/CSS/script requests use the same source/address checks. Fonts, images,
API requests and other undeclared subrequests are blocked; service workers and WebSockets
are disabled. The research container mounts only tooling, source evidence and acquisition
cache/staging directories, with no studio secrets or database volume. It does not grant
internet access to preview workers. The normal preview iframe still has no same-origin
permission and permits font bytes only through data URLs.

## Applying themes to the application

Only the shared validated token generator can produce application CSS. Imported CSS,
component JavaScript, scraped website styles and user HTML never enter the studio DOM.
Selectors are fixed templates scoped by snapshot hashes, values use the existing bounded
theme schema, and local fonts must resolve to registered content-addressed bytes. Runtime
stylesheets include content hashes and browser integrity attributes; private assets are
authorized before cache/ETag checks. No dynamic asset is written into the compiled public
manifest. Worker networking, finite Twig loaders and iframe privileges are unchanged.

Personal preference writes and trials require CSRF. Prepared selections are bound to the
session, account, tab client and sequence; optimistic preference revisions prevent stale
writes. Account preferences override validated mode-only guest cookies; no shared browser
storage cache can override another account. Trials use owner-only, expiring/no-store CSS
and cannot update Theme or AppearancePreference records. Personalized HTML is private/
no-store; only the built-in system-font default CSS is available without a login.
See APPEARANCE.md for request, stylesheet, tab-slot and trial-history limits.

The server-rendered appearance recovery form additionally accepts a CSRF-protected
POST at `/appearance/reset`. This fixed action prepares/persists only the built-in
default family in system mode, with the current preference revision. It accepts no
owner, theme or return-URL selection. Anonymous reset only writes the validated guest
mode cookie. JavaScript enhancement retains the existing tab-bound prepare/commit API.
Recovery controls cloned into application-owned native dialogs remain trusted shell UI;
no user HTML/JavaScript is moved into the studio or outside its opaque preview iframe.

The theme-detail component reference renders only repository-owned Twig partials and
bundled controllers directly in the application. User-created/imported Twig, CSS and
JavaScript remain in the isolated renderer/frame; this trusted presentation does not
relax that sandbox. Its stylesheet uses the existing bounded validator, registered
embedded font bytes, a fixed showcase root/snapshot selector and namespaced font faces.
It has no global reset or root selectors. The content-hashed CSS endpoint requires the
reader role before returning private CSS, and its link carries a content integrity hash.
Personalized HTML keeps its existing private/no-store policy. Viewing never writes a
preference, theme or catalogue record. Demo actions are scoped to the showcase root,
with no upload, persistence or submission handler; sidebar forms keep their CSRF checks.
Native dialogs/drawers stay within that root's DOM/theming, make the background inert,
and close with focus restoration. Toasts expire and dismiss. IDs/input groups are unique.

Preview documents have a trusted `about:srcdoc` base, keeping fragment-only links inside
the current opaque document instead of resolving against an authenticated studio route.
`base-uri about:` permits that non-network base scheme; HTTP(S) base injection is still
blocked. Sandbox flags, nonce scripts, data-only font/image sources, denied connections,
frames and forms are unchanged. Native fragment navigation also works with scripts
disabled; user templates and href attributes are not rewritten.

## Composer MCP boundary

/mcp/compose authenticates every request using an active enabled editor's scoped PAT or
League-verified OAuth bearer; cookies/session IDs never substitute for a credential.
CompositionVoter checks the affected catalogue and every transitive immutable reference.
The pre-existing shared editor catalogue remains shared; actor isolation applies to
credentials, MCP sessions, receipts and preview results. File sessions are keyed by the
current actor/credential, and handlers retain no caller/selection in shared mutable state.

PATs are256bit random secrets, stored as SHA256 hashes with1–90day expiry/revocation,
explicit read/create/edit/preview scopes and catalogue patterns. OAuth is provided by
maintained League plus MCP OAuth bundles, with predefined clients only; no automatic
URL-based client discovery/SSRF surface is enabled. Exact callbacks, required S256 for all
clients, canonical resource checks on authorization/token, signed resource binding,
expiration/revocation and current user/client status checks apply. Subjects use User IDs.
The canonical issuer and RSA key pair pin the local authorization server; private keys
remain mode0600 in the persistent private volume, absent from renderer/image layers.
Consent is CSRF-protected, actor/query-bound, short-lived and single-use.

Bodies are bounded to256KiB. Exact Host/Origin checks and HTTPS outside local development
remain mandatory. Production Compose defaults MCP off; a flag also disables OAuth and
discovery. Configure exact public hosts and trusted proxies before enabling remote access.
MCP/OAuth debug profiles are disabled. Bearers, authorization codes and client secrets
must never be pasted into tool data/config/logs. Replayed results require current access,
including references in older result snapshots. Row locks plus optimistic revisions
prevent lost writes; advisory locks serialize duplicate operation keys before first save.

The manual composer history API requires the normal editor role and composition voter.
Snapshot listing is bounded; selected differences and restore revalidate historical
reference closures under current permissions. Restore requires expectedRevision and
normal API CSRF, and uses the same workspace transaction as MCP. No inaccessible reference
is accepted to make recovery succeed. Responses are private/no-store; the UI renders
abbreviated differences with textContent, never historical HTML/JavaScript. Browser
editing guards keep temporary snapshots only in memory and remove their listeners on
disconnect. No browser persistence, authentication bypass or second history store is added.

Only typed bounded composition commands are allowed. Imported text remains escaped data,
never PHP/Twig instructions or a tool-policy override. Source conversion, publishing,
registry/package writes and theme/font administration remain outside MCP. Existing finite
Twig loading, isolated worker networks/resource limits and opaque iframe privileges are
unchanged. Preview snapshots are owner-only, and MCP retrieval rechecks captured reference
permissions. No screenshot or visual-inspection capability is implied by returned HTML.

## Profile and browser credential setup

Profile routes require a studio cookie session and CSRF for writes; a composer bearer
cannot authenticate these administration pages. The actor always comes from the session,
with no user ID/roles/enabled fields accepted for profile mutation. Email changes require
the current password, canonical uniqueness and a current profile fingerprint; password
changes require confirmation. Sensitive checks are throttled to5per5minutes per account.
Current-session identity is preserved; old sessions and that user's MCP/OAuth grants cease
to authorize after a password change. Existing other-user grants remain unchanged.

Personal tokens reuse hash-only ComposeTokens storage, bounded expiry/scopes/catalogue
patterns and owner-only listing/revocation. Web issuance limits an account to20active
credentials. Shared OAuth client creation/enable/disable requires ROLE_ADMIN and CSRF;
creation uses the maintained manager/hasher and exact documented HTTPS ChatGPT callbacks,
authorization-code/refresh grants, composer scopes and S256. Secrets are returned once,
absent from subsequent lists/config downloads, URLs, browser storage and session storage.

All /profile responses have private/no-store headers and profiler capture is disabled,
including error responses. The trusted controller scrubs secret DOM values/attributes
before cache/navigation. Downloads reference a client environment variable and contain
no secret. Browser probes omit studio cookies, send the supplied bearer only to the fixed
same-origin MCP endpoint, follow no redirects and time out. They call discovery and a
read-only tool; they cannot test arbitrary URLs or mutate catalogue data. After Host is
validated, its actual same origin is permitted by the MCP Origin guard; untrusted hosts
and cross-origin requests remain rejected. Server-wide enablement/HTTPS/proxy settings
stay deployment-controlled. No external account connection is claimed by a local probe.

## Acceptance test ownership

Browser fixture creation and cleanup require a private run receipt, matching PostgreSQL
database-comment ownership and a matching blob-root sentinel. Initialization refuses
nonempty stores. A fresh HMAC challenge verifies the actual HTTP target before browser
tests and login; target names and environment flags grant no mutation authority. The
proof route returns 404 in ordinary installations. Production fixture mounts support
only this proof and do not bypass studio authentication or enable fixture CLI commands.

The standalone test project has isolated caches/sessions/OAuth keys and internal
networks, null mail transport, no host ports and no writable checkout mount. Teardown
checks exact project labels, mounts and resource names. Shared tool images are retained;
only exact project-owned worker image tags are removed after label checks. Logs and
text artifacts redact credentials, and one-time fields are scrubbed before automatic
failure screenshots. Traces that could capture credentials stay disabled. See
BROWSER_TESTS.md for retained-run handling, accessibility scope and manual limits.


Discovery frames and full-page listings share role checks, actor-scoped favorite queries
and private/no-store responses. Query owner/user IDs never select another account. The
bounded echoed discovery receipt is an ordering hint, not a credential or authorization
input. Active chips use escaped Twig values, and Undo messages use textContent. Undo
calls the existing CSRF-protected explicit PUT, including current target/access checks;
no removed target is reconstructed and no client card is inserted to simulate success.


Catalogue readers may create bounded previews only from an accessible immutable release.
Draft capture requires an editor and exact saved revision. The dedicated input DTO accepts
no imported source/manifest/theme code, and creation requires session CSRF. Job status,
document and cancellation authorize the owner before storage/cache access. Output reuse
queries the same owner and full input hash, accepts only successful unexpired artifacts,
and assembles a new nonce/channel. No shared public private-output cache is introduced.
Reader preview visits/settings never save presets, source or appearance. Listing thumbnail
GETs are reader-protected/private and read only an existing bounded artifact. Detail and
thumbnail documents remain opaque sandboxed frames with unchanged CSP/worker limits;
thumbnail scripts are disabled. Strict current-frame/channel/schema resize validation and
a bounded outer scroll region prevent page growth from untrusted resize messages.


Browser reference discovery requires an enabled User editor/admin session and target
COMPOSE_READ before SQL. Session editors share the catalogue under the existing voter;
MCP ComposeIdentity is explicitly rejected by this browser service, so namespace-scoped
MCP access cannot be widened by a global release query. Owner-scoped favorites join in
the same SQL statement; no post-limit client authorization or metadata fanout is used.
The finite tree/DTO/query bounds and parameterized SQL preserve literal wildcard searches.
Published graph metadata filters known cycles/version conflicts before paging, while
application/save revalidate every reference, archive, dependency and property.

Search responses contain bounded plain metadata, rendered with textContent, and pinned
application URLs. Exact-release failures expose no inaccessible metadata. Current pins
and local props survive failures. Search and exact reads are private/no-store; POST search
requires session CSRF. Preview HTML/JS never enters the studio DOM. Existing opaque
iframe/CSP/worker isolation remains unchanged. Load fixtures and measurement tools require
a test database or verified disposable database/blob ownership; no production fixture
command or unauthenticated test API is added.
