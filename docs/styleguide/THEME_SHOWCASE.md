# Theme detail component showcase

Open a theme from **Themes** at http://localhost:8095/themes. Its detail page now contains
**Component showcase**, with independent **Showcase light / Showcase dark** links for the
variants already recorded in that family. The current variant is highlighted. A single
variant remains usable without inventing an opposite palette.

The showcase always uses the detail page's saved draft revision, even when the studio
header is using another family or mode. Apply to application, Edit theme, Duplicate/Edit,
release/export and application light/dark actions retain their meanings. Viewing,
filtering or interacting with the examples never applies a theme or saves preferences.

Use **Component category** to narrow the examples and **Find a component** to search their
names/styles. Clearing the search and choosing All categories restores the complete
catalogue. The detail page fills the available width with a 300px left sidebar and a
flexible, minimum-width-zero showcase column. On narrow screens the native **Theme
details** disclosure starts closed; open it for Apply, variants, metadata and downloads.
Desktop opens it automatically. All other routes, including create/edit, keep the
existing constrained layout. Reference/provenance, contrast, draft downloads and release
history expand within the sidebar; nothing is stacked beneath the catalogue.

Examples use a grid based on the actual available width. Tables, navbars, heroes,
footers, browser mockups and code span the grid. Long tables/code scroll locally; the
page uses normal document scrolling, with no showcase iframe, height cap or inner scroll
panel. `#component-showcase` and category fragment anchors remain useful. The search
controls stay with the catalogue.

## Theme action map

Gallery cards and the detail sidebar share the same translated action partial. The
swatches and inline showcase identify the saved **draft revision, name and mode**.
The separate editor action identifies the **published name, version and mode**; its
colors can differ from the draft. A proposed draft version is not a published version.

| Visible action | Route / method | Target and permission | Saved effect | Destination |
| --- | --- | --- | --- | --- |
| Apply to application | Existing `POST /api/v1/appearance/prepare`, then `/commit` after CSS loads | Selected Theme revision and family variants; signed-in reader; API CSRF | Current user's appearance snapshots; existing light/dark/system preference retained | Current page, restyled |
| Browse component showcase | `GET /themes/{selectedVariantId}#component-showcase` (may retain `returnTo`) | Selected variant's saved draft; reader | None; no renderer preset, appearance or content write | Existing inline examples |
| Preview published theme in component editor | `POST /themes/{id}/apply` | Exact displayed ThemeRelease belonging to that Theme; editor; `theme-apply` CSRF | Current user's sample-component renderer theme, body/heading font selection, text size and root size; other settings preserved | 303 to sample `/catalogue/{itemId}/edit`; notice identifies release and saved effect |
| Showcase light / Showcase dark | `GET /themes/{variantId}#component-showcase` | Explicit family variant; reader | None | That variant's saved draft examples |
| Edit theme / Duplicate/Edit | Existing GET editor / CSRF-protected POST duplicate | Existing editor and managed/built-in rules | Existing explicit save / independent copy semantics | Theme editor |

The existing sample component is created lazily on the first published-editor POST if
it is absent. Read-only showcase browsing never calls that creation path.

The published form includes `releaseId`, so a newer release appearing between viewing
and submitting cannot change the displayed selection. The server checks the release's
Theme identity. Existing POST clients without that field still select the latest
publication by creation time, with ID as a deterministic tie breaker. Version order
does not replace publication order. Gallery metadata is loaded in a bounded batch for
the twelve visible cards; it does not query releases once per card.

Draft-only variants keep their read-only showcase. The unavailable published preview
explains that editors can publish from **Edit theme**, or **Duplicate/Edit** a managed/
built-in theme first. Readers retain browsing and personal application appearance;
renderer-preset mutations still require editor access. A missing or mismatched release
returns a usable 422 page linking back to the draft showcase and writes no settings.
No route was converted into a mutating GET, and no storage migration is required.

## Included examples

There are **65 example panels in seven categories**, using the installed **daisyUI 5.7.50**.

| Category | Examples |
| --- | --- |
| Actions — 7 panels | Buttons, button groups, dropdowns, modals, swaps, floating actions/speed dial and links |
| Forms — 13 panels | Text inputs, textareas, selects, checkboxes, radios, toggles, ranges, file inputs, ratings, fieldsets/labels (including floating labels), validation, filter radios and OTP |
| Navigation — 7 panels | Navbar, menus/submenus, breadcrumbs, three tab styles, pagination, steps and dock navigation |
| Data display — 12 panels | Cards, badges, avatars/groups, stats, tables, lists, timelines, chat bubbles, keyboard shortcuts, status indicators, countdown and rotating text |
| Feedback — 7 panels | Alerts, progress bars, radial progress, six loading shapes, skeletons, toasts and tooltips |
| Layout — 15 panels | Accordions, independent collapses, dividers, joined controls, indicators, heroes, footers, drawers, carousels, stacks, masks, diff comparison, aura, hover 3D and hover gallery |
| Mockups — 4 panels | Browser, desktop window, phone and code/terminal |

Buttons, badges, inputs, checkboxes, radios, toggles, ranges, progress and status indicators
cover the eight semantic appearances: primary, secondary, accent, neutral, information,
success, warning and error. Relevant examples also show supported XS–XL sizes, outline,
dash, soft, ghost, selected, checked, disabled, loading and explicit/native validation
states. Controls have meaningful labels and content; code, headings and body text retain
their separate theme font roles, real weights and styles.

Tabs use the existing portable keyboard controller (Left/Right/Home/End) and modals reuse
the existing native-dialog controller. Accordions, collapses, radio groups, swaps, inputs,
ratings and validation use native controls/CSS. A small showcase Stimulus controller owns
filtering, local selection/output, slider feedback, file-name feedback, countdown, toast,
carousel navigation and accessible drawer focus/escape. The drawer now uses a native
modal dialog with a side panel: background controls are inert while it is open, and its
Close, backdrop, Escape and Tab loop restore the opener.
Timers and listeners are cleaned up on disconnect.

All demonstration buttons are `type="button"`. File selection stays in the browser;
no form submits, upload endpoint, theme mutation or persistence request is connected to
the examples. Radio groups and input/dialog IDs are unique within the page. Native
dialogs trap focus; the drawer traps Tab, closes with Escape/backdrop/Close and restores
its opener. Toasts expire after 4.5 seconds and have a Dismiss action. Tooltips expose the
same explanation to keyboard and screen-reader users. Animated decoration honours reduced
motion; the countdown starts only when explicitly activated.

## Rendering, CSS and isolation

The application renders only repository-owned Twig partials and macros beneath
`templates/theme/showcase` directly into its existing `<main>`. There are no nested
HTML/head/body/main elements or iframe resize messages. User-created/imported component
Twig, CSS and JavaScript keep the existing worker renderer and opaque sandbox in the
catalogue and theme editors. That boundary is unchanged, including the trusted
`about:srcdoc` base for native preview fragments.

`ThemeComponentShowcase` reuses the existing validator, canonical documents,
`ThemeCssGenerator` and `ThemeFontAssets::embed()` pipeline. The generator shares its
application token implementation with a fixed `#component-showcase` selector and a
validated snapshot hash; its showcase output has no resets, global root rules, imports
or utility rebuilds. Local font-face names are namespaced by the snapshot so a theme
cannot replace the shell's face for the same font family. The saved/exported document
is never changed. Selected typography, font weights/styles, semantic colours, geometry,
spacing scale and light/dark scheme are local to the showcase. Rem-based geometry is
resolved against the selected theme's root size.

A reader-authorized, content-hashed `/themes/{id}/showcase/{hash}.css` link loads these
validated declarations and registered embedded WOFF2 bytes with integrity verification
and private caching. Changed or invalid hashes return 404; anonymous requests require
login. Viewing creates no catalogue item, theme revision, preference or preview job.
Native dialogs/drawers remain DOM descendants of the showcase even in the top layer;
toasts and tooltips inherit its tokens. Event actions are bound only to that root, so
sidebar forms and application controls retain their normal handlers and CSRF protection.

`make assets` builds the single studio utility stylesheet, including every showcase Twig
and runtime class, and an ESM export of the existing trusted showcase/portable
controllers. AssetMapper imports that bundle into the **existing application Stimulus
instance**; it never starts another instance. Production builds the same files before
AssetMapper compilation. New saved themes need only validated token CSS/font bytes;
there is no worker compilation, manifest write, migration or redeployment for viewing.

## Omissions and browser limits

- **Calendar** in this version supplies adapters for external calendar libraries. Those
  dependencies are not installed; no third-party calendar or React package is added.
- **Megamenu** is designed as a single full-page desktop navigation system. The component
  catalogue uses navbar/menu/dropdown/drawer examples instead of installing a competing
  full-page navigation layer.
- **Theme controller** is deliberately omitted: the application already has its own
  family-aware appearance controller. A demo checkbox must not override the selected
  showcase palette or the user's saved application appearance.
- Hover 3D and hover gallery are decorative pointer effects, with readable static content
  on touch/keyboard. Diff uses the installed native CSS resize/focus pattern. Native OTP
  presentation uses modern CSS such as field sizing. Cosmetic support can vary between
  browsers; the actual verified browser is Chromium.
- The phone's dark device frame and two local SVG illustrations are intentional content
  colours. Its screen and surrounding controls use the selected semantic palette.
- This is a broad interactive component reference, not a full product workflow or a claim
  that every possible daisyUI modifier permutation or arbitrary custom palette meets WCAG.

The installed component sources were checked against the matching official
[component catalogue](https://daisyui.com/components/),
[native validation](https://daisyui.com/components/validator/),
[tabs](https://daisyui.com/components/tabs/) and
[drawer](https://daisyui.com/components/drawer/) documentation.

## Verification commands

```sh
make assets
make check
docker compose exec -T -e APP_ENV=test app php bin/phpunit
make browser
make theme-showcase-production
```

The production command reuses the isolated appearance fixture, builds actual production
images and runs both appearance and showcase browser specs. It imports three real font
families from verified offline staging, creates new themes after asset compilation and
checks that the read-only production manifest stays unchanged. Temporary fixture accounts
are disabled and only its own containers are stopped; existing application data and
production instances remain intact.

Actual results and corrected failures are recorded in [VERIFICATION.md](VERIFICATION.md)
and `agent-kit/STATUS.md`. Screenshots, FontFace/CSS evidence and row-hash preservation
results are retained under `var/theme-layout-verification` (M12) and the historical
`var/component-showcase-verification` (M11).

## M12 changed files

- Page layout: `templates/base.html.twig`, `templates/theme/show.html.twig`, new
  `templates/theme/_detail_sidebar.html.twig`, compact `templates/theme/_provenance.html.twig`.
- Trusted examples: `templates/theme/showcase/_catalogue.html.twig`, `_macros.html.twig`,
  `_layout.html.twig` and `_actions.html.twig`; existing category partials remain in use.
- Scoped token/font delivery: `src/Controller/ThemeController.php`,
  `src/Theme/ThemeComponentShowcase.php`, `packages/catalogue-contracts/src/ThemeCssGenerator.php`.
- Interaction/layout assets: new `assets/controllers/theme_details_controller.js`, new
  `assets/styles/showcase.layout.css`, `assets/stimulus_bootstrap.js`,
  `fixtures/theme-showcase/bootstrap.js` and `controller.js`.
- Build: `assets/styles/studio.input.css`, removed standalone/reset-bearing
  `assets/styles/showcase.input.css`, `package.json`, `docker/frankenphp/Dockerfile`,
  `.gitignore`. Dependency versions and lockfiles are unchanged.
- Acceptance: `tests/Functional/ThemeComponentShowcaseTest.php`,
  `tests/Browser/theme_components.spec.js`, two shell-header selectors in
  `tests/Browser/appearance.spec.js` and a matching sequential-build deadline in
  `tests/Browser/editor.spec.js`; architecture, security, milestones, verification
  and `agent-kit/STATUS.md` record the new trusted-rendering boundary and actual results.
