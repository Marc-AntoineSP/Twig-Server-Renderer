# Product specification

## Outcome

A designer/developer can build a reusable UI library using Twig, HTML, Tailwind and
daisyUI; attach Stimulus behaviour; compare graphic palettes; combine pieces into
blocks and layouts; and install the result in another Symfony project.

Component means a portable Twig view with declared JSON props, defaults, examples,
assets, optional Stimulus controllers and dependencies. It is not required to be
a Symfony UX PHP-backed component. The studio can use UX internally, but portable
packages must not depend on application-specific PHP classes.

Block means a reusable composition of components. Layout means a larger composition
of blocks, containers and components. A shared tree representation avoids maintaining
three unrelated rendering systems. All three have names, drafts and immutable releases.

## Assumptions

- One private catalogue initially, accessible by authenticated people.
- Editor roles: ROLE_EDITOR creates/edits/previews/publishes content; ROLE_ADMIN also
  manages accounts and read tokens. ROLE_READER can browse, evaluate sandboxed published examples and download releases.
- Start with a keyboard-accessible tree builder; drag and drop can be added later.
- Explicit Save creates a persisted draft revision; preview updates are transient.
- Source ZIP/YAML imports are supported. Arbitrary URLs and complete foreign websites
  are not automatically transformed into components.
- Runtime framework compatibility targets Symfony 8 + Twig 3 for consumers. A plain
  Twig export may work elsewhere, but that is not the first automated integration target.

## Screens

### Catalogue

Search by name/title/tag/kind; sort by update time/name; show preview thumbnail,
release version, draft status, description and dependency count. Toggle components,
blocks and layouts. Pagination queries the database, not every package file.
Actions: New, Import, Duplicate, Open, Download, Copy installation command.

### Editor

Three regions: asset/dependency list on the left; Twig/JS/props/example tabs in the
middle; preview and property controls on the right. Provide a stacked mobile layout.
Use a native textarea first; introduce CodeMirror only if it improves the delivered
workflow. Syntax highlighting is optional; unsaved status, error location and usable
keyboard navigation are mandatory.

A 300 ms debounce submits a new preview request. Display rendering/building/error
states. Cancel obsolete browser requests and ignore out-of-order results. Last-good
preview may remain visible with an explicit stale badge. Never label it current.
Saving uses the last known revision so concurrent edits cannot silently overwrite.

### Renderer / design laboratory

Display any component, block or layout. Controls:

- Viewport widths: 375, 768, 1280 px and a bounded custom width (320–1920).
- Zoom: 50%, 75%, 100%; zoom is visual scaling, separate from CSS viewport width.
- Theme preset, light/dark colour scheme, surface/background and device frame toggle.
- Body font and heading font; initially system sans, serif and monospace.
- Default text class: text-xs, text-sm, text-base, text-lg, text-xl.
- Root font size: 12–24 px. Explain that rem-based spacing also scales.
- Prop form generated from the declared schema, plus raw JSON editor.
- Reset to example; compare two presets side by side; persist renderer presets.

Default text size changes inheritance. A component's explicit text-2xl stays explicit.
Do not pretend a root text-base override globally rewrites heading classes.
Keep studio UI styles independent of preview styling.

### Theme editor

Edit a complete daisyUI semantic palette and its content colours, font choices,
text defaults, selector/field/box radius, base field/selector size, border, depth
and noise. Offer separate named light and dark themes in a design-system preset.
Palette edits update CSS custom properties without rebuilding all utility classes;
changing a class, font asset or build configuration may require a rebuild.
Store a typed canonical theme document; validate it before saving/exporting.
Provide colour-pair contrast measurements as guidance, not a universal accessibility
certificate. Focus, keyboard and motion behaviour still need their own checks.

### Composition builder

Tree rows support add, remove, duplicate, reorder, indent/outdent and selection.
Reference insertion/replacement uses authorized server search with bounded pages, personal
favorites, kind and explicit immutable versions. A native search dialog preserves keyboard
focus and unsaved work; exact pinned identities remain visible outside active filters.
Previews are explicit and isolated. Replacement preserves props unless resetting to the
selected release's defaults is deliberate. See REFERENCE_PICKER.md.
Containers offer a bounded tag and literal utility classes. References target exact
published component, block or layout versions and supply props. Text nodes contain escaped text.
The preview uses the same renderer as standalone components.
Use concrete props in v1; arbitrary expression binding is deferred.
Composition mode owns composition.json and generated Twig. Generated Twig is read-only.
An explicit Convert to source action duplicates the item into source mode; do not
silently promise bidirectional conversion between arbitrary Twig and a visual tree.

## Export choices

1. Source package: Twig, Stimulus, props schemas/defaults, composition source if any,
   assets, manifest, licence and an installation README.
2. Resolved collection ZIP: the selected release and all transitive dependency packages,
   with an index and exact checksums, usable without the registry.
3. Static preview ZIP: index.html, compiled CSS/JS and permitted assets for the chosen
   props/theme. It is an HTML snapshot, not a live Symfony backend or Twig runtime.
4. Theme ZIP: theme.json, editable Tailwind app.css entry, mergeable theme fragment,
   typography bridge, compiled preview.css, required font files/licences and README.

Downloadable app.css is Tailwind source and must be compiled; compiled preview.css is
ready for browser use. Show both labels clearly. Export must remain usable without
the catalogue server and without its authentication cookies or asset URLs.

## Release boundary

M01–M05 form the authoring MVP; M06–M07 make it portable; M08 makes it deployable.
The full request is complete only after M08 and the acceptance checklist pass.
Defer multiplayer editing, visual CSS painting, arbitrary third-party JS packages,
custom PHP components, full Figma import, Composer-per-component publication, a public
marketplace and automatic deployment. Do not replace required imports or installation
with copy-to-clipboard as the final implementation.
