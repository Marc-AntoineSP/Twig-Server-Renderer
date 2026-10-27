# Codex implementation prompt — MCP access to the catalogue composer

Extend the existing Styleguide Studio application with an MCP server that lets Codex and ChatGPT create and edit catalogue components, blocks and layouts through the same operations available at /catalogue/<id>/compose.

Implement this in the existing repository. Audit the current composer first, then complete the application work and integration tests. Preserve working functionality and user data. Do not rebuild the application from the original agent kit.

## 1. Scope and intended workflow

A user should be able to ask a connected AI:

“Create a draft pricing layout using the existing navbar, three pricing cards and an FAQ. Put the cards inside a responsive container, move the FAQ below them, use an available theme for the preview, and show me the result.”

The AI should discover available building blocks and editable properties, create or open the appropriate draft, perform typed composer operations, validate it, render a preview and return its composer URL and a concise change summary.

The integration covers creation and editing within the catalogue composer only. Include its existing add-container, add-component/block/layout, update-properties, indent, outdent, reorder, move, duplicate, remove and save capabilities where supported. Distinguish creating a catalogue item from inserting a reference to an existing item.

The server must not expose unrelated application administration, theme editing, font acquisition, package installation, publishing, release deletion, shell execution or unrestricted database/file access. Selecting an existing theme for a composition preview is allowed if the composer already supports that.

Creation of a “component” means the component authoring capabilities actually available in the compose UI. Do not silently expand this into an unrestricted Twig/PHP/JavaScript source editor. Report any capability that belongs to a different editor and is consequently outside this integration.

Keep Twig SSR, Tailwind, daisyUI, Stimulus, AssetMapper where installed, and the existing Docker/FrankenPHP setup.

## 2. Inspect the actual implementation

Read AGENTS.md, composer.json/lock, package manifests, Docker services, routes, composer Twig templates, Stimulus controllers, domain/application services, entities, permissions, renderer and existing tests.

Produce a compact operation map connecting each compose UI action to its current handler, data mutation, validation and persistence behaviour. Include the exact semantics of indent/outdent and move up/down, root restrictions, nested references, property schemas, draft saving and undo.

Identify whether the UI edits a browser-only working tree until Save or persists every operation. The MCP adapter must use the same authoritative persistence path; it cannot edit a disconnected copy of the tree.

Verify the compatible released symfony/mcp-bundle and mcp/sdk versions against the installed PHP/Symfony dependencies and their matching documentation. Pin the resulting dependency resolution in composer.lock. Do not copy configuration from newer documentation without checking that the installed package supports it.

## 3. Share the editing logic

Extract or reuse application services for composer reads, commands, validation and saving. Both the browser interface and MCP handlers must use those services. Avoid separate implementations of tree manipulation.

If some rules currently exist only in Stimulus, add authoritative server validation and preserve equivalent UI behaviour. Keep the actual composition format; do not introduce a competing schema or serialize the editor DOM as the data model.

Use stable composition and node IDs. Validate parent/child compatibility, root placement, permitted slots, sibling order, component properties, references and maximum size/depth. Detect cycles both in node trees and in nested component/block/layout references.

Keep dependency/reference resolution consistent with the existing catalogue. Do not silently change pinned releases or modify a referenced component when the user is editing one instance.

## 4. Provide a real MCP endpoint

Use Symfony's MCP bundle as a server. A full Symfony AI agent, model provider integration and OpenAI API calls inside this application are unnecessary for this feature: the connected client supplies the AI.

Expose a dedicated authenticated Streamable HTTP endpoint, proposed path /mcp/compose. Register only this composer's capabilities using an explicit allowlist or dedicated namespace. Do not expose every attributed service globally.

Use SDK transport and schema support rather than implementing JSON-RPC yourself. Verify discovery and calls with a real MCP client, including the protocol negotiation supported by the installed SDK. Optional STDIO support may reuse the handlers, but is not necessary when local Codex can reach the HTTP endpoint.

Resolve identity and permissions on each request. FrankenPHP workers must not retain one caller's user, composition selection or credentials in mutable shared state. An MCP session ID is not authentication.

## 5. Tools and discoverable contracts

Use focused, well-described tools with explicit JSON input and output schemas. These are proposed names; map them to the existing domain vocabulary and document the final names.

| Proposed tool | Responsibility |
| --- | --- |
| compose_capabilities | Explain allowed node kinds, property schemas, allowed operations, limits and save semantics. |
| compose_search_catalogue | Find accessible components, blocks and layouts with stable IDs and concise summaries. |
| compose_get | Read one composition, revision, tree, references, validation status and effective permissions. |
| compose_get_node | Read details/schema for one node without returning the entire catalogue. |
| compose_create_draft | Create a new component, block or layout draft using supported catalogue creation rules. |
| compose_add_container | Add a supported container to an explicit parent/slot and position. |
| compose_add_reference | Insert an accessible existing component, block or layout using normal reference rules. |
| compose_update_node | Change allowed content, properties, variants, styles or bindings supported by the UI. |
| compose_move_node | Move a node to an explicit parent/slot and relative sibling position. |
| compose_indent_node / compose_outdent_node | Match the UI's nesting behaviour exactly. |
| compose_move_up / compose_move_down | Match the UI's sibling reordering behaviour exactly. |
| compose_duplicate_node / compose_remove_node | Duplicate or remove a node/subtree using existing semantics. |
| compose_validate | Validate the composition without changing its content. |
| compose_render_preview | Render a specified composition revision using the existing isolated renderer. |
| compose_apply_batch | Apply an ordered, bounded list of supported editing commands atomically. |
| compose_get_history / compose_restore_revision | Inspect change history and restore a recoverable draft revision. |

Also expose existing composer actions discovered during the audit if they fall within this scope. Document unsupported actions rather than silently ignoring them. Add a save-working-copy operation only if the persistence model requires it; do not introduce a fake Save step after operations that already save.

Search results must be paginated. Read responses should include the information necessary for editing: node IDs and parents, legal positions/slots, allowed properties, reference IDs/revisions and available actions. Support bounded subtree reads for large layouts.

Expose schema information through ordinary tools so clients do not need MCP resource browsing to discover how to work. Optional resources/prompts may supplement this.

Tool responses should include compositionId, the authoritative revision, affected/generated IDs, a short change summary, warnings and the composer URL. Use structuredContent where supported, with a useful concise text representation.

Use stable error codes such as VALIDATION_FAILED, REVISION_CONFLICT, INVALID_PARENT, REFERENCE_CYCLE, NOT_FOUND and FORBIDDEN, with actionable field/node details. Respect the application's policy on concealing inaccessible records. Return MCP tool errors through the SDK's supported mechanism.

Describe annotations accurately. Tools that edit are not read-only; creating multiple objects is not naturally idempotent. Annotations inform clients but never replace server-side enforcement.

## 6. Concurrent editing, retries and recovery

Every mutation of an existing draft must require its expectedRevision. Check the revision and save atomically with the mutation. A stale request returns a conflict and does not overwrite newer browser or AI edits.

Use the same protection for browser saves. Adding a revision check only to MCP still allows a stale browser form to erase the AI's work.

Require an operationId/idempotency key for mutation calls, including draft creation and batches. Scope replay records to the authenticated actor, operation and target; match a canonical payload hash. A retry after a lost response returns the original result without creating another node or revision. Reusing a key with different input must fail. Check authorization before returning replayed results. Persist replay records atomically with successful writes.

A batch contains only explicitly typed composer commands, never arbitrary PHP, SQL, shell or unrestricted JSON patches. Process commands sequentially against one working tree and commit all changes in one transaction after validation. Any failure rolls back the entire batch. Enforce a reasonable documented batch limit.

Allow validated temporary aliases for nodes created earlier in the same batch and return the alias-to-ID mapping. Define exactly how aliases are referenced; do not confuse aliases with persisted IDs.

Reuse draft history/undo where available. Otherwise retain enough before/after information to recover these edits. Restoration creates a new revision and also requires an expectedRevision; it must not reset revision numbers or overwrite later work silently.

Authorized draft editing should not require a new application confirmation for every small node movement. Honour the connected client's tool approval policy. No publishing tool is included.

## 7. Authentication and connection paths

Implement a practical local Codex path first, then the authenticated ChatGPT connection path. Both use the same tools and permissions.

For local Codex:
- Use the loopback HTTP endpoint with a revocable, expiring, high-entropy bearer token bound to an application user and explicit composer scopes.
- Reuse the application's token mechanism if suitable. Otherwise provide a minimal authenticated issuance/revocation path; store only token hashes and show the token only at creation.
- Separate read, create and edit capabilities where useful. Apply existing Symfony voters to every affected catalogue item and referenced dependency.
- Never trust a userId, role, tenant or permission supplied as a tool argument.

For ChatGPT:
- Support OAuth-compatible MCP authorization using maintained libraries or the existing authorization provider. The Symfony MCP bundle alone is not a complete authorization server.
- Provide protected-resource discovery, authorization-server metadata, authorization code with S256 PKCE, exact redirect allowlisting, resource/audience validation, expiry and scope checks. Use a supported predefined client or supported discovery/registration approach.
- Map the validated identity to an application user. A normal browser login cookie or a Codex-only personal token is not by itself a working ChatGPT OAuth integration.
- Keep authorization endpoints reachable for the chosen connection mode. A network tunnel does not replace user authorization.

Bind local development access to loopback unless explicitly configured otherwise. Use HTTPS for remote access, preserve host/origin checks and configure trusted proxies correctly. Keep secrets out of URLs, repository files, tool results and logs.

Limit the exposed editing surface, request sizes and renderer resource use. Imported catalogue text is data, not an instruction to access secrets or run another tool. Preserve Twig/rendering sandbox protections.

## 8. Preview and browser feedback

The AI needs inspectable state and rendering feedback. Reuse the same compiler, Twig renderer, assets and validation pipeline as the compose page.

Preview the exact requested revision. Return its revision, render status, warnings/errors and authenticated preview URL. If rendering is asynchronous, return a job ID and expose bounded status/result retrieval.

If an existing browser-rendering worker can provide screenshots, return a bounded MCP image result tied to the revision and viewport. Otherwise provide available HTML/diagnostics and the preview URL, explicitly marking visual inspection as unavailable. A URL alone does not mean the AI has seen the page. Do not add a large screenshot platform just for this first integration.

When AI changes a saved draft, show that revision in the compose UI. Reuse existing live updates, or add modest revision polling while the page is visible. If the browser has unsaved work, show a clear conflict/update notice and preserve that work; never refresh over it automatically.

Scope demo and editor events correctly. Keep all manual composer actions operational.

## 9. Docker and client setup documentation

Run dependencies, migrations, assets and tests through the existing Docker services. Keep the current FrankenPHP image policy. HTTP MCP should normally run in the existing Symfony application container; do not add another language/runtime service solely to wrap PHP services.

Provide exact commands based on the real Compose service names, plus environment-variable documentation and a way to disable MCP access.

Document local Codex configuration after the endpoint exists. This is the intended TOML shape, with the URL adjusted to the real host/port:

~~~toml
[mcp_servers.styleguide_compose]
url = "http://127.0.0.1:8095/mcp/compose"
bearer_token_env_var = "STYLEGUIDE_COMPOSE_MCP_TOKEN"
~~~

Explain how to set the variable in the environment of the Codex process without putting the token in config.toml. For containers or remote clients, localhost refers to their own runtime; document the address that can actually reach the app.

For ChatGPT, document adding the MCP server as a custom connection/plugin, configuring OAuth and testing it. Current official documentation supports a reachable HTTPS endpoint or Secure MCP Tunnel for a private server, subject to account/workspace access. Check the actual available client UI rather than promising account-specific access.

If using Secure MCP Tunnel, document its separate Platform permissions, runtime API key and network requirements. Configure the local forwarding address using the actual Docker topology. Do not assume a ChatGPT subscription supplies tunnel credentials. Provide an optional Docker profile only if this path is implemented and tested.

Do not publish the application or create a public tunnel merely to test local tools. Prepare the integration and runbooks; external account setup is performed by the user with their credentials.

## 10. Meaningful tests and completion criteria

Use existing test conventions. Cover:
- Tool discovery exposes only the intended composer capabilities.
- Valid authenticated reads and mutations work through the actual MCP endpoint.
- Missing/expired/revoked credentials and insufficient permissions are rejected.
- Cross-user access and unauthorized component references are rejected.
- Create, add, properties, move, indent/outdent, duplicate/remove and supported save behaviour match the UI.
- Invalid nesting, out-of-range placement and reference cycles leave the composition unchanged.
- Concurrent browser/MCP writes conflict safely.
- Lost-response retries do not duplicate changes.
- A failed batch rolls back; a successful batch returns all generated IDs.
- History restoration preserves recovery and concurrency rules.
- Preview results correspond to the returned revision.
- A new draft created through MCP opens and remains editable in the normal compose UI.
- Existing composer actions and production rendering still work.

Include a reproducible MCP client smoke test: discover tools; find actual catalogue items; create a dedicated test draft; build a small layout; reorder and nest nodes; validate; render; read it back; restore a previous draft revision. Use dedicated fixtures, never delete real catalogue content to clean up.

Record exactly what was tested. Local SDK/Inspector tests do not prove that a ChatGPT account connection succeeded. Complete a real Codex round trip when available; provide precise remaining account steps for ChatGPT if credentials/access are unavailable.

Deliver working implementation, any migrations, tests, the UI-to-tool operation map, configuration examples, connection documentation and a final sample user prompt. Report changed files, test results, remaining limitations and exact startup commands. Do not stop after architecture or a list of TODOs.

## Reference documentation

These sources establish the integration choices; verify versions and client availability during implementation.

- Symfony MCP Bundle: https://symfony.com/doc/current/ai/bundles/mcp-bundle.html
- Codex MCP connections: https://learn.chatgpt.com/docs/extend/mcp
- OpenAI MCP server design: https://developers.openai.com/plugins/build/mcp-server
- ChatGPT custom MCP connections: https://developers.openai.com/api/docs/guides/custom-mcp-server
- OAuth authentication: https://developers.openai.com/plugins/build/auth
- Secure MCP Tunnel: https://developers.openai.com/api/docs/guides/secure-mcp-tunnels

