# Catalogue composer MCP integration

Implementation audit (2026-10-27). The existing manual builder is authoritative; MCP
adapts the same compiler, dependency resolution, draft writer and isolated preview jobs.
This is M13, following the completed inline theme reference.

## Existing operation map

| Compose action | Existing Stimulus handler / change | Validation and persistence |
| --- | --- | --- |
| Select / keyboard navigation | render/key; browser index path | Selection only; never saved |
| Add container / text / reference | addContainer/addText/addReference → add | Append to selected container, or insert after selected non-root leaf; selected root leaf cannot accept siblings |
| Edit tag/classes, literal text, JSON props | fields/referenceChanged | Browser working tree; JSON props must be objects; changing a pinned reference resets props to that release's defaults |
| Move up/down | up/down → move(±1) | Swap with adjacent sibling; root/boundary movements do nothing |
| Indent | indent | Move into immediately preceding sibling only if it is a container; append as last child |
| Outdent | outdent | Move immediately after its parent in the grandparent; root and direct children cannot outdent |
| Duplicate | duplicate | Deep-copy subtree immediately after original; root cannot duplicate |
| Remove | remove | Remove subtree and select its parent; root cannot remove |
| Save | save → PUT /api/v1/items/{id}/composition | Existing expectedRevision and CatalogueWriter optimistic locking; tree owns generated Twig/dependencies |
| Preview | preview → POST composition, then PreviewJobs | Validate/materialize unsaved tree, resolve exact immutable dependencies, run bounded render/build workers and opaque iframe |
| Renderer choices / save | themeChanged/settings/savePreset | Existing published theme and body/heading/code choices; separate actor-owned preset |
| Reset preview | reset | Clear local iframe, cancel owned job; no draft mutation |
| Duplicate and convert to source | separate protected POST | Different source editor; excluded from MCP, manual action preserved |

The browser keeps unsaved work until Save. Saved history and restore are available in
the manual builder; full in-memory Undo/Redo is separate follow-up work. Composition
nodes are container/reference/text; containers accept only children (no named slots).
References pin name + exact release version; instance properties do not change that
release. Only **block/layout** creation produces a composition. A component is created in
the source editor, so unrestricted component/source creation is outside this integration.
Existing roles share catalogue access; there is no per-user ownership/tenant model.
MCP adds capability/catalogue scopes without inventing ownership restrictions for the UI.

Existing limits: 64KiB JSON, 500 nodes, 20 levels, allowed HTML container tag list, bounded
literal classes, JSON-schema props, exact dependency closure/cycle checks. Imported text
is escaped by CompositionTemplate/TwigLiteral, never interpreted as Twig instructions.

## Shared editing path

`CompositionTree` owns typed structure commands; `CompositionWorkspace` owns reference
permissions, compilation, expected revisions, saving and history. Browser structural
buttons POST the **unsaved** document to `/api/v1/items/{id}/composition/commands` with
CSRF protection; this returns a validated working tree without writing a revision.
Scalar fields remain local until preview/Save validates them. The existing PUT composition
route calls the shared workspace and CatalogueWriter. MCP invokes the same commands and
save path immediately; one command or one batch saves exactly one revision. There is no
fake second Save tool. Renderer settings and source conversion keep their existing UI.

Nodes have optional schemaVersion1-compatible `id: n_<32 lowercase hex digits>` metadata.
Legacy reads assign deterministic IDs from the composition ID/path without rewriting data;
the next explicit save persists them. New/duplicated nodes receive random IDs, duplicates
receive fresh IDs throughout, and moves/prop edits retain IDs. Contracts and the portable
package validator accept older documents and reject duplicate/invalid IDs. IDs do not affect
rendered Twig. The existing 64KiB document bound includes IDs after normalization.

Container children use zero-based positions. `move_node.position` is measured **after**
removal. There are no named slots. Root update is supported, root move/duplicate/remove
is rejected. Indent requires the immediately preceding container sibling; outdent requires
a grandparent. UI boundary buttons retain their former no-op behaviour; explicit MCP
requests for impossible operations return `INVALID_PARENT` without writing.

All saves refresh/lock the authoritative draft, compare `expectedRevision` and use the
existing optimistic writer. Browser and AI stale writes fail. Successful MCP mutations
also require an 8–64 character `operationId`. Durable receipts are scoped to actor,
operation and target, with a canonical JSON payload hash and a transaction advisory lock.
An identical retry returns its original IDs/revision plus `replayed: true`; a different
payload with that key fails `IDEMPOTENCY_CONFLICT`. Authorization is checked before replay,
including the references in the old result when the current tree has since changed.
Receipts, snapshots and writes commit together. A failed batch writes nothing.

Batches contain 1–50 typed commands. Set `alias: "cards"` on an add/duplicate command,
then use `parentId: "$cards"` or `nodeId: "$cards"` in later commands in that same batch.
The response maps aliases to actual IDs. Aliases never become persisted node IDs.

History captures before/after trees and title/description for browser and MCP saves.
Restoring an available revision revalidates its dependencies and saves a **new** revision,
using current `expectedRevision` and `operationId`. History starts at the first new save
for old drafts; earlier unpublished revisions cannot be reconstructed. Receipts/history
are durable recovery data, with no automatic destructive retention policy in this release.
The manual Saved history panel and MCP share CompositionHistory/CompositionWorkspace;
the browser does not call the MCP HTTP endpoint. Bounded comparisons and current-reference
validation apply to both interfaces. See [EDITING.md](EDITING.md) for recovery, conflicts
and navigation protection.

The browser polls the saved revision every five seconds while visible. A notice offers
Load saved revision, preserves unsaved work, and explicitly confirms discarding it.
It never refreshes over local edits. The normal Save still rejects a stale working tree.

## Tool contract

Only the following 26 `compose_` tools are registered; no global attribute discovery.
Every tool has explicit input/output JSON schemas, annotations, structuredContent and a
short text result. Full contracts live in `src/Mcp/Tool/ComposeSchemas.php`; the reviewed
schema generator is maintenance tooling, never executed during requests.

| Tools (all prefixed `compose_`) | Purpose |
| --- | --- |
| `capabilities`, `search_catalogue`, `list_preview_themes` | Discover rules, paginated accessible items/releases and existing published preview themes |
| `get_reference`, `get`, `get_node` | Exact props schema/defaults, saved tree/revision/permissions, or bounded subtree with parent/position/actions |
| `create_draft` | Create a composition-owned block/layout; source components remain outside this editor |
| `add_container`, `add_text`, `add_reference` | Insert typed content or an exact immutable release at parent/position |
| `update_node`, `update_metadata` | Change supported instance fields or title/description |
| `move_node`, `indent_node`, `outdent_node`, `move_up`, `move_down` | Shared UI movement semantics |
| `duplicate_node`, `remove_node`, `apply_batch` | Recoverable subtree changes or one atomic command batch |
| `validate`, `get_history`, `restore_revision` | Exact-current-revision validation and recoverable saves |
| `render_preview`, `get_preview`, `cancel_preview` | Real asynchronous isolated rendering and owned result/status/cancel |

Search/history/theme pages allow limit1–50 and offset0–10,000. Node reads return at most
100 nodes with depth1–20 and an explicit truncation flag. Reference insertion without
props uses the exact selected release's defaults. Changing a reference must supply any
necessary new instance props; schemas/defaults are discoverable through get_reference.
No operation edits a referenced release or silently changes its version.

Success envelopes include compositionId/revision/composerUrl (null for discovery), summary,
warnings and operation-specific affected IDs/aliases/tree/job data. Errors use SDK tool
errors with `ok:false`, code/message/details; common codes include VALIDATION_FAILED,
REVISION_CONFLICT, INVALID_PARENT, REFERENCE_CYCLE, NOT_FOUND, FORBIDDEN and
IDEMPOTENCY_CONFLICT. Missing/inaccessible references are concealed as NOT_FOUND.
A schema-invalid request may be rejected directly by the SDK before a tool executes.

Preview requests capture exactly the requested **current saved revision**, optionally
selecting an existing ThemeRelease and width320–1920. The existing workers generate
Twig/Tailwind/Stimulus/assets/fonts under their existing time/resource/network limits.
Later saves do not change a queued/cached preview's captured revision. get_preview can
return HTML/diagnostics and an authenticated `/catalogue/composition-previews/{jobId}`
URL; the page uses the original opaque frame (`sandbox="allow-scripts"`, or an empty
sandbox when scripts=false) and existing
job/status endpoints. Only the preview owner may retrieve it. Current reference scopes
are rechecked for the captured tree before MCP results are returned.

`visualInspectionAvailable:false` is explicit: no screenshot worker exists. HTML plus a
URL does not mean the AI saw the rendered page. Preview enqueue/cancel are operations
with retry keys but do not modify a draft; render uses `revision`, not expectedRevision.

## Versions and authorization

Resolved/released versions, installed and locked: Symfony MCP bundle **0.13.0**, official
PHP MCP SDK **0.8.1**, Omouren MCP OAuth bundle **1.2**, League OAuth2 bundle **1.2.3**
(League server **9.4.1**). Matching installed README/configuration/source were inspected.
The OAuth glue supports Symfony MCP0.13; selecting newer0.15/SDK0.9 independently would
violate that released compatibility constraint. No application model provider, OpenAI
API calls, new runtime service or arbitrary npm plugin was added. Stack remains
FrankenPHP1.13.4/Trixie, PHP8.5.12, Symfony8.1.9, Twig SSR, Stimulus/AssetMapper.

`/mcp/compose` is stateless authenticated Streamable HTTP. An MCP session ID never
replaces a bearer credential. Every request resolves the current enabled application
user/roles; the server stores no selected actor/composition in a mutable singleton.
File-backed MCP sessions expire after1800s and are namespaced by actor+credential.
OAuth refresh for the same actor/client can retain its session; another PAT/user cannot
borrow it. Auth failures are401 challenges, never studio-login HTML redirects.

Local PATs have256bits of entropy, hash-only storage,1–90day expiry, immediate revocation,
an existing enabled editor owner and explicit compose:read/create/edit/preview scopes.
Optional catalogue patterns are `namespace/slug`, `namespace/*`, or `*`, max20. Reads,
create/edit/preview and every transitive immutable reference use CompositionVoter.
The original app intentionally shares the catalogue among editors; another editor's
draft is not private ownership data. Credentials, sessions, previews and retry records
are actor-isolated; no new tenant/ownership model changes manual access.

OAuth uses maintained League authorization-code/refresh grants plus the MCP OAuth bundle's
RFC9728/RFC8414 discovery, resource indicators and RFC9207 issuer identification.
Registration is **predefined clients only**; CIMD/DCR are disabled. Exact registered
redirects, S256 PKCE for public and confidential clients, explicit canonical resource on
authorize/token, signed resource binding, token expiry/revocation and current user/client
status are enforced. JWT subjects use stable application user IDs, not email addresses.
The authorization server is pinned by the configured issuer origin
and its private/public RSA key pair; access-token resource is a signed `resource` claim
(League's aud remains the registered client). This is OAuth, not an OIDC identity provider.
No ID-token/UserInfo or Enterprise email-domain restriction support is claimed.

Consent is CSRF-protected, bound to actor+validated authorization query, expires in10min,
and is consumed once. It cannot approve a changed scope/client/redirect request. A normal
studio login cookie authenticates that consent flow, but never authenticates MCP calls.
Debug profiles are disabled on MCP/OAuth requests so credentials/codes are not retained
in the profiler. Request bodies are bounded to256KiB; existing preview quotas remain.

## Browser profile setup

Open **/profile** from the navigation to edit your account, create/revoke personal MCP
credentials, copy/download Codex settings and test the actual authenticated endpoint.
Administrators can also register/enable/disable predefined ChatGPT OAuth clients there.
These Studio-side actions require no console commands. See [PROFILE.md](PROFILE.md) for
the browser workflow, permissions and the distinction between local checks and external
client account linking. The CLI instructions below remain an operator/automation option.

## Start locally and connect Codex

For a fresh checkout, `make bootstrap` installs locked dependencies/assets, initializes
private keys, migrates and starts the existing Docker services. For this working tree:

```bash
make install mcp-init migrate assets up
make check test
# Optional reproducible official-client smoke (creates only dedicated test drafts):
make mcp-smoke
```

Development application: **http://127.0.0.1:8095** (localhost also works). Use the same
browser hostname when following returned links to reuse its normal login. MCP endpoint:
**http://127.0.0.1:8095/mcp/compose** (a browser GET without bearer should return401).
Use your existing editor/admin account email; this does not create/reset a user password:

```bash
docker compose exec -T app php bin/console styleguide:mcp:token:issue \
  --email='YOUR_EXISTING_EDITOR_EMAIL' --label='Local Codex' --days=7 \
  --catalogue='*'
```

The command prints a token ID and shows the secret once. For narrower privileges repeat
`--scope=compose:read --scope=compose:edit` (add create/preview if needed), and repeat
catalogue patterns to include **all** dependencies you want to insert. Keep compose:read
for editing/reference lookup. The token-only option exists for private script capture.

Merge [examples/codex-compose-mcp.toml](examples/codex-compose-mcp.toml) into your Codex
configuration (normally `~/.codex/config.toml`), preserving other settings:

```toml
[mcp_servers.styleguide_compose]
url = "http://127.0.0.1:8095/mcp/compose"
bearer_token_env_var = "STYLEGUIDE_COMPOSE_MCP_TOKEN"
```

Set the secret in the **environment of the Codex process**, then start/restart that
process. For Bash, enter it privately without putting it in shell history/config:

```bash
read -rsp 'Composer token: ' STYLEGUIDE_COMPOSE_MCP_TOKEN
export STYLEGUIDE_COMPOSE_MCP_TOKEN
printf '\n'
codex
```

An already running IDE/daemon must also be restarted from an environment containing
that variable. Check `/mcp` in Codex and ask it to call compose_capabilities. The actual
CLI0.164.0 client was verified here; global user configuration was not modified.
Revoke the printed **token ID**, not the secret:

```bash
docker compose exec -T app php bin/console styleguide:mcp:token:revoke TOKEN_ID
```

Localhost means the client runtime. Host Codex uses8095; an app-container CLI uses
`http://localhost/mcp/compose`; another service on this development Compose network uses
`http://studio/mcp/compose`. A container/remote Codex cannot reach your host via its own
127.0.0.1. Use a reachable private address/forwarding arrangement and update host/origin
allowlists deliberately; do not expose a loopback token endpoint publicly for convenience.

## Configuration, keys and production

| Variable | Behaviour |
| --- | --- |
| `MCP_COMPOSE_ENABLED` |1 in local development; production Compose defaults to0. 0 returns404 for MCP/OAuth/discovery without affecting manual composition |
| `MCP_PUBLIC_BASE_URL` |Canonical issuer/application origin; default http://127.0.0.1:8095 in development. HTTPS required outside explicit local hosts; no path/query/userinfo |
| `MCP_PUBLIC_HOST` |Additional exact accepted HTTP Host; default localhost; must agree with public origin and TRUSTED_HOSTS |
| `MCP_OAUTH_ENCRYPTION_KEY_FILE` |Private key file path; default var/catalogue/mcp/oauth-encryption.key |

`.env.local` overrides development defaults. Restart the affected app processes after
configuration changes. Production Compose passes enable/base/host variables; set them
in its private compose.env before `make prod-up`. Set a matching `STUDIO_BASE_URI`, exact
TRUSTED_HOSTS and actual trusted proxy CIDRs; never trust all forwarded hosts/proxies.
Production listening remains loopback unless an explicitly configured HTTPS ingress
forwards to it. This change does not publish/deploy the existing production application.

`php tools/mcp/init.php` creates3072bit RSA keys and an encryption key under the existing
persistent private catalogue volume; it preserves existing keys and rejects incomplete
pairs. Directory0700, key files0600. `make mcp-init` and prod-migrate call
it. Back up that private directory with the database; restoring a database without the
same keys invalidates OAuth codes/refresh tokens. Never commit/copy keys into image
layers, the renderer, logs or examples. The production image includes only the initializer
and client smoke tooling; generated keys live in its persistent runtime volume.

For an explicitly enabled new local production environment, use the existing
`make prod-init prod-build prod-migrate prod-up` workflow with its own compose.env.
`make compose-mcp-production` builds a separate throwaway localhost8125 environment,
checks the official SDK and manual/MCP browser flows, and stops its own containers.
It does not replace the normal8096 production stack.

## ChatGPT connection (requires your account and reachable OAuth endpoints)

This repository's OAuth server/flows are tested locally. **No ChatGPT account was linked,
no public server was deployed and no tunnel was created.** Your workspace must permit
custom MCP connections. Current official instructions use ChatGPT **Plugins → + → Add
custom MCP server**; account/workspace UI availability must be checked after sign-in.

1. Choose a reachable HTTPS origin for this application and configure the variables and
   trusted proxy/host settings above. Keep `/mcp/compose`, discovery, `/oauth2/compose/*`
   and `/login` reachable for their respective clients/browser. Check unauthenticated MCP
   returns401 with resource_metadata, and both discovery documents advertise that exact
   HTTPS origin. Authentication must remain enabled.
2. In the custom MCP connection, enter `https://YOUR_HOST/mcp/compose` and select OAuth
   with a predefined client. Copy its **exact displayed callback URI**. Do not wildcard
   redirects or assume another account's callback ID. This server advertises issuer
   identification; the currently documented stable URI is
   `https://chatgpt.com/connector_platform_oauth_redirect`, but use the UI's exact value.
3. Register a confidential client with the maintained CLI, substituting that callback:

   ```bash
   docker compose exec -T app php bin/console league:oauth2-server:create-client \
     'ChatGPT catalogue composer' \
     --redirect-uri='EXACT_CALLBACK_FROM_CHATGPT' \
     --grant-type=authorization_code --grant-type=refresh_token \
     --scope=compose:read --scope=compose:create --scope=compose:edit --scope=compose:preview
   ```

   Use the production Compose command prefix if targeting production. The CLI generates
   an ID/secret and stores a hash of the secret. Enter them only into the connection's
   OAuth fields; do not paste them into a chat, URL or committed config. A public client
   (`--public`) is supported and tested if the chosen client UI supports that mode.
4. Complete the connection. The browser redirects to the existing studio login, then an
   explicit composer consent page. Sign in as your existing enabled editor/admin and
   approve the listed scopes. This is the legitimate account-linking screen, not a
   component preview requesting another login. MCP itself never uses the login cookie.
5. Review the26tools, install/select the custom connection in a new conversation, call
   compose_capabilities/search_catalogue, then create a dedicated draft and inspect its
   composer URL in the normal studio. Verify the returned saved revision and preview.
   Refresh connection metadata after later tool changes. Keep the client's write approvals.

Disable a predefined client and invalidate its access on subsequent requests with:

```bash
docker compose exec -T app php bin/console league:oauth2-server:update-client CLIENT_ID --deactivate
```

Reactivation is explicit; deleting the client also removes its access/code records.
Disabled users cannot use either credential path. Token exchange uses1haccess/7drefresh;
refresh rotation/revocation is provided by League. Resource is the exact canonical
`https://YOUR_HOST/mcp/compose` throughout, not merely the site's origin.

### Private-network option: Secure MCP Tunnel (not configured here)

A tunnel is transport, not authorization. Follow the current official guide with your
credentials: creating/editing requires Platform **Tunnels Read + Manage**; runtime use
requires **Tunnels Read + Use**, a restricted runtime API key and the correct organization/
workspace association. A ChatGPT subscription alone does not supply those permissions.
The client needs outbound HTTPS to api.openai.com:443 (or the configured mTLS endpoint)
and local access to this MCP server. Host forwarding targets127.0.0.1:8095/mcp/compose;
a client on this Docker development network targetshttp://studio/mcp/compose.

The user's browser must also reach the configured canonical login/authorization origin;
forwarding only the MCP POST path does not make private OAuth login pages reachable.
Use a supported reachable authorization origin/network arrangement. Inspect current
`tunnel-client help quickstart`, configure its HTTP server URL, supply its control-plane
key through a private environment reference, run doctor, and keep its polling client
healthy while selecting the associated Tunnel in ChatGPT. Preserve this application's
bearer/OAuth checks, resource URL and allowlists. No tunnel profile/binary/service or
unverified credential-dependent end-to-end claim is included in this implementation.

## Reproducible verification and sample request

The official SDK smoke is `make mcp-smoke`: discover26tools, find a real released
studio-showcase component, read schema, create a dedicated layout, batch reference/text/
responsive container insertion, replay, indent/outdent/reorder/duplicate/remove, validate,
discover/select an existing published theme when available, render exact revision8,
retrieve real sandbox HTML, read, and restore revision1 as9.
It creates a temporary scoped actor, revokes its token and disables it in finally; dedicated
`mcp-smoke/*` drafts remain for inspection. It never deletes original catalogue content.
If this is a fresh empty catalogue, seed the repository's normal samples first with
`php bin/console styleguide:showcase --publish` inside the app container.

`tests/Mcp/codex-client.py` uses the **installed Codex app-server MCP implementation**,
with command-line configuration overrides and a supplied token environment; it performs
real discovery/create/add/read without an LLM turn or global-config writes. The concurrency
client runs two HTTP sessions to prove one winner/one revision conflict and one exactly-once
replay for identical simultaneous requests. Test-only credentials are revoked afterward.
Detailed measured results and limitations are recorded in VERIFICATION.md and STATUS.md.

Example after connecting:

> Find suitable existing released navbar, pricing-card and FAQ catalogue items and inspect
> their editable properties. Create a draft pricing layout with a responsive container
> holding three distinct pricing instances, move the FAQ below it, validate and preview it
> using an available published theme. Use atomic batches where useful, report the saved
> revision and composer/preview URLs, and say whether you received HTML or actually saw an
> image. If a needed building block is unavailable, tell me rather than inventing a release.
> Do not publish or edit source templates.

Official references checked2026-10-27:
[Symfony MCP bundle](https://symfony.com/doc/current/ai/bundles/mcp-bundle.html),
[PHP SDK](https://github.com/modelcontextprotocol/php-sdk),
[MCP OAuth bundle](https://github.com/omouren/mcp-oauth-bundle),
[Codex MCP](https://learn.chatgpt.com/docs/extend/mcp?surface=cli),
[ChatGPT connection flow](https://developers.openai.com/plugins/deploy/connect-chatgpt),
[OAuth requirements](https://developers.openai.com/plugins/build/auth),
[Secure MCP Tunnel](https://developers.openai.com/api/docs/guides/secure-mcp-tunnels).

## Changed-file map

- Shared composer: `src/Composition/CompositionTree.php`, `CompositionWorkspace.php`,
  `CompositionCommandInput.php`; `src/Controller/CompositionController.php`;
  `assets/controllers/composition_controller.js`; `templates/composition/edit.html.twig`.
- Persistence/contracts: `src/Entity/ComposeToken.php`, `CompositionOperation.php`,
  `CompositionRevision.php`, `CompositionPreview.php`; `migrations/Version20261027203258.php`;
  both `agent-kit/schemas/composition.schema.json` and
  `packages/catalogue-contracts/schemas/composition.schema.json`;
  `packages/catalogue-contracts/src/CompositionValidator.php`.
- MCP adapter: `src/Mcp/ComposeApplication.php`, `ComposeOperations.php`,
  `ComposeToolHandler.php`, `ComposeToolInput.php`, `Tool/ComposeTools.php`,
  `Tool/ComposeSchemas.php`; `src/Security/CompositionVoter.php`;
  `src/Mcp/Security/ComposeIdentity.php`, `ComposeTokens.php`, `ComposeAuthenticator.php`,
  `ComposeRequestGuard.php`, `ActorSessionStore.php`.
- OAuth: `src/Mcp/OAuth/ComposeConsent.php`, `ComposeUserConverter.php`,
  `ResourceRequestGuard.php`; `src/Controller/ComposeOAuthController.php`;
  `templates/mcp/consent.html.twig`, `denied.html.twig`.
- Preview page: `src/Controller/CompositionPreviewController.php`,
  `templates/composition/result.html.twig`, `assets/controllers/composition_result_controller.js`.
- Wiring/dependencies: `composer.json`, `composer.lock`, `symfony.lock`, `config/bundles.php`,
  `config/services.yaml`, `config/reference.php`, `config/packages/security.yaml`,
  `mcp.yaml`, `mcp_oauth.yaml`, `league_oauth2_server.yaml`, `http_discovery.yaml`;
  `config/routes/mcp.yaml`, `league_oauth2_server.yaml`; `.env`, `.gitignore`;
  `src/EventListener/PrivateProfileListener.php`.
- CLI/Docker: `src/Command/CreateComposeTokenCommand.php`, `RevokeComposeTokenCommand.php`,
  `ComposeMcpSmokeCommand.php`, `BrowserAccountsCommand.php`; `tools/mcp/init.php`,
  `smoke-client.php`, `generate-tool-schemas.py`; `docker/frankenphp/Dockerfile`,
  `compose.production.yaml`, `Makefile`.
- Verification: `tests/Functional/ComposeMcpTest.php`, `ComposeOAuthTest.php`,
  `CompositionTest.php`; `tests/Integration/ComposeTokenCommandTest.php`,
  `CompositionBoundsTest.php`; `tests/Browser/compose_mcp.spec.js`;
  `tests/Mcp/codex-client.py`, `concurrency.py`, `credentials.php`;
  `tests/AppearanceProduction/run.sh`.
- Documentation: this runbook, `examples/codex-compose-mcp.toml`, `ARCHITECTURE.md`,
  `CONTRACTS.md`, `SECURITY.md`, `MILESTONES.md`, `VERIFICATION.md`, `agent-kit/STATUS.md`.
  The supplied `codex-compose-mcp-server.md` task prompt is preserved unchanged.
