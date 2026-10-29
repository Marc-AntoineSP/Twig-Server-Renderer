# Editing protection and composition recovery

Source, composition and theme editors compare the current editable document and metadata
with their last authoritative saved snapshot. Opening tabs, selecting nodes or browsing
theme variants does not itself create an edit. Theme family labels and unsaved inactive
variants are included. Renderer presets, trials, Save and Publish remain separate actions.

| State | Meaning |
| --- | --- |
| Clean / Saved | Current editable content matches the saved snapshot; a successful save also reports its revision |
| Unsaved changes | Content, metadata or invalid entered JSON differs from that snapshot |
| Saving | A captured snapshot is being submitted; newer edits can remain unsaved |
| Failed | The save failed; local values and the previous saved snapshot are kept |
| Conflict | Another write advanced the revision; local values are kept and must be reconciled |

In-application navigation uses a native modal with **Keep editing** and an explicit
discard action. Escape keeps editing. Canceled navigation preserves the current fields,
caret, tree selection and history position. The browser supplies its own confirmation
for reload, close and full-page navigation where supported. Leaving during a pending save
does not cancel a write already submitted; the dialog explains this distinction.

The shared guard removes its listeners on disconnect. It uses no local/session storage,
IndexedDB or extra history entries. Temporary field snapshots cover browser form-state
restoration during a canceled traversal. Turbo restoration visits bypass the ordinary
before-visit event, so the guard also handles the installed Turbo 8.0.23 history indices.
Recheck this integration when upgrading Turbo. References: [Turbo events](https://turbo.hotwired.dev/reference/events)
and the [installed version's history implementation](https://github.com/hotwired/turbo/blob/v8.0.23/src/core/drive/history.js).

Source saves enhance the existing CSRF-protected draft API with If-Match. With JavaScript
disabled, the original SSR form remains. An earlier save response advances the saved
revision without discarding newer text, tree/props or theme edits. A failed or conflicting
save never labels newer work saved. There is no automatic publication or persistent
browser recovery store, and this change does not add full in-memory Undo/Redo or shortcuts.

Open **Saved history** in the composer to inspect recorded saves. Pages contain at most
20 entries in the UI (API limit 1–50, offset 0–10000). Each entry identifies the saved
revision, timestamp, recorded action and stored actor ID. “Snapshot actor” means the
account recorded with that snapshot; it does not invent original authorship for older
content first captured by a later save. Deleted actors are reported as unavailable.

Select a revision to compare it with the editor's saved head. Differences cover inserted,
removed and reordered/moved nodes, changed text/classes/props, reference versions and
title/description. Long values are abbreviated; the first 100 differences are shown
with full counts. Comparisons use saved snapshots, not the unsaved tree behind the panel.
No raw historical tree or executable source is dumped into the interface.

**Restore as new revision** replaces the tree, title and description with the selected
snapshot and creates a later head. If local edits exist, **Keep editing** preserves them;
**Discard local edits and restore** explicitly permits replacement. Only a successful
response updates the editor's tree, title, revision and saved state. The old preview is
cleared and rebuilt for the restored content. Existing snapshots remain unchanged.

Restoration requires the current expected revision and revalidates historical references
under current permissions, including nested dependencies. Missing versions, inaccessible
references and invalid historical content cannot bypass validation. A conflict keeps
local work and offers **Load latest saved revision**, with another explicit discard
choice. Copy or merge local work before choosing replacement. Loading history never
silently refreshes the editor's revision.

History starts with the first captured save for older drafts. Missing/uncaptured snapshots
cannot be reconstructed. There is no new pruning policy. Empty history and missing records
are explained; invalid current or historical references may prevent inspection according
to the existing workspace policy. Browser history/restore endpoints require editor access,
normal composition permissions and CSRF for writes, and return private/no-store JSON.

The manual UI and MCP share CompositionHistory and CompositionWorkspace. MCP retains its
durable operation receipts, current authorization checks and idempotent replay. Browser
restores use expectedRevision; a retry after a lost success response may conflict and
requires deliberate reconciliation instead of creating another head silently. There is
no second snapshot table, schema migration or new dependency.


The composition inspector's form and Advanced JSON share node.props. Invalid JSON or
incomplete numeric/structured text is kept per stable node ID, makes the editor dirty
and blocks Save and view switching. Selecting another node retains that text. Use
**Go to invalid properties** to return, then correct it or explicitly **Discard invalid
property text** to recover the last valid object. Default/reset actions are separate,
deliberate edits. See INSTANCE_PROPERTIES.md for omission/null and schema behavior.
