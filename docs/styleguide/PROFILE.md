# Profile and MCP connection setup

Open **http://localhost:8095/profile**, or select **Profile** in the application navigation.
All Studio-side personal credential and ChatGPT OAuth client setup is available here.
The existing console commands remain available for operators and automation.

## Account details

Save an optional display name and your sign-in email. Email changes require your current
password, are canonicalized and checked for uniqueness, and keep the current browser
signed in. Roles/account status are displayed as information; personal forms cannot edit
another account or grant privileges. A profile fingerprint prevents stale-tab overwrites.
Existing accounts acquire only a nullable display_name column; no identities, email
addresses, password hashes, roles or catalogue data are rewritten by migration.

Change password using the separate form. It requires the current password and a matching
new password of at least12characters, at most4096bytes. Password checks are limited to5
attempts per5minutes per account. The current browser keeps its session; other browser
sessions expire at their next request. Existing personal MCP credentials and this user's
OAuth access/refresh/code grants are revoked. Grants belonging to other users remain.

## Personal MCP credentials

Editors and administrators can expand **Create a personal MCP credential**, choose a
name, composer permissions, catalogue patterns and1–90day expiry, then create it.
Read permission supports discovery and editing; additional permissions separately allow
creation, editing and isolated previews. Patterns accept `namespace/slug`, `namespace/*`
or `*`, including any referenced dependencies. The browser interface limits an account
to20active credentials; the service retains existing256bit entropy/hash-only storage.

The one-time secret appears in the masked **MCP bearer token** field. Copy/show controls
operate only after a user action. The value is never stored in browser storage, a URL or
the session. Refresh/navigation clears it; the credential list contains metadata only.
Each credential's Revoke action is owner-only, including for administrators viewing their
own personal page. Other users' credential IDs do not grant access or revocation rights.

**Test MCP connection** performs real same-origin HTTP calls using the entered bearer:
initialize/negotiation, initialized notification, tools/list and compose_capabilities.
The requests omit studio cookies, follow no redirects, have a20second overall deadline
and perform no catalogue mutation. Results show the negotiated version and actual tool
count, or actionable authentication/scope/availability errors. This works with personal
PATs and the server's OAuth access tokens. It verifies the browser's connection to this
server; it does not claim that a separate Codex or ChatGPT client has connected.

## Connect Codex

Expand **Connect Codex** to copy or download the actual configured endpoint's TOML.
It references `STYLEGUIDE_COMPOSE_MCP_TOKEN`; the secret is deliberately absent from the
configuration download. Set that variable in the environment of your Codex app/IDE/CLI,
merge the configuration and restart its MCP connection. These are client-side settings;
the website cannot modify files or environment variables on your computer.
No server command is required to issue or revoke a credential or run the browser check.

Keep one browser hostname when following links, because sign-in cookies belong to that
hostname. A remote/container client must reach the configured URL from its own network.
The URL shown here comes from the existing MCP_PUBLIC_BASE_URL deployment configuration.

## ChatGPT OAuth clients

**Check OAuth server setup** fetches real protected-resource and authorization-server
metadata and checks resource/issuer consistency, issuer identification and S256 support.
It reports readiness separately from external network access and ChatGPT account linking.

An administrator can register a confidential predefined client under **Register a ChatGPT
OAuth client**, using the exact HTTPS callback copied from the ChatGPT connection UI.
The allowed callback forms are the documented stable callback or a callback-ID-specific
ChatGPT URI. No arbitrary callback host, wildcard redirect, plain PKCE or unrelated OAuth
scope can be registered through this page. The maintained League client manager stores a
hashed secret; the plaintext is returned once. Copy the ID/secret into the OAuth fields in
ChatGPT. Existing clients, including those created through the CLI, can be disabled or
enabled here. These administrator controls are shared and affect every linked user of
the selected client. Editor/reader accounts cannot call those routes.

Complete the link in ChatGPT, sign in to Studio and approve the scopes, then call composer
capabilities from a new conversation using that connection. A real ChatGPT connection
requires your account/workspace access and a reachable HTTPS endpoint or supported tunnel
arrangement, including access to OAuth/login pages. Local discovery success cannot verify
that external path. This feature does not publish the site or create a tunnel.

MCP enablement, canonical origin, HTTPS ingress and trusted proxy/host settings remain
operator deployment settings. An explicitly disabled deployment stays disabled; its
profile shows the state and the live check returns an unavailable result. Personal
settings cannot override that server-wide policy.

Official client instructions checked2026-10-27:
[Codex MCP](https://learn.chatgpt.com/docs/extend/mcp?surface=cli),
[ChatGPT connection setup](https://developers.openai.com/plugins/deploy/connect-chatgpt),
[OAuth callbacks/authorization](https://developers.openai.com/plugins/build/auth).
See COMPOSE_MCP.md for the existing server contract and network configuration.

## Implementation and verification

ProfileController uses focused AccountProfile and ComposeConnections services, the
existing Symfony authenticated user/CSRF/hasher/Doctrine services, ComposeTokens and
League ClientManager. Application-owned Twig renders all forms/list/configuration markup;
Stimulus handles clipboard/secret visibility and live protocol checks. No new dependency,
SPA, shell execution, model provider or renderer permission was introduced. MCP tools
remain the original composer allowlist; profile administration is cookie/CSRF protected.

The MCP Origin guard accepts the actual same origin after validating Host, as well as its
existing explicit origins. This permits browser checks on supported Docker aliases while
continuing to reject untrusted hosts and cross-origin requests. Sensitive profile pages
have private/no-store responses and no profiler capture. Secret values are scrubbed from
DOM fields/attributes before history caching/navigation. One-time responses normalize the
visible URL back to /profile; GET form-return routes safely redirect to that page. Native
section anchors preserve unsaved fields without a Turbo reload.

Migration Version20261027215409 adds only studio_user.display_name, nullable VARCHAR100.
Tests and exact results are recorded in VERIFICATION.md and agent-kit/STATUS.md. The
isolated production acceptance target is `make profile-production`; it exercises the
browser UI, actual PAT/OAuth MCP calls and existing authentication/composer flows.

Changed files: User/ComposeToken entities; new ProfileController, AccountProfile and
ComposeConnections; profile/show.html.twig and _mcp.html.twig; profile_mcp controller;
base navigation, private-profiler/Origin guards; profile rate-limiter configuration and
the additive migration; ProfileTest, profile.spec.js, production test driver/Make target;
profile/MCP/architecture/security/milestone/verification/status documentation.
