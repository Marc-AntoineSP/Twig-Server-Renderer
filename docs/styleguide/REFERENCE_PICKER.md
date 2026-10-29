# Composer reference discovery

In a block or layout composer, select a container to insert inside it, or select a
child to insert after it. **Add reference** opens a searchable list of published
components, blocks and layouts. Search by package name, title or description; combine
kind, personal favorites and an exact version. Each page contains at most twenty
immutable releases, ordered by package name and descending numeric version. Older
nonfavorite releases remain searchable. Favorites are item-wide, so every eligible
version of a starred item can appear.

Choose a radio button, then **Insert selected reference**. The instance starts with
that release's defaults. The selected-node inspector always shows the exact
`namespace/name@version`, independently of search filters and pages. **Change immutable
reference** replaces that node only after an explicit selection. It keeps existing
instance props; if they are incompatible, the operation fails with the original tree
and props intact. Check **Replace instance props with this release's defaults** only
when that reset is intended. The existing JSON property editor remains available.

Search, paging, filtering and previews do not save or change the composition. Applying
a reference changes the unsaved tree; **Save composition** persists it with the usual
revision check and history. A preview link opens the selected immutable release in a
separate tab. An available **Show published preview** button loads one existing cached
thumbnail in a script-disabled opaque iframe; it never starts a build for every result.

The picker uses a native modal dialog following the
[WAI-ARIA modal dialog pattern](https://www.w3.org/WAI/ARIA/apg/patterns/dialog-modal/).
Opening focuses Search references. Tab/Shift+Tab stay within the dialog; Space selects
a radio button; Enter submits a search or activates the focused action. Escape and
Close restore the opener; successful insertion focuses the selected tree node.
Loading, empty and error states have status text and an explicit retry. Searches wait
300ms after typing, respect IME composition, abort prior requests and reject obsolete
responses. Closing during a pending selected-release read cancels its insertion.

Without JavaScript, **Browse immutable references** opens a server-rendered GET search
with the same bounded summaries and pinned preview links. That page uses saved tree
context; the enhanced dialog uses the current unsaved selection and tree. Adding nodes
still requires the existing JavaScript composer.

If access changes or a reference is unavailable, the inspector keeps its pinned
identity and local props, hides the unavailable preview link and offers Recheck or a
deliberate replacement. Enabled studio editors currently share catalogue access;
personal favorites remain private. Revoking an account invalidates its session. No new
per-item access policy is introduced.

The browser query is separate from MCP's item/draft discovery: the latter includes
unpublished drafts and scoped identities. The UI never consumes an MCP transport.
Both still mutate through CompositionWorkspace, which rechecks authorization, exact
versions, dependency closure, cycles, props, insertion slots and revision. Discovery
uses published metadata to remove known incompatible graph choices before pagination;
archive integrity, complete source limits and props validation remain authoritative at
application time. Search does not execute or compile imported code.

No migration, dependency installation or operator configuration is required. Automated
browser audits cover application controls at narrow/wide widths and both modes;
manual screen-reader assessment and contrast incompletes remain separate checks.


The accepted exact reference also supplies the composition property inspector's shared
schema controls. A compatible replacement retains the complete current props object;
an incompatible replacement leaves the old reference and values intact. The explicit
release-default checkbox replaces the whole object. There is no coercion or partial
dropping. See INSTANCE_PROPERTIES.md for form/Advanced JSON and error recovery.
