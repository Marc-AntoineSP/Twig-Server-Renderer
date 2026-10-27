# Theme detail component showcase

Open a theme from **Themes** at http://localhost:8095/themes. Its detail page now contains
**Component showcase**, with independent **Showcase light / Showcase dark** links for the
variants already recorded in that family. The current variant is highlighted. A single
variant remains usable without inventing an opposite palette.

The showcase always uses the detail page's saved draft revision, even when the studio
header is using another family or mode. Existing Apply theme, Edit, Duplicate/Edit,
release/export and application light/dark actions retain their meanings. Viewing,
filtering or interacting with the examples never applies a theme or saves preferences.

Use **Component category** to narrow the examples and **Find a component** to search their
names/styles. Clearing the search and choosing All categories restores the complete
catalogue. Examples use one column on small screens and two in a wide preview. Long tables
scroll inside their own container; overlays stay inside the preview, with Close/Escape
and timed/dismissible toast recovery.

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
carousel navigation and accessible drawer focus/escape. Drawer focus waits for daisyUI's
opening transitions; backdrop closing cannot accidentally toggle the checkbox open again.
Timers and listeners are cleaned up on disconnect.

All demonstration buttons are `type="button"`. File selection stays in the browser;
no form submits, upload endpoint, theme mutation or persistence request is connected to
the examples. Radio groups and input/dialog IDs are unique within each preview. Native
dialogs trap focus; the drawer traps Tab, closes with Escape/backdrop/Close and restores
its opener. Toasts expire after 4.5 seconds and have a Dismiss action. Tooltips expose the
same explanation to keyboard and screen-reader users. Animated decoration honours reduced
motion; the countdown starts only when explicitly activated.

## Rendering, CSS and isolation

`ThemeComponentShowcase` renders only repository-owned Twig partials and macros beneath
`templates/theme/showcase`. Theme values are escaped scalars or validated typed tokens;
user component Twig, imported CSS and imported JavaScript never execute in the studio.
It reuses `ThemeCssGenerator::applicationCss()` and `DocumentAssembler`, including existing
font resolution, WOFF2 data embedding, nonce scripts and preview CSP. No second generator,
theme model, migration, personal setting or catalogue fixture is created for this view.

The complete document is rendered on the server and placed in the detail page's `srcdoc`
iframe with the existing **sandbox="allow-scripts"** permission only. There is no
same-origin, forms, popup or navigation privilege. The root has the displayed theme's
hash-scoped tokens and effective light/dark mode; dialogs, drawers, toasts, tooltips and
native controls inherit the same root. Font and decorative image bytes are embedded;
the preview makes no HTTP requests. The studio header's theme cannot reach into it.

`make assets` now builds the existing studio CSS plus `assets/styles/showcase.css` and the
bundled showcase Stimulus runtime. Their source classes are complete literal names in
the Twig/JS sources, including each semantic colour/size. Production copies the actual
built outputs into its immutable image before AssetMapper compilation. At request time
only validated theme declarations and font data are added; no Tailwind compilation,
runtime manifest write, worker job or redeployment is needed for a new saved theme.

## Omissions and browser limits

- **Calendar** in this version supplies adapters for external calendar libraries. Those
  dependencies are not installed; no third-party calendar or React package is added.
- **Megamenu** is designed as a single full-page desktop navigation system. The embedded
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
results are retained under `var/component-showcase-verification`.
