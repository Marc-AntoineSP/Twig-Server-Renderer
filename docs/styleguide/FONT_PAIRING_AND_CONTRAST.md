# Font pairing and draft contrast

The theme editor includes a **Font pairing proposal** below its editable fields. The
font library links to this workspace in a new theme; existing themes offer it under
Edit theme. Search the installed families and choose body, heading and code families,
integer weights and normal/italic styles. Available ranges, subsets, licence and source
version remain visible. Copy current editor fonts explicitly when changing variants.

**Preview pairing** loads the requested real faces and sample subsets into an opaque
sandboxed frame. Selection alone downloads no pairing font. English and French samples
include accented characters, headings, reading text, a field/button and code. Additional
sample characters are escaped text. The sample can use light or dark presentation
independently of application appearance. It reuses the renderer's trusted typography
sample, FontLibrary, FontRegistry, ThemeFontAssets and DocumentAssembler.

No synthetic bold or italic is used. An unavailable family or weight/style prevents a
new preview and disables transfer. Declared Unicode ranges identify characters that
will require system fallback. English/French coverage was verified during catalogue
ingestion; a range match alone does not prove every other glyph exists. Device system
families cannot promise a particular face or coverage. The frame reports actual decoding
failures and active system fallback. Nothing requests a third-party runtime font service.

**Use pairing in theme fields** transfers only the nine existing family/weight/style
values and their full registered family definitions (preview subset pruning is not
persisted). This also supports a theme that previously bundled only normal faces. It
preserves other unsaved edits and enters the existing dirty state. Proposal
controls themselves are transient. Save theme persists the draft; Save & Apply also
updates personal application appearance. Try on application remains the existing explicit
trial action (and continues tracking edits while that trial is active). No second theme
settings format, font store, acquisition workflow or migration is introduced.

**Current draft contrast** uses the existing PHP contrast engine and the same eleven
semantic background/content pairs as saved calculations. Changes debounce for 300 ms.
The table labels current draft and saved baseline separately; a successful Save refreshes
the baseline, and variant switching uses that variant's baseline. Pending/network/error
states never present an old result as current. Request cancellation plus sequence checks
prevent delayed responses from replacing newer drafts. Each result links to both editable
tokens and a focusable representative example in a separate opaque frame. On small
screens, the results region scrolls horizontally and accepts keyboard arrow keys,
keeping text readable without widening the page.

The canonical theme format supports opaque `#RRGGBB`, case insensitive. Incomplete text,
alpha hex, rgba, CSS variables, color functions and other formats produce **Cannot
evaluate** with field-associated guidance. They are never converted to opaque colours or
composited over an invented background. Each evaluable pair uses exactly its named solid
background and foreground. The normal save validator still enforces the existing format;
low contrast recommendations do not block saving and colours are never rewritten.

The thresholds follow [WCAG 2.2 text contrast guidance](https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html):
4.5:1 for ordinary text and 3:1 for large text (24 CSS px, or 18⅔ CSS px bold). Results
compare full precision before rounding the displayed ratio to two decimals. A meaningful
content-colour icon on the paired surface uses the 3:1 guidance from
[non-text contrast](https://www.w3.org/WAI/WCAG22/Understanding/non-text-contrast.html).
This is not a measurement of control boundaries against adjacent surfaces, hover/focus
states, gradients, opacity, or complete layouts. A passing pair does not certify an
interface's accessibility. Keyboard, focus, motion and actual rendered content still
require assessment.

The editor-only POST endpoints `/api/v1/fonts/pairing` and `/api/v1/themes/contrast`
require the session's API CSRF token. They accept bounded typed data and return private,
no-store diagnostics without theme, release or appearance writes. The pairing endpoint
resolves installed registry definitions, never supplied CSS or uploaded font metadata.
Contrast styles contain only strictly accepted six-digit hex values. Both previews keep
the existing denied network CSP and `sandbox="allow-scripts"`, without same-origin access.

Run checks in a verified disposable environment as described in BROWSER_TESTS.md.
Focused browser coverage is `tests/Browser/font_pairing_contrast.spec.js`, alongside
`fonts.spec.js`, `theme.spec.js` and `theme_actions.spec.js`. The immutable production
harness accepts the new spec. Exact commands, actual results and cleanup evidence for
this change are recorded in agent-kit/STATUS.md.
