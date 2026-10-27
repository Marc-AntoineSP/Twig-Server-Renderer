# Codex task: self-hosted font library and website-inspired themes

The component catalogue and editor are already built and functional. Extend the current application with a useful, locally hosted font library and a collection of website-inspired theme presets. Implement this end to end, including downloading real font files, collecting real design evidence, importing the data, exposing it in the existing UI, and verifying exports.

## 1. Inspect the current implementation

Read AGENTS.md and relevant project documentation, then inspect the actual code, database schema, font handling, theme storage, renderer, asset compiler, package formats, import/export commands and Docker services. The current repository is authoritative: old milestone notes may be stale.

Reuse existing services and conventions. Add migrations and backward-compatible contract changes only where needed. Preserve existing components, user themes, uploads, saved presets and unrelated changes. Keep Symfony/Twig SSR, Tailwind/daisyUI, Stimulus, AssetMapper where already used, and Docker. Do not rebuild the application or introduce a second theme system or frontend framework.

Proceed through the work with short progress updates. Make routine implementation choices independently. Do not stop after presenting a plan, generating metadata, or downloading files without integrating them.

## 2. Build a curated, expandable font library

Interpret “most used and user friendly” as a broad, practical collection for readable interfaces, body text, headings and code. The following 42 families are the initial candidates, not a claimed statistical popularity ranking. Verify current family names, available packages, redistribution terms, faces and character coverage before ingestion.

| Category | Initial candidates |
| --- | --- |
| Sans-serif / interface | Inter, Geist, Roboto, Open Sans, Lato, Montserrat, Poppins, Nunito Sans, Source Sans 3, Noto Sans, Work Sans, DM Sans, Manrope, IBM Plex Sans, Public Sans, Figtree, Outfit, Plus Jakarta Sans, Urbanist, Rubik, Mulish, Ubuntu, Barlow, Atkinson Hyperlegible, Lexend |
| Serif / editorial / display | Merriweather, Lora, Source Serif 4, Noto Serif, Libre Baskerville, Playfair Display, DM Serif Display, Cormorant Garamond, EB Garamond, Roboto Slab |
| Monospace | JetBrains Mono, Geist Mono, Fira Code, Source Code Pro, IBM Plex Mono, Roboto Mono, Inconsolata |

Use official font projects or Google Fonts upstream as the licensing/source authority; prefer versioned Fontsource packages for convenient WOFF2 distribution when suitable. Verify the mapping to each upstream family. Pin exact resolved versions and retain source URLs, checksums and licence files. Reuse existing package tooling; download through a Dockerized maintenance/build task, with third-party install scripts disabled where supported. Do not install font packages dynamically from a public web request.

Actually download and register each eligible family. If one is unavailable or unsuitable, report that specific exception and keep working on the rest. Do not silently substitute another family or claim a fallback font is the installed one. Do not scrape proprietary font binaries from websites. For unavailable commercial/system fonts, keep a system fallback or select a clearly identified redistributable alternative.

Prefer variable WOFF2 when available; otherwise include supported normal weights 400, 500, 600 and 700, plus useful real italic faces where available. Preserve real weight/style/axis metadata and avoid fabricated bold/italic options. Support a full variable weight range through its actual declared range. Extra axes can have sensible defaults if the UI does not yet expose them.

Include the subsets required for French and English, checking actual glyph coverage, including accents, œ/Œ, ç, €, typographic punctuation and common numerals. Allow other scripts to be added through the same importer. Respect existing unicode-range declarations; do not assume that a subset label alone guarantees coverage. Font choice alone is not an accessibility guarantee.

Store family identity separately from individual font assets. Capture category/tags, source/version, licence, weight/style/axes, subset/unicode range, byte count, checksum and local asset reference using existing storage conventions. Imports must be idempotent and resumable. A failed download must not replace a previously valid asset; validate bytes before atomic registration. Preserve font assets through container restarts and rebuilds using the project's persistent or immutable asset strategy.

Integrate fonts into the existing theme editor and renderer:

- Search by family, filter by sans/serif/mono/display and preview sample text.
- Separate body, heading and code font choices; preserve existing typography settings.
- Display supported weights/styles and a small source/licence detail panel.
- Allow editable specimen text and show French/English examples.
- Update the live preview and persist the selection with the theme/preset.
- Use sensible system fallbacks and font-display: swap or an explicitly justified alternative.
- Keep the catalogue available locally while loading browser font bytes only for selected or visible preview families. A font menu must not eagerly fetch all families or every weight.
- Do not preload every face. Measure initial requests/bytes and document the policy.

Keep studio typography independent of preview typography. If the renderer uses an opaque-origin srcdoc iframe, retain that isolation: embed the selected fonts or use the existing approved delivery mechanism. Test CSP/CORS behaviour. Do not add allow-same-origin just to make fonts work.

Exports must include only the fonts needed by the selected theme/layout, their licences and correct relative @font-face URLs. Include them in file inventories/checksums. Exported previews and consumer-installed packages must work without Google Fonts, Fontsource CDNs or the studio server at runtime. Verify weight/style/axis selections survive export and reimport.

## 3. Collect website-inspired theme presets

Create a first collection targeting these 20 references:

1. GitHub
2. GitLab
3. Stripe
4. Linear
5. Vercel
6. Notion
7. Slack
8. Discord
9. Spotify
10. YouTube
11. Twitch
12. Reddit
13. Stack Overflow
14. Airbnb
15. Dropbox
16. Figma
17. Wikipedia
18. GOV.UK
19. Shopify
20. Atlassian

These are recognisable design references, not an asserted traffic ranking. Name presets clearly, for example “GitHub-inspired Light”, “Spotify-inspired Dark” and “Stripe-inspired”. Use unique stable slugs such as github-inspired-light. Record the precise reference surface: product UI, public marketing site or published design system. Do not merge them into an unexplained claim about the entire brand.

Collect evidence from current public sources. Preferred order:

1. Official design-system documentation and available, appropriately licensed token datasets.
2. The reference website's publicly accessible CSS/custom properties.
3. Computed styles from representative public pages using Dockerized browser automation, where needed and permitted.

For example, investigate GitHub Primer and GitLab Pajamas for their product UI; check the current Atlassian, Shopify and GOV.UK design documentation instead of assuming historic documentation URLs or package formats still apply. Verify sources as you go.

Extract palette, typography and recurring visual properties. Prefer semantic tokens and actual interactive element styles over counting every colour in a stylesheet. Account for transparency and computed background colour. A logo colour alone is not enough to define a complete theme.

For each family, record source URL(s), retrieval date, reference surface/mode, source version or revision when available, extraction method and attribution/licence information where applicable. Retain a small evidence record linking observed properties/tokens to the generated theme.

Separate three kinds of values in provenance:

- Observed: read directly from a documented token or inspected element.
- Adapted: adjusted for the studio's component semantics or readability.
- Derived: designed to complete missing semantic colours, typography or a new mode.

Do not invent an “extracted” palette when a page could not be accessed. If a source is blocked, use another official public source or mark the reference pending with the actual reason. Implement the available presets and infrastructure without being blocked by one website. Do not bypass sign-in, access controls or anti-bot challenges. Use bounded requests, caching and modest concurrency, and honour source access policies.

Provide source-observed light/dark modes when available. An additional mode may be designed for usefulness, but label it as a derived adaptation rather than claiming the website uses it. Avoid obtaining 40 superficial presets merely by inverting 20 palettes.

Use website names descriptively. These are independent theme presets, not official products or endorsements. Collect design tokens rather than copying whole branded pages, logos, illustrations or proprietary font files. Preserve applicable attribution when reusing licensed datasets; public visibility alone does not establish a licence to redistribute downloaded assets.

## 4. Map each preset to a complete usable theme

Translate every preset into the existing canonical theme format, matching the installed Tailwind/daisyUI versions. Reuse the project's CSS generator and import/export pipeline.

Populate the full semantic palette: base-100/200/300 and base-content; primary, secondary, accent and neutral with their content colours; info, success, warning and error with their content colours. Include colour scheme, font selections, body size/line height, supported radius, field/selector size, border and effects settings. Do not globally change the studio's default theme when importing presets.

Preserve the project's existing colour format. If it currently accepts only hex, convert supported source colours through a real colour parser with documented gamut/alpha handling, or introduce a backward-compatible format extension; do not bypass validation with raw CSS strings. Only generate supported tokens. Shadows, gradients or other source properties not representable in the current schema should be documented as omitted or added through an intentional schema extension.

Choose a suitable installed font when the exact source font cannot be redistributed. Record both the observed family and chosen substitute. Prefer a deliberate body/heading/code pairing over applying an ornamental heading family to all interface text.

Measure foreground/background contrast and report the checked pairs and ratios. Target at least 4.5:1 for ordinary text, 3:1 for qualifying large text, and relevant 3:1 non-text contrast cases. Inspect focus, hover, selected, disabled and error states separately. Keep source colours in provenance when adjusted for usability. Do not claim full WCAG compliance from palette checks alone.

Render every preset on the same existing component showcase: navigation/sidebar, buttons and states, inputs/selects, cards, badges, table, tabs, alerts, accordion, progress, modal, body copy and code. Check mobile and desktop layouts. Use the shared showcase for comparison rather than building a separate branded page per website.

Add the presets to the existing theme gallery with swatches, readable preview, reference/mode label, fonts and an Apply action. Offer Duplicate/Edit so users can create their own palette. Keep imported seeds distinguishable from user themes. Rerunning imports must not overwrite user-edited copies; updates to managed presets should produce a diff or a new revision using the existing version model.

## 5. Make acquisition repeatable

Use versioned declarative manifests for font sources and theme-reference sources. Add or extend Symfony commands equivalent to the following, adapting names to the existing CLI:

    styleguide:fonts:sync --collection=popular --dry-run
    styleguide:fonts:sync --collection=popular
    styleguide:fonts:sync --family=inter
    styleguide:themes:collect --collection=website-inspired --dry-run
    styleguide:themes:collect --collection=website-inspired

Document Docker/Makefile wrappers. CLI names are proposals to implement, not claims that these commands already exist. Collection should create validated candidates in staging, then apply them through the existing importer. Reuse existing token/style extraction tools when appropriate rather than writing a general website crawler.

Separate online acquisition from offline application runtime and preview compilation. Do not give restricted rendering/compiler workers general internet access. Remote collection belongs in a maintenance task with explicit allowed sources, timeouts, size limits and bounded retries. If source URLs are configurable, validate redirects and resolved addresses, reject private/link-local/metadata destinations, and restrict outbound browser subrequests too. Never execute downloaded website scripts in PHP/Node application context; browser inspection belongs in its isolated browser process.

Cache downloads and retain pinned manifests so installation/rebuilds are reproducible. Avoid expensive reacquisition when content/version has not changed. Network failure should produce actionable per-source results, not false success. New families and source adapters should be addable without rebuilding the entire feature.

## 6. Verification and completion

Use the project's existing test setup. Cover meaningful behaviour:

- Downloaded fonts have valid metadata, checksums, licence files and verified required glyph coverage.
- Selected fonts actually load in the studio preview; verify the corresponding bytes and loaded FontFace, not only computed font-family text or a fallback rendered under the requested name.
- Weight, italic, variable ranges and font switching behave correctly; broken fonts produce visible errors/fallback status.
- Font catalogue UI does not download the full library on first load.
- Imported themes contain valid complete tokens, recorded source evidence and explicit derived values.
- Showcase screenshots/visual review demonstrate the themes differ meaningfully and remain readable.
- Existing custom themes, components and previews still work after migrations/imports.
- Repeating sync is idempotent; a failed source does not corrupt previous assets or user edits.
- Export a theme/layout with fonts, import into a clean consumer or existing integration fixture, and verify appearance and controls.
- Block external network access during exported-preview/consumer checks and confirm no remote-font or studio-server requests remain.
- Verify relative asset URLs, package checksums, licence inclusion and restart/rebuild persistence.

Finish the working feature, not just scripts or placeholder catalogue rows. Report exact successful font-family/face counts, total disk size, imported theme-family/mode counts, skipped sources with reasons, commands run, test results and remaining limitations. List observed versus adapted/derived themes clearly. Update relevant project docs and progress notes without resetting earlier completed work. Do not publish or deploy externally unless separately requested.

Useful official starting points, to verify against current versions:

- Fontsource: https://fontsource.org/docs/getting-started/introduction
- Font packages/variants: https://fontsource.org/docs/getting-started/install
- Google Fonts sources and licences: https://github.com/google/fonts
- daisyUI theme format: https://daisyui.com/docs/themes/
- GitHub Primer colours: https://primer.style/product/primitives/color/
- WCAG text contrast: https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html
- WCAG non-text contrast: https://www.w3.org/WAI/WCAG22/Understanding/non-text-contrast.html
