# Codex task: apply catalogue themes to the application itself

The component catalogue/editor is already functional. Extend it so I can apply its themes to the current application's interface, switch between light and dark with a crescent-moon/sun button, and create or edit themes and immediately try them on the application.

Implement the complete feature in the existing repository. Read AGENTS.md and inspect the current theme model, gallery, editor, persistence, font library, CSS generator, application layout, Stimulus controllers and preview isolation. Current code is authoritative; old milestones may be stale. Preserve existing work and data. Keep Symfony/Twig SSR, Tailwind/daisyUI, Stimulus and the existing Docker/asset pipeline.

This request explicitly adds application-wide theming. Earlier instructions to keep the studio's appearance fixed do not prevent this feature. Component preview appearance should still be independently selectable: applying a studio theme must not silently rewrite the component/layout being edited or its export settings.

## 1. Required interface and behaviour

Add an **Apply theme** button to each usable theme card/detail view. It must change the real application shell: page background, header, sidebar, navigation, cards, forms, tables, buttons, alerts, dialogs, tooltips, dropdowns and typography. Show an **Applied** indicator and the active theme name. Applying a saved theme persists my personal preference and offers an Undo action or a readily accessible Restore default action.

Add a compact appearance control in the main header, usable on mobile and desktop:

| Effective appearance | Icon displayed | Button action and accessible label |
| --- | --- | --- |
| Light | Crescent moon | Switch to dark mode |
| Dark | Sun | Switch to light mode |

Use the project's existing icon system or small inline SVG icons. Keep the glyph decorative and expose the action through an accessible name. Use a real button with keyboard activation and visible focus, not an unexplained clickable icon. Its tooltip must describe the same action as its accessible label.

Provide a **Use system appearance** choice in the appearance menu. The main button remains a simple light/dark toggle. In system mode it shows the action opposite the currently effective mode; clicking selects an explicit light/dark preference. OS changes are followed only while system mode is selected. Preserve mode when changing theme families whenever that mode exists.

Add a visible **Restore default appearance** action that resets personal selection to the built-in default family and system mode. Make this action reliably reachable even after trying a poor-contrast custom theme; a narrowly scoped fixed-style recovery control is acceptable. Do not add a full application redesign to this task.

## 2. Model theme family and appearance separately

Reuse existing entities/documents where possible. Separate the selected theme family from the mode preference: light, dark or system. A family can reference distinct light and dark variants, each with its own colours and optional typography/geometry settings. Do not treat light and dark as two unrelated global themes or replace a selected custom family with generic daisyUI light/dark on toggle.

Use stable IDs and explicit variant relationships. Do not depend on parsing display labels or assuming every slug ends in -light or -dark. Migrate existing independent themes non-destructively and pair only records whose relationship is known. Single-variant themes must remain usable.

For a single-variant family, use its available variant and show which mode is available. Keep the requested preference separately from effective mode. Disable the unavailable toggle with a discoverable explanation and offer **Create light variant** or **Create dark variant** in the editor. Do not apply a CSS invert filter, silently jump to another family, or present a duplicated palette as a genuine opposite-mode variant.

The built-in default family must have working light and dark variants. Newly created families can start from this complete pair, duplicate an existing pair, or start with one variant and add the other. Preserve existing provenance, font references and package metadata.

Applying a theme selects a saved, validated snapshot of the relevant family/variants. Respect the project's existing immutable release/revision model. Saving an edit must not overwrite a published release or silently change another user's applied snapshot. If app appearance needs a small preference record referencing variant revisions, add that rather than copying the complete theme document into each user record.

## 3. Apply themes at runtime, including newly created ones

Use the application root as the theme boundary, typically html[data-theme], with a separate root effective-mode attribute such as data-color-mode. Keep CSS color-scheme consistent with effective mode so native controls and scrollbars agree. Reuse a single application appearance controller/service rather than creating competing togglers.

Audit studio templates/styles for fixed white/black/grey/brand backgrounds and text that prevent theme changes. Convert appropriate UI surfaces to existing daisyUI semantic classes/tokens. Preserve intentional content colours, code syntax semantics, illustrations and independently themed previews. Include overlays mounted outside the main content wrapper; changing only one panel is insufficient.

Make Tailwind dark: utilities follow the effective mode, using a stable selector compatible with the installed Tailwind version. Do not generate a hardcoded list of dark theme names that must be rebuilt whenever a new theme is created. Avoid daisyUI theme-controller selectors or OS media rules overriding the chosen root state; consolidate existing implementations.

Support themes saved after the production image was built. A data-theme name alone is insufficient when no CSS for that theme exists. Generate an application-safe stylesheet from validated theme tokens, with versioned/content-addressed URLs and appropriate access control. Reuse the existing generator/storage when possible. Token-only changes should not require a complete Tailwind rebuild; compile changes that actually require it through the existing controlled pipeline.

Do not write dynamic assets into AssetMapper's already compiled production manifest and assume the browser can see them. Serve them through the existing runtime asset mechanism or implement a focused one. Keep the static application utilities compiled normally. Ensure runtime theme selectors have the required specificity and ordering, and remove obsolete temporary rules without overriding unrelated scoped themes.

Only validated declarations, known selectors, supported font definitions and registered local assets may reach the application's own DOM. Never inject raw scraped CSS, arbitrary uploaded CSS/HTML or component JavaScript into the studio to apply a theme. Keep component renderer sandboxing and iframe permissions unchanged. Comply with the existing CSP, using approved stylesheet endpoints or nonce-based styles as appropriate.

Load a candidate stylesheet before committing the root attributes and preference. Use sequence/cancellation handling so rapid A -> B -> C selections cannot end on an older response. If loading, validation or preference persistence fails, preserve/restore the last confirmed appearance and report the failure. Show success/Applied only for the confirmed selection. Retain readable font fallbacks when a font cannot load.

Reuse the local font library for body, heading and code selections. Load only required faces, respect declared weights/styles, and ensure the font chosen for application headings does not unintentionally replace code fonts or icons.

## 4. Persist preferences and avoid a flash of the wrong theme

Default scope is the current user, not a global site setting. For signed-in users persist through the existing profile/preferences mechanism, with authorization and CSRF protection. One user's Apply action must not change everyone else's application. Do not introduce a global administrator setting unless it already exists and needs integration.

If guest access exists, use a validated cookie or existing guest-preference mechanism. Keep authenticated account preferences authoritative after sign-in; do not let another account's stale localStorage silently override them. If localStorage is used as a cache, scope it appropriately and handle unavailable/corrupt storage.

Define and document precedence: temporary editor trial, then saved authenticated preference (or guest preference), then default family with system mode. Temporary trials must not become saved preferences accidentally.

Render known saved theme state and the necessary stylesheet links in the initial Twig document. For system mode, use a minimal CSP-compatible early bootstrap or equivalent approach to resolve prefers-color-scheme before first paint, with both candidate variants available. Do not wait for Stimulus connect after the page is visible to correct a full-screen light/dark flash. Treat font-display swapping separately from a wrong-colour theme flash.

Handle normal navigation, reload and browser back/forward. If the repository already uses Turbo, also handle its head management, cached snapshots and reconnect lifecycle; do not add Turbo for this feature. Remove obsolete listeners and avoid duplicate stylesheet/controller registrations.

Theme IDs, revisions, variant IDs and asset requests must be validated and authorized. Private theme CSS must not be publicly cacheable. Avoid sharing personalized initial HTML through a shared public cache. When a selected theme becomes unavailable, deleted or inaccessible, fall back cleanly to the default and let the user know; never leave an unreadable page or a broken saved preference.

## 5. Creating, editing and trying themes

Extend the existing editor with **New theme**, **Edit**, **Duplicate**, variant selection, **Save theme**, **Save & Apply**, and **Try on application**. Adapt labels to existing UI conventions. Do not build a second editor beside the working one.

New themes support a name, stable identifier, light/dark variant relationship, semantic palette, fonts and the typography/geometry settings already supported. Provide a useful valid starting palette. Validate duplicate names/identifiers according to current conventions. Show accessible validation errors without discarding entered data. Respect permissions for built-in/imported themes; offer Duplicate to customize when they are read-only.

Define the actions precisely:

| Action | Theme data | Application appearance |
| --- | --- | --- |
| Apply theme | Uses a saved valid revision | Applies and saves my personal selection |
| Save theme | Persists valid edits/new revision | Keeps my previously applied snapshot |
| Save & Apply | Saves edits, prepares valid assets, then selects the saved revision | Updates my application and preference |
| Try on application | Keeps edits unsaved | Temporary trial in this editor tab only |
| Cancel/Revert trial | Does not save the draft | Restores the pre-trial appearance |

While Try on application is enabled, debounce valid token edits into the studio's appearance. Show a clear **Trying unsaved changes** banner with Revert and Save & Apply. Capture the pre-trial selection once; consecutive edits must not replace the rollback target. Invalid input leaves the last valid trial visible with an error, without silently saving it.

A draft trial must not update account preferences, other tabs or other users. Leaving the editor or reloading restores the saved appearance. Integrate with existing unsaved-changes handling; do not lose draft text just because previewing is reverted. Save theme while a trial is active saves the draft and ends the trial, restoring the previously applied snapshot. Save & Apply commits the newly saved revision and ends the trial.

If theme saving succeeds but asset preparation or preference saving fails, retain the saved theme, keep the old application appearance, and offer to retry Apply. Do not report the whole action as successful or discard the successfully saved edit. Use current optimistic-lock/revision handling to prevent concurrent editor overwrites.

Newly saved/imported themes and newly added variants must appear immediately in the gallery and application selector without a container restart or deployment. Renaming a theme must not break preference references. Editing creates a new revision while users on earlier snapshots remain stable until applying the new one. Deletion/archive behaviour must remain compatible with existing immutable releases and dependencies.

## 6. Acceptance checks

Use the existing PHPUnit and browser test setup. Run against production-built assets as well as development mode where relevant.

1. Apply two visibly different saved themes and verify the actual root, header, sidebar, forms, dialogs and typography change; catalogue preview selection remains independent.
2. Moon activates dark and sun activates light within the same family; icon, accessible label, root mode and native control colour scheme stay synchronized.
3. Explicit mode ignores subsequent OS changes; system mode follows them and a click switches to an explicit preference.
4. Preferences survive navigation/reload/sign-in as designed, without a full-screen wrong-theme flash. Account A's selection never overwrites account B's.
5. Create a new family after production assets are compiled, save both variants and apply them without rebuilding the image. Verify real computed token values, not only a changed data-theme string.
6. Edit an active theme: Save preserves the old applied snapshot; Save & Apply selects the new one. Another user on the old snapshot stays unchanged.
7. Try unsaved changes, edit repeatedly, switch trial variants and Revert; the exact original appearance returns. Navigation/reload does not persist a trial.
8. Test invalid tokens, inaccessible/deleted themes, single-variant themes, missing fonts and failed CSS/preference requests. The interface remains usable with honest status messages.
9. Rapidly apply A/B/C with deliberately reordered responses; only the latest confirmed request wins and rollback state is correct.
10. Verify keyboard focus, button labels and mobile control usability; Restore default is always reachable.
11. Verify private asset authorization, CSRF, CSS/token injection rejection, no added iframe privileges and no remote font dependency.
12. Existing theme imports/exports, font files, components and package checksums still work after any model/schema changes. Extend round-trip coverage for variant relationships where the export format changes.

Complete the feature and run the relevant checks. Report changed behaviour, migrations/configuration, commands and actual test results, and any remaining gaps. Update project documentation without resetting completed work. Do not claim a feature works solely because the button or database field exists. Do not publish or deploy externally unless requested.

Reference documentation, to match against installed versions:

- daisyUI themes: https://daisyui.com/docs/themes/
- daisyUI theme-controller behaviour: https://daisyui.com/components/theme-controller/
- System colour scheme: https://developer.mozilla.org/en-US/docs/Web/CSS/@media/prefers-color-scheme
