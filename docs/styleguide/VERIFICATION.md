# Acceptance evidence

This maps the supplied A01–A38 scenarios to executed tests and operator checks. Detailed
commands, versions, failures and corrections are in agent-kit/STATUS.md. The final clean
checkout passes 108 tests/544 assertions, 25 Chromium flows and real consumer installation;
production recovery and the latest focused browser corrections also pass. No remote
CI/deployment/public certificate/Packagist result is implied by these local checks.

| Scenario | Evidence |
| --- | --- |
| A01 Fresh Docker setup | Fresh-clone bootstrap/check/test/browser/consumer; production init/build/migrate/health |
| A02 Save/restart | Browser restart.mjs; production restart and restored.mjs compare saved drafts/tokens |
| A03 Concurrent edit | CatalogueTest and catalogue browser two-tab 412 preservation |
| A04 Literal responsive edit | preview/editor browser tests at 375/768/1280; consumer production CSS |
| A05 Dynamic variants | ShowcaseTest publishes all button variants with complete literal class maps |
| A06 Stimulus/cleanup | showcase browser accordion, progress values/timer disconnect and alert dismissal |
| A07 Stale races | editor browser delayed old result; PreviewLifecycleTest cancellation/lease exclusion |
| A08 Diagnostics | WorkerBoundaryTest and production invalid-Twig browser flow retain last-good preview |
| A09 Props | PackageValidationTest/PortableContractsTest and real preview API invalid-value tests |
| A10 Template boundary | WorkerBoundaryTest app/filesystem/PHP/cycle cases; portable consumer Twig checks |
| A11 Render limits | Actual runaway/memory/output/CPU render tests, followed by successful worker calls |
| A12 Browser boundary | preview browser denies parent DOM, cookies and storage |
| A13 Message boundary | preview browser rejects wrong source/channel/schema/oversized messages |
| A14 CSS boundary | theme/preview browser compare studio styles against isolated edited themes |
| A15 Compiler boundary | Actual finite-import/plugin/malformed-CSS worker tests, escaped CSS policy and consumer rejection |
| A16 Palette/geometry | ThemeTest, ThemeImportTest and theme browser save/reload/publish |
| A17 Typography | editor/theme browser inherited text versus explicit heading sizes; system font bridge |
| A18 Token round-trip | ThemeImportTest/TransferCodecTest and canonical JSON/YAML exports/imports |
| A19 CSS round-trip | Generated CSS marker/token consistency and conflicting edited CSS rejection |
| A20 Composition | composition browser keyboard tree operations, nested release preview/export and consumer |
| A21 Graph validation | DependencyResolverTest/CompositionBoundsTest plus installer conflicting/pinned roots |
| A22 Literal Twig text | CompositionTest/roundtrip render explicit escaping of delimiters and HTML |
| A23 Source export | TransferTest/TransferCodecTest/TransferCommandTest verify files/hashes/licences/exact closure |
| A24 Static export | transfer browser opens file:// snapshot and observes zero HTTP requests |
| A25 Malicious ZIP | PortableContractsTest real traversal, case/exact duplicates, symlink and expansion archives |
| A26 Release integrity | Immutable publication/import tests; registry archive hashes and storage verifier |
| A27 Consumer integration | Fresh real Symfony fixture, production AssetMapper/CSS and consumer Chromium |
| A28 Idempotence | Consumer reinstall reports changed=0 and every destination hash remains equal |
| A29 Conflicts | Edited owned files and byte-identical unowned collisions abort with unchanged snapshots |
| A30 Install crash | Actual CLI SIGKILL after first journalled write, fresh doctor recovery and exact hashes |
| A31 Update/remove | Real pinned-dependent rejection, parent removal, leaf update, owned deletion and local install |
| A32 Registry auth | RegistryTest/read-token CLI/browser expiry/revoke/scope; real foreign-origin redirect rejection |
| A33 Access control | AuthenticationTest/CatalogueTest/TransferTest and reader/anonymous browser/API cases |
| A34 Accessibility | Editor/tree/tab keyboard tests; native dialog focus/Escape; reduced-motion progress |
| A35 Backup restore | Consistent pg_dump/blob snapshot, fresh DB/blob volumes, exact draft/theme/export and repeat install |
| A36 Production boundary | Actual Docker privileges/ports/networks/secrets/limits/health inspection and local TLS cookie checks |
| A37 Document assembly | Closing script/style and Unicode handling in worker/preview boundary tests |
| A38 Cleanup | CleanupTest dry-run/apply/reference retention; actual compiler stale-directory removal; scheduled maintenance |

Production recovery uses real paused workers, a graceful consumer stop and SIGKILL. It
waits for durable-message redelivery and asserts successful output after the lease, then
compares restored drafts/tokens and byte-identical complete collection exports. The same
fresh consumer repeats actual registry and offline installs after restoration. These are
local application checks; browser motion/focus checks are not a full accessibility audit.
System fonts and bounded text-only packages are the supported initial profile.


## Font library and website presets — 2026-10-27

This completed extension supersedes the earlier system-font/text-only profile above.
The existing M00–M08 evidence remains historical evidence; the application was extended
in place. No existing database was reset. Baseline hashes still match all **13 original
themes, 268 catalogue items and 268 drafts**; there were no original renderer-preset rows.
The baseline comparison is retained in `var/library-verification/preservation.json`.
Unrelated AGENTS.md, staged README.md and pre-existing untracked files were preserved.
No remote deployment, branch push or external package publication was performed.

### Actual font imports

All **42 requested families** were downloaded, validated and registered in persistent
`catalogue_blobs/fonts/`: **208 WOFF2 subset files**, **104 style/weight groups**,
**78 family/style combinations**, **35 variable families** and **7 static families**.
There are no pending font families and no substituted font binaries.

| Stored data | Actual bytes/count |
| --- | --- |
| Installed WOFF2 payload | 6,713,012 bytes (208 files) |
| Licence references | 42; 40 unique licence files, 192,499 bytes |
| Persistent font registry including metadata and retained immutable history | 7,824,256 bytes, 374 files |
| Maintenance download cache, including website evidence | 70,053,903 bytes |
| Maintenance staging, including font metadata and theme candidates | 7,210,935 bytes |

These are logical file/directory sizes, not filesystem block allocation. The cache and
staging directories are separate from the installed runtime font payload. Their contents
are retained for reproducibility; they are not browser downloads. Exact family/face/byte,
weight/style, licence and authority results are in
[font-imports.csv](../../resources/library/font-imports.csv). The lock records every
individual font hash, pinned tarball integrity and authority metadata hash.

Fontsource versions are **5.3.0 for 39 families** and **5.3.1 for Source Sans 3,
Source Serif 4 and Source Code Pro**. Google Fonts family/licence authority is pinned to
revision `e7db6ca603fd4862010383ff17ffd6cc4ffac1c0`. There are 40 OFL families, Ubuntu
under UFL and Roboto Slab under Apache-2.0, each with its actual licence text. The
maintenance toolchain pins fontkit 2.0.4, tar-stream 3.2.2, Culori 4.0.2 and ipaddr.js 2.5.0;
`npm audit --prefix tools/library --ignore-scripts` reports **0 vulnerabilities**.

Acquisition parsed real cmap/fvar/OS/2 metadata. For every supported style/weight group,
actual glyph maps intersected with the declared Latin/Latin-ext Unicode ranges cover
**137 checked English/French characters**, including œ/Œ, ç, accents, €, punctuation and
numerals. Variable ranges remain fully declared; static weights/italics are offered only
when real faces exist. The browser decoder then loaded **all 208 pinned WOFF2 faces**
across all 104 style/weight cases, verifying matching embedded SHA-256 bytes and actual
loaded FontFace records. This is stronger than checking computed family names alone.

The catalogue is searchable/filterable with editable English/French specimens. Its first
load transfers **zero font files**. Previews are opt-in; specimen delivery selects only
the requested real style/weight's two Unicode subsets. Inter italic at weight 472 embeds
143,732 raw bytes, with a real `100 900` loaded FontFace range. There are no font preloads.
The final cache-cleared measurement is **25 resource requests /
89,702 transferred resource bytes**, plus **18,186 document bytes**
(326,665 decoded resource bytes). The record is
`var/library-verification/font-network.json`; these are studio assets, separately from
font bytes. Preview font choices do not affect studio typography. Broken-byte injection
produces the real browser decoder failure and a visible system-fallback status.

Body, heading and code selections persist in themes and account-owned renderer presets,
including the source editor and composition builder. The composition flow preserves its
draft while saving font/theme/viewport settings. Theme selection resets all three font
roles to theme inheritance. Srcdoc remains opaque-origin with `sandbox="allow-scripts"`
(or no script capability when disabled), `font-src data:` and `connect-src 'none'`.
No `allow-same-origin` or font-CORS exception was introduced.

### Actual website collection

**18 of 20 reference families** produced **25 imported modes** in the existing Theme /
ThemeRelease model, currently **1.2.0**. All 25 modes are source-observed. Complete theme
values are a mixture of observed, adapted and derived values: no preset is represented
as a wholly extracted website palette, and there are **zero invented/inverted modes**.
Each candidate records the observed property, source value, generated value and reason.
The last column below counts observed / adapted / derived canonical values, including
geometry and typography. Exact source URLs, chosen fonts and contrast results are in
[theme-imports.csv](../../resources/library/theme-imports.csv); timestamped source/style
hashes, source fonts and attribution are in [evidence](../../resources/library/evidence).

| Imported preset | Precise reference surface | Observed / adapted / derived values |
| --- | --- | --- |
| GitHub-inspired Light | Primer product UI design tokens | 6 / 7 / 28 |
| GitHub-inspired Dark | Primer product UI design tokens | 6 / 7 / 28 |
| GitLab-inspired Light | Pajamas product UI design-system documentation | 5 / 6 / 30 |
| GitLab-inspired Dark | Pajamas product UI design-system documentation | 5 / 6 / 30 |
| Stripe-inspired Light | Public marketing homepage | 5 / 1 / 35 |
| Vercel-inspired Light | Geist design-system colours | 7 / 6 / 28 |
| Vercel-inspired Dark | Geist design-system colours | 9 / 4 / 28 |
| Notion-inspired Light | Public marketing homepage | 8 / 3 / 30 |
| Slack-inspired Light | Public marketing homepage | 5 / 0 / 36 |
| Discord-inspired Light | Public marketing homepage | 3 / 0 / 38 |
| Spotify-inspired Light | Spotify developer design guidelines; Encore light documentation surface | 11 / 0 / 30 |
| YouTube-inspired Light | Public video product homepage | 5 / 2 / 34 |
| YouTube-inspired Dark | Public video product homepage | 5 / 2 / 34 |
| Reddit-inspired Light | Reddit Inc public corporate marketing homepage; product homepage excluded by robots.txt | 5 / 3 / 33 |
| Stack Overflow-inspired Light | Stacks product UI colour documentation | 5 / 5 / 31 |
| Stack Overflow-inspired Dark | Stacks product UI colour documentation | 9 / 1 / 31 |
| Airbnb-inspired Light | Public booking product homepage | 6 / 2 / 33 |
| Dropbox-inspired Light | Public marketing homepage | 3 / 3 / 35 |
| Figma-inspired Light | Public marketing homepage | 7 / 0 / 34 |
| Wikipedia-inspired Light | Public English encyclopedia product UI | 7 / 7 / 27 |
| Wikipedia-inspired Dark | Public English encyclopedia product UI | 14 / 0 / 27 |
| GOV.UK-inspired Light | GOV.UK Design System service UI guidance | 6 / 6 / 29 |
| Shopify-inspired Light | Current Polaris App Home documentation website (documentation UI, not the embedded admin product) | 6 / 0 / 35 |
| Atlassian-inspired Light | Atlassian Design System token documentation | 6 / 7 / 28 |
| Atlassian-inspired Dark | Atlassian Design System token documentation | 13 / 0 / 28 |

The two pending references have explicit failure records, not placeholder presets:

- **Linear:** its required stylesheet host `https://static.linear.app/robots.txt` disallows
  `/`. The unstyled page was rejected as palette evidence.
- **Twitch:** certificate verification fails with `self-signed certificate in certificate
  chain`, including the alternative official developer documentation. TLS verification
  was never disabled; the previous unstyled observation is marked unusable.

Reddit's product surface is robots-blocked; the imported light preset is explicitly the
official Reddit Inc corporate marketing site. Historic Shopify Polaris documentation
moved; the imported light preset observes current App Home **documentation UI**, not an
embedded admin product palette. Spotify observes Encore developer documentation in light
mode, with its current observed purple accent; it does not claim to reproduce the music
player. Failed later refreshes for GitLab, GOV.UK and Atlassian (TLS), and Slack/Dropbox
(no permitted stylesheet), retain the earlier successful snapshot with its real retrieval
time plus a separate failed-attempt record. Cached data is never presented as freshly
retrieved evidence. Access controls, challenges and robots restrictions were respected.

Culori converts valid observed colours to existing canonical hex tokens, clips to sRGB
and composites transparency against the recorded surface. Missing semantics and licensed
font alternatives are explicitly derived. Adjusted source colours remain in provenance.
Unsupported gradients/shadows and unrepresentable properties are documented as omitted.
These independent presets contain tokens and evidence, not copied logos/pages or
proprietary font files. Managed seeds reject direct editing; Duplicate/Edit creates an
independent user theme. Apply saves the current account's renderer selection and opens a
single shared showcase. Repeat import reports all 25 unchanged; changed managed sources
require a newer collection version and produce a diff plus an immutable release. Earlier
1.0.0/1.1.0 revisions and user copies remain intact.

### Showcase and contrast evidence

Every imported preset was actually rendered on the same complete showcase at **375 and
1280 px**, with actual decoded local fonts, navigation/sidebar, buttons/states, fields,
select, cards, badges, table, native tabs, alerts, accordion, progress, modal, body and
code. Native tab and modal interactions pass. **50 screenshots** and a contact sheet
were visually reviewed; screenshots show distinct palettes and readable layouts.
Artifacts are `var/library-verification/showcases/`, `showcases-contact.png`,
`showcases.html` and `showcase-audit.json`.

All canonical ordinary-text content/surface pairs exceed 4.5:1. Browser measurements
separately inspect real CSS states rather than assuming token contrast covers them:

| Checked computed state/pair | Lowest measured ratio |
| --- | --- |
| Normal button text/background | 5.51:1 |
| Hover/focus button text/background | 4.67:1 |
| Selected tab text/background | 9.35:1 |
| Focus outline/surrounding surface | 4.94:1 (at least 2 px) |
| Normal input border/surface | 9.35:1 |
| Error input border/surface | 4.62:1 |
| Disabled text/background, reported separately | 1.37:1 |

Disabled controls are assessed separately and exempt from the ordinary-text threshold.
Field outlines use the optional canonical highContrastBorders setting. This evidence is
bounded palette/state/keyboard/browser verification, not full WCAG certification.

### Export, clean consumer and persistence

`bash tests/FontConsumer/run.sh` completed **exit 0** using the absent, unique fixture
`var/font-consumer-20261027T131001Z-12365`. It exported an actual composed layout with
Inter body **472 italic**, Lora heading **631 normal**, and JetBrains Mono code **400
normal**. The collection ZIP, importable theme bundle ZIP and static ZIP include only
those three families, raw font inventories, original licences and SHA-256 checksums.
Full selected-family face definitions/ranges remain portable; browser use selects the
needed faces/subsets. JSON/YAML source envelopes use validated canonical base64 for font
bytes; ZIPs and installed destinations use original raw WOFF2.

The fresh Symfony consumer installed with the real installer bundle, compared canonical
theme metadata, every raw font/licence/inventory hash and all rebased relative CSS URLs,
then repeated installation with **changed=0**. Production AssetMapper compilation maps
font URLs correctly and includes no staging paths. The consumer runs on an internal
Docker network without a host port. Browser routing permits only that consumer origin,
file/data URLs, and records **zero external or studio requests** for the consumer and
file:// snapshot at mobile/desktop widths. Actual requested font hashes and decoded
FontFaces, variable italic/body and heading weights, native modal and Stimulus counter
all pass. These same checks pass after consumer restart. The fixture is retained and its
service stopped; the pre-existing independent consumer was not overwritten. This final
standalone repeat started without browser credentials, created its own temporary accounts
and removed them on exit. The earlier successful 09:45 UTC fixture is also retained.

A fresh Stimulus CDN download initially failed TLS verification. The fixture now reuses
the bootstrapped studio's exact cached **Stimulus 3.2.2**, checks its locked version and
copy hashes, then compiles the independent production consumer. No TLS bypass or runtime
studio dependency is involved. Development app and preview-consumer images were also
rebuilt/recreated with persistent volumes retained; all 42/208 font bytes reverify and
the complete browser suite passed afterward. Restricted build/render worker networking
was retained. Existing production services/storage were not modified by this extension.

### Executed checks

| Command/check | Actual result |
| --- | --- |
| Dockerized `fonts-download.mjs` and `styleguide:fonts:sync --collection=popular` | All 42 downloaded and registered; repeat 42 unchanged |
| `styleguide:fonts:sync --collection=popular --dry-run` | All 42 validated without writes |
| `styleguide:fonts:verify` after rebuild/restart | 42 families / 208 faces / 6,713,012 font bytes / 40 unique licences; errors=[] |
| Dockerized `themes-collect.mjs`, `themes-candidates.mjs`, `styleguide:themes:collect` | 18 references / 25 modes imported; Linear/Twitch pending |
| `styleguide:themes:collect --collection=website-inspired --dry-run` | 25 unchanged, two pending reasons printed |
| `docker compose exec -T -e APP_ENV=test app php bin/phpunit` | **112 tests / 589 assertions**, pass |
| `docker compose run --rm --no-deps browser npx playwright test --reporter=list` | Complete **28-flow** suite, pass after rebuild |
| Final focused `fonts.spec.js composition.spec.js` | **5 passed**, including new composition font/preset flow and existing save-race workflow |
| Final cache-cleared catalogue measurement (`--grep 'catalogue stays lazy'`) | **1 passed**; zero initial font requests; actual selected bytes/FontFaces verified |
| `browser node tests/FontConsumer/fontfaces.mjs` | 42 families / 104 style-weight cases / 208 real decoded faces, pass |
| `browser node tests/FontConsumer/showcases.mjs` | All 25 presets, 50 screenshots, interactive/state/mobile/desktop/gallery-copy checks pass |
| `bash tests/FontConsumer/run.sh` | Fresh real install / idempotence / production compile / offline browser / restart pass, exit 0 |
| Docker Compose config, Composer validate --strict, lint:container, lint:twig, lint:yaml | Pass (20 Twig templates / 26 YAML files at full lint); final composition Twig/container lint also pass |
| Maintenance npm audit; schema copies; git diff --check | Zero vulnerabilities; schema copies identical; diff check passes |
| Database baseline comparison | Original 13 themes / 268 items / 268 drafts unchanged; zero changed or missing rows |

The full suite contains 29 flows after adding the final composition regression. The
complete 28-flow run preceded that last UI addition; the subsequent five focused flows
exercise the final controllers and the existing composition save-race path. No unrun
29-flow result is claimed. Remote CI and a new production disaster-recovery cycle were
not run for this extension; earlier M08 recovery evidence above is historical.

Observed failures corrected during this work include the Latin-ext subset-prefix match
(now longest-first, metadata ingestionRevision 2 with history retained), JSONB empty-axis
arrays in static-font worker payloads, a font-asset AssetMapper exclusion, a theme-bundle
ZIP that previously lacked an importable manifest, and read-only import preparation that
previously attempted asset registration. Regression checks cover real queued static-font
rendering, corrupted bytes preserving existing assets, clean-store prepare/apply and
portable inventory/ownership. Initial environment/test-fixture errors were corrected
and rerun; the final checks above are actual passing runs. Running showcase capture
concurrently with worker resource-limit tests caused transient worker_unavailable;
sequential final captures pass for every preset.

Limits: English/French Latin coverage is verified; other scripts require the same
manifest-driven importer. Source websites can change or become inaccessible. Proprietary
source fonts are represented by the recorded redistributable/system alternatives. Font
menus expose declared weight/style; additional variable axes retain declared defaults.
The selected-family export may contain additional real faces needed to preserve editable
family ranges; it never includes unrelated library families. Browser verification uses
Chromium. Large JSON/YAML font envelopes above the shared byte cap require the ZIP format.
No full accessibility certification or public hosting is implied.

## M10 — Application appearance — 2026-10-27

Implemented `codex-apply-themes-to-application.md` in the existing editor, gallery,
FontLibrary, shared CSS generator and persistence. Application preferences are personal;
renderer presets and exported component appearance remain independent. Usage, model,
precedence, rollback and operator commands are in [APPEARANCE.md](APPEARANCE.md).

`Version20261027143000` adds explicit families, immutable shared revision snapshots and
per-user preferences. It ran successfully against the existing development/test databases
and a fresh production database. The migration pairs only known managed reference
provenance, preserves unrelated single variants and does not rewrite Theme documents.
Read-only row-hash comparison confirms all **47 original themes, 308 catalogue items,
308 drafts and one renderer preset** are unchanged; evidence is
`var/appearance-verification/preservation.json` against `var/appearance-baseline.json`.
All **42 font families / 208 WOFF2 files / 6,713,012 font bytes** still verify without
errors. Existing production on 8096 and the consumer on 8110 were preserved.

### Acceptance evidence

| Prompt check | Actual coverage |
| --- | --- |
| 1. Real application surfaces and independent previews | `appearance.spec.js` applies two contrasting palettes; asserts computed root/header/body/button/form/menu/native-dialog colours, real body/heading/code fonts and unchanged renderer preset; component iframe keeps its own theme. |
| 2. Moon/sun within the family | Browser checks both SVG visibility states, button title/name, family identity, mode and native `color-scheme`. |
| 3. Explicit/system modes | Browser changes emulated OS scheme; explicit mode stays fixed, system follows, toggle selects explicit mode. |
| 4. Persistence, first paint and account separation | Navigation, back, reload and first-animation-frame colour checks; independent reader/editor/admin sessions; PHPUnit verifies authenticated precedence over a signed guest cookie and rejects corrupt cookies. |
| 5. New families after production compilation | Production browser creates a complete pair and an additional single-family opposite variant after image/AssetMapper build; applies both and verifies actual computed colours. Manifest is read-only and its SHA-256 remains identical. |
| 6. Save versus Save & Apply and old revisions | Two accounts apply one family; Save keeps both captured snapshots, Save & Apply advances only the current account; renamed family keeps its stable ID. PHPUnit checks old CSS bytes and hashes remain unchanged. |
| 7. Trial/revert | Repeated debounced edits, invalid tokens, unsaved per-variant field retention, trial variant switching, exact original snapshot rollback, ordinary Save, navigation/reload and Save & Apply. Preference API stays unchanged during trial. |
| 8. Failure paths | Invalid CSS/tokens and unknown fonts rejected; missing/deleted source falls back to default; single variant explains/blocks unavailable mode; real font decode failure has readable fallback; CSS/preference failures preserve confirmed state, including cold reload, lost successful HTTP response and first-create partial failure with Retry. |
| 9. Reordered requests | Browser deliberately holds A/B prepare responses, commits C, releases B/A, then fails D; C remains the saved and visible rollback target. PHPUnit rejects obsolete sequence and preference revision writes. |
| 10. Accessible/mobile controls | Real Enter-key activation, retained focus with a solid outline at least 2px wide, discoverable unavailable-mode explanation, 375px viewport and visible fixed Restore default above development overlays. |
| 11. Security/isolation/local assets | PHPUnit checks CSRF, trial owner/anonymous denial, private/no-store policies, asset hashes and injection; real browser confirms unchanged `sandbox="allow-scripts"`, font role separation and local decoded assets. Generated CSS links have SRI; raw scraped/uploaded CSS is never applied. |
| 12. Existing contracts/exports | Full PHPUnit and browser suites retain existing imports, theme/package checksums, fonts, immutable releases and isolated previews; fresh consumer verification is recorded below. Portable theme format stays schemaVersion 1; family assignments remain application database metadata. |

### Commands and production proof

- `docker compose exec -T -e APP_ENV=test app php bin/phpunit`: **120 tests / 694 assertions**,
  exit 0. Includes eight new appearance functional tests and existing package/font,
  renderer isolation, import/export, registry, installer and resource-limit checks.
- `docker compose run --rm --no-deps browser npx playwright test
  --output=var/appearance-verification/development-results --reporter=list`:
  complete **41-flow development suite passed in 5.0 minutes**, exit 0. This includes all
  12 appearance flows and the existing authentication, catalogue, composition, source
  editor, font, isolated renderer, registry, release, showcase, theme and transfer flows.
- `make check`: Compose config and Composer strict validation, service/container lint,
  **26 YAML files / 21 Twig files**, all pass. `doctrine:schema:validate` confirms mapping
  and database are synchronized. `git diff --check` passes.
- `bash tests/AppearanceProduction/run.sh`: **12 browser tests passed in 44.4 seconds**,
  exit 0, against actual APP_ENV=prod/APP_DEBUG=0 immutable images. Fixture:
  `var/appearance-20261027155720-7821`, image tag `local-20261027155721-805d8e0c`.
  AssetMapper `public/assets/manifest.json` is not writable and has SHA-256
  `2697be0747faf6901e441dd5f4d6bf4e9cb354a0604a0172622f61e0282e8416`
  both before and after runtime theme creation/editing/application. No build/restart
  occurred between those checks. Inter, Lora and JetBrains Mono were registered from
  verified offline staging: **3 families / 12 faces / 511,916 font bytes**, no errors.
  Real application headings load Lora631, body Inter472 italic and source code
  JetBrains Mono400. Temporary accounts were disabled; only the isolated fixture was
  stopped, with its volumes/evidence retained.
- `bash tests/FontConsumer/run.sh`: exit 0 with fresh fixture
  `var/font-consumer-20261027T160404Z-13295`. A real composed layout plus Inter472 italic,
  Lora631 and JetBrains Mono400 passed collection/theme/static exports, exact byte,
  metadata, licence and inventory/checksum checks, real consumer install/reimport,
  **changed=0** reinstall and production AssetMapper compilation. Consumer and file
  snapshot both decoded fonts, ran Stimulus/modal controls at mobile/desktop widths and
  made **zero external or studio runtime requests**, then passed again after restart.
  Only the task-owned consumer service was stopped; the fixture is retained. Log:
  `var/appearance-verification/font-consumer.log`.
- Temporary development browser accounts were removed after all checks; production
  fixture accounts were disabled. Original row hashes were checked again after the
  consumer run and account cleanup: every original row remains unchanged.

Screenshots, actual loaded FontFace metadata, unchanged renderer settings, browser logs
and preservation evidence are under `var/appearance-verification`; production build and
manifest evidence also live in the unique fixture. The browser runs use separate trace
directories to avoid collisions when testing isolated instances concurrently.

Failures found and corrected: eager Twig runtime dependencies initially broke unrelated
font tests; the extension now loads runtime services lazily. Existing theme creation
confirmation was lost by the API redirect and now uses a bounded session flash. The old
debounce timing assertion now uses Playwright's clock while retaining real worker calls.
Production browser network inheritance and shared trace output were corrected. An empty
failed stylesheet could appear as a loaded CSSStyleSheet; confirmation now requires
non-empty rules, with SHA-256 SRI on initial and dynamic links. The final focus test checks
the actual visible daisyUI outline rather than assuming the base-layer 3px rule wins.

No new dependencies or portable package-format changes were needed. Actual versions
remain those recorded for the font/theme extension above. Chromium is the browser tested;
this does not claim a full accessibility certification. No external deployment, branch
push or package publication occurred.

## M11 — Theme detail component showcase — 2026-10-27

The existing theme detail now has **65 server-rendered Twig example panels in seven
categories**, with category/search controls and real local interactions. Inventory,
architecture, commands and the three intentional catalogue omissions are documented in
[THEME_SHOWCASE.md](THEME_SHOWCASE.md). It reuses current family variants, typed token CSS,
font embedding, the opaque preview document and the existing portable tabs/dialog
controllers. No migration, theme-model change, second generator or catalogue fixture is
needed. Apply/Edit/Duplicate/export and application appearance remain independent.

### Actual commands and results

- `make assets`: actual studio/showcase Tailwind CSS and bundled Stimulus built in Docker
  with the installed Tailwind4.3.3/daisyUI5.7.50/esbuild0.28.2. No dependencies changed.
- `make check`: Compose config, Composer strict validation, service injection, **26 YAML
  files / 30 Twig files** all pass. Doctrine mapping and schema validate in sync.
- `docker compose exec -T -e APP_ENV=test app php bin/phpunit`: **122 tests / 724 assertions**,
  exit0. Includes server-rendered inventory, private reader access, unique IDs/radio
  groups, unchanged database/preferences and all existing contracts/import/export,
  renderer/font/installer checks.
- `docker compose run --rm --no-deps browser npx playwright test
  tests/Browser/theme_components.spec.js
  --output=var/component-showcase-verification/final-focused-results --reporter=list`:
  **5 passed / 1.3m**, exit0. Tests actual computed palette/typography, two contrasting
  custom families and real light/dark variants, local Inter472 italic/Lora631/JetBrains
  Mono400 decoding, category/search, three keyboard tab styles, dropdowns, native dialogs,
  swaps/FAB, accordions/collapses, form states/validation, independent radios/ratings,
  file selection without upload, drawer focus/Escape/backdrop, timed/dismissible toast,
  carousel, pagination/dock, countdown, and every category at **375px / 768px** without
  horizontal overflow. Tooltip focus and the always-reachable recovery control pass.
  A browser CSSOM walk confirms **every rendered class has a compiled/generated rule**;
  all65 component scopes share the selected primary token. The frame makes **zero HTTP
  requests** and cannot read its parent DOM. Personal preference stays unchanged.
- `make theme-showcase-production`: **17 browser flows passed / 4.4m**, exit0, including
  all12 existing appearance flows and all5 showcase flows in actual APP_ENV=prod,
  APP_DEBUG=0 immutable images. Fixture `var/appearance-20261027172638-15809`, release tag
  `local-20261027172638-c0124396`. New families/variants were created after compilation.
  AssetMapper manifest remained read-only, SHA-256 before=after:
  `41ff52657f613a2a832c0cf68d20edf9b56db2535286ac754016086acd0b762b`.
  Offline registration verifies **3 families / 12 faces / 511,916 bytes**, errors=[].
  Its three temporary accounts were disabled and only its isolated services were stopped;
  volumes and compiled manifest evidence are retained. Existing production8096 is intact.
- A read-only reader survey checks **eight existing website variants**: Airbnb Light,
  Atlassian Dark/Light, Discord Light, Dropbox Light, Figma Light and GitHub Dark/Light.
  Each has65 matching component token scopes, computed base/primary colours and decoded
  local fonts. Preference before/after is identical. Evidence and screenshots:
  `var/component-showcase-verification/website-survey.json` and `website-*.png`.
- Complete development browser command with
  `--output=var/component-showcase-verification/full-development-results --reporter=list`:
  **45 passed / 1 failed / 14.5m**. The failure was the existing property-form preview
  check's five-second assertion while its real worker job was still building. It now
  waits for the public current state within20 seconds and gives its several sequential
  builds a90-second test deadline, retaining all class/mode/persistence assertions.
  `... playwright test tests/Browser/showcase.spec.js --grep 'property form'
  --output=var/component-showcase-verification/renderer-wait-results --reporter=list`:
  **1 passed / 33.1s** (flow30.7s), exit0. Thus all46 distinct flows have passing evidence
  across these runs; a second complete46-flow passing run is not claimed. Logs retain
  both the original failure and corrected real-renderer result.

Production and development screenshots were reviewed, including light/dark typography,
responsive mockups and keyboard-focused mobile tooltips. Browser logs, FontFace metadata,
CSS coverage and preservation evidence are retained in `var/component-showcase-verification`.
After temporary development account cleanup, original row hashes still match **284
themes / 430 catalogue items / 430 drafts / 1 renderer preset / 1 personal appearance
preference**. No original row changed or disappeared. All **42 font families / 208 faces /
6,713,012 font bytes** verify with errors=[]. The baseline and final comparison are
`baseline.json` and `preservation.json` in the evidence directory. Temporary credentials
were removed; existing production and consumer services/data were retained.

Corrections during verification: macro imports are explicit in each reusable partial;
select labels use unique for/id relationships; swaps have a full interactive checkbox
area and visible keyboard focus; drawer focus waits for native CSS transitions and its
backdrop suppresses the checkbox's second default toggle. Responsive fieldsets/file
inputs no longer force intrinsic widths, and tooltip placement cannot widen a mobile
document. Focused tests reset the requested application mode explicitly; a read-only
survey uses its own reader account instead of comparing an editor preference while
concurrent acceptance tests deliberately change it. Earlier failed checks are retained
as diagnostic evidence and are not counted as passes.

Limits: external-library calendars, full-page megamenus and an additional daisyUI theme
controller are deliberately omitted; all explicitly requested groups are covered. Native
OTP/diff/hover cosmetics vary by browser; Chromium is verified. The phone device frame
and two bundled illustrative SVGs keep their content colours. Not every modifier
permutation or accessibility conformance is claimed. No external release/push/deployment
or new package-format change occurred.

### Preview fragment navigation follow-up — 2026-10-27

User-reported reproduction on `/catalogue/01M5Y9QKN2PE245NAPSBB32363/edit`: clicking
Overview resolved `#overview` to the editor HTTP URL. The opaque frame requested the
protected page and rendered `/login#overview` inside itself. The outer editor stayed in
place. The shared assembler now sets a trusted about:srcdoc base and an about-only base
CSP rule. No component markup/href or saved data is rewritten, and native links work with
scripts disabled. The long-running development consumer was restarted to load this fix.

Actual user-component verification, both script modes: Overview/Activity/Settings clicked
and activated with Enter; frame stays about:srcdoc#..., zero iframe HTTP requests, fresh
private studio request returns200 and draft comparison remains equal. Evidence:
`var/preview-navigation-verification/before-fix.json` and `after-fix.json`. The pre-fix
login reproduction is expected failing-behaviour evidence, not a passing check.

- Focused new development regressions: **2 passed / 45.9s**. All showcase anchors and
  cross-category targets; catalogue native fragment and Unicode/missing targets, local
  field state, scripts on/off, no login/network navigation and intact authentication.
- Full PHPUnit: **123 tests / 736 assertions**, exit0.
- `make preview-navigation-production`: **12 passed / 3.2m**, exit0, including real
  login/logout, all catalogue preview isolation/message/CSS checks and all6 showcase
  flows. The fragment test also proves CSP rejects HTTP(S) bases. Fixture
  `var/appearance-20261027182113-25049`, release `local-20261027182114-9d211253`.
  Manifest SHA256 remains read-only and before=after:
  `41ff52657f613a2a832c0cf68d20edf9b56db2535286ac754016086acd0b762b`.
  Fixture accounts disabled, own services stopped and evidence retained.
- `make check`: strict Composer/Compose/container/26YAML/30Twig and diff checks pass.

The existing iframe sandbox has no added permissions. Auth/CSRF, data-only fonts/images,
denied connections/forms/frames and opaque parent isolation remain enforced. No theme,
component, account credential, migration or dependency change was required. Reload the
editor or Build preview to replace an already navigated login frame. Background session
cookie settings and the production8096 instance were not modified.

## M12 — Full-width inline theme detail reference — 2026-10-27

All theme detail routes now use the full-width main block, a300px desktop sidebar and
normal document scrolling through65 trusted Twig example panels. Mobile Theme details
starts collapsed. There is no detail iframe, height-capped scroll panel, nested document
or resize-message bootstrap. Other route layouts and user/imported-code sandboxes remain.
Scoped validated tokens, namespaced real font faces, native modal/drawer focus and the
single existing Stimulus application keep the selected theme independent of the shell.
Complete file inventory and browser/version limitations: [THEME_SHOWCASE.md](THEME_SHOWCASE.md).

Actual commands/results, with evidence under `var/theme-layout-verification`:

- `make assets`: exit0; Tailwind4.3.3/daisyUI5.7.50 compile the studio stylesheet including
  all Twig/JS showcase classes; esbuild emits an ESM controller bundle using the existing
  Stimulus3.2.2 module. The obsolete standalone showcase reset/build was removed.
- `make check`: exit0, including strict Composer/Compose/container,26YAML/31Twig checks.
  Final `git diff --check` passes. Runtime remains Symfony8.1.9/PHP8.5.12/
  FrankenPHP1.13.4/Trixie; no dependency or lockfile change.
- `docker compose exec -T -e APP_ENV=test app php bin/phpunit`: **123 tests /752 assertions**,
  exit0. Reader/privacy/hash/SRI/no-mutation checks cover the inline catalogue and new CSS
  route. Existing font, packaging, renderer, source-isolation and import contracts pass.
- `docker compose run --rm browser npm run test:browser -- --output=var/theme-layout-verification/browser-final --reporter=list`:
  **49 passed /1 timed out /11.4m**. The old multi-build renderer-preset flow exceeded its
  overall30s default; its deadline now allows90s for real sequential builds/save/reload.
  The focused unchanged feature assertions rerun with `npx playwright test tests/Browser/editor.spec.js --grep 'viewport, zoom, typography and private preset survive reload'`:
  **1 passed /15.5s**, exit0. Thus all50 distinct flows have passing evidence across these
  runs; no single all50 passing run is claimed. All8 theme-detail flows pass in the full
  run, including actual browser JSON/ZIP downloads and legacy Apply-to-showcase submission.
- `make theme-showcase-production`: **20 passed /3.4m**, exit0, actual immutable production
  images (`APP_ENV=prod`, debug off). Fixture `var/appearance-20261027192213-13661`, tag
  `local-20261027192214-db878b0a`. New families/variants were created after compilation;
  all65 panels, complete emitted class coverage, fonts, independent schemes/root sizes,
  keyboard interactions, overlays,375/768/1920px widths, edit/create layout, duplicate,
  all draft/release endpoints, actual downloads, private package action and sandbox
  preview selection pass. Manifest is read-only, before=after SHA256:
  `2ff923422b68587eed6d82d934c8b9db5c0c139d99e0eb5cf9eeedac102d631c`.
  Offline fixture fonts:3families/12faces/511,916bytes/errors=[]. Its accounts are disabled
  and its own containers stopped; the existing production8096 and consumer8110 remain.
- Read-only reader survey: **8 existing variants /24 renders**,1440/768/375px: the exact
  YouTube reference route, Airbnb, Atlassian dark/light, Discord, Figma and GitHub dark/
  light. Selected colours/decoded fonts, no iframe/horizontal overflow and identical
  reader preference are recorded in `survey/results.json`; screenshots are retained.
  YouTube desktop/mobile and Airbnb desktop screenshots were visually reviewed.
- Application CSS compatibility: byte-for-byte identical to HEAD for **all474 then-current
  themes**, including old system/local fonts, with the shared generator's application mode.
  Original406 themes/461 items/461 drafts/97 releases/1renderer preset/299 appearance
  snapshots retain their complete row hashes after the work and account cleanup. The
  existing administrator preference changed through7 successful non-test Apply requests
  between19:19–19:25UTC during the run. Profiler request history is retained in
  `preference-requests.json`; its latest choice was preserved instead of restoring the
  starting value. Automated flows used separate accounts and their read-only checks
  retained identical preferences. Registry remains42families/208faces/6,713,012bytes/errors=[].

Corrected intermediate checks are retained. The first new PHPUnit assertion used a
nonexistent Response::isPrivate method; it now checks the actual private cache directive.
Focused browser corrections cover old iframe assumptions, native drawer Shift+Tab,
Turbo fragment reload/input loss, and a filtered-only height assertion. An initial
production run overlapped a full development run and survey; under heavy CPU load it
passed19/20, with a bounded Tailwind worker command failure. The development overlap was
stopped after appearance preparation exceeded5s assertions. The sequential production
rerun passes20/20 without changing assertions or worker limits. Logs/traces are retained
as `production-overlap.log`, `browser-overlap.log` and the corresponding fixture outputs.

No new component omission or functional limitation was introduced. Chromium is the
verified browser; this is not cross-browser or full WCAG certification. Existing calendar
adapter/megamenu/theme-controller omissions remain documented. No migration, user data
rewrite, external deployment, external publication or branch push occurred.

## M13 — Composer MCP, 2026-10-27

Implemented and exercised in the existing application; full operation map/contracts and
connection instructions are in COMPOSE_MCP.md, including its changed-file map. No external
publication/deployment/tunnel or global Codex configuration change occurred.

- `make assets`: pass with the locked Tailwind4.3.3/daisyUI5.7.50/Stimulus3.2.2 toolchain.
  Production also compiles the actual current Twig/controller sources into immutable assets.
- `make check test`: **135 tests / 1,138 assertions**, exit0,1m18.7s. Composer strict,
  container,32YAML/34Twig pass. Additive migration Version20261027203258 applied to dev/test;
  schema/mapping in sync. Final evidence:var/mcp-verification/final-verified.log.
- Endpoint tests use the real Symfony MCP SDK handler/transport with26explicit tools and
  JSON schemas; test PAT read/edit/create/preview scopes, missing/expired/revoked/disabled/
  reader credentials, actor/session isolation, transitive reference restrictions, replay
  reauthorization, props/defaults, node cycles/bounds, exact movement/root rules, atomic
  rollback, metadata, history restoration, stale browser/AI writes and owned preview cancel.
- OAuth functional HTTP checks exercise real form login/CSRF and return-target preservation,
  request-bound consent, exact redirects/state/issuer, S256 public+confidential clients,
  secret verification, code exchange/reuse rejection, signed JWT access, refresh rotation,
  access revocation, disabled users/clients, resource mismatch/query pollution and consent
  scope tampering. These are local application tests, not a ChatGPT account connection.
- `styleguide:mcp:smoke`: real official SDK network client negotiated **2025-11-25**,
  discovered26tools, searched real released samples/props, created and edited dedicated
  layout drafts, replayed an atomic alias batch, moved/nested/duplicated/removed nodes,
  validated, selected an existing published theme, retrieved real isolated HTML for
  revision8 and restored revision1 as9. Final development draft:
  01M5ZMCY8B5CQRF5B6JFW496WA; selected ThemeRelease01M5ZE9SBNGCKK9JEA7MJMM7ZV.
  Evidence:sdk-smoke-final.json; no screenshot/visual-inspection claim.
- **Installed Codex CLI0.164.0** app-server MCP round trip: discovery26tools, capabilities,
  create_draft/add_text/get; saved revision2 at draft01M5ZJV4B0DPV83PARFY8EZ2QM.
  Used command-line config overrides and an ephemeral thread; no model turn, global config
  write or user's token issuance. Evidence:codex-client.log; test credential revoked and
  actor disabled/private credential file removed.
- Real simultaneous HTTP sessions: different write keys produce exactly one saved winner
  and one REVISION_CONFLICT; simultaneous identical retry keys produce one mutation and
  one receipt replay. Revision3 contains exactly2nodes. Evidence:concurrency.log, dedicated
  draft01M5ZK1XAFDC77SS1V8KWCFPMA. Credentials revoked/actor disabled.
- Development manual+MCP browser flows:2/2pass (53.3s), plus focused responsive-CSS check
  1/1pass (19.3s). Tests preserve unsaved local text, show external revision notices,
  exercise discard/cancel, reject stale writes both ways, open the same draft in the normal
  editor, and verify actual three-column generated CSS above the breakpoint.
- `make compose-mcp-production`: **3/3 browser flows**, exit0,33.5s, with the official SDK
  network smoke against the same immutable APP_ENV=prod/APP_DEBUG=0 image. Actual manual
  creation, keyboard reorder/nest, duplicate/remove, Save, reference publication/nesting,
  rendered interactions, source conversion and login/logout pass. Captured preview3 stays
  at3 after saved draft4; another editor receives404. scripts=false gives an empty iframe
  sandbox and static HTML remains usable. No application theme preference is changed.
  Fresh production fixture has no ThemeRelease, so its SDK smoke uses the existing package
  default; development explicitly verifies a published theme. Fixture:
  var/appearance-20261027213428-25078; image:local-20261027213428-1a0494f3.
- The production manifest is read-only, before=after SHA256
  `9d58c205240700b95cfbc043828078c90c25e029ac31521a204a4b7bdf890d58`.
  Font fixtures verify3families/12faces/511,916bytes/errors=[]. New MCP drafts/class edits
  and renderer jobs work without rebuilding/redeploying that running image.
  production-verified.log and the fixture retain the actual smoke, manifests and traces.
- Feature-disabled check: MCP/OAuth/discovery return404 while normal login remains200.
  Existing storage verifies114releases/errors=[]. Runtime Composer audit (`--no-dev`) is
  clean. The complete audit retains the **pre-existing development PHPUnit13.4.3 advisory**
  PKSA-qhjm-rpqc-qkn6 (local PHPT code-coverage shared-temp handling); no existing package
  versions changed. NewSDK0.8.1 is outside the known<0.7.1 affected range.
- All original534themes/284families/496items/496drafts/110catalogue releases/100theme
  releases/1renderer preset/375appearance snapshots/1personal preference match their
  complete baseline row hashes. Original content was not normalized/re-saved by migration
  or reads.42font families/208faces/6,713,012font bytes verify without errors.
  Temporary browser accounts/files removed; SDK/Codex actors disabled and PATs revoked.
  All task-owned production containers stopped; original8095/8096/8110 remain running.

Corrected failures/observations: real SDK smoke exposed unsupported DQL concatenation and
empty-object loss in the SDK's associative decoder; both were fixed and retested using
actual network clients. Composer strict rejected exact1.x requirements; compatible caret
constraints retain the pinned resolution. A preview can complete before its response;
queued-only test assumptions were corrected while retaining cancellation/replay checks.
An extra responsive-CSS assertion initially failed2/3production flows because a768px
border-box iframe has766pxinner width, below the768pxbreakpoint; using the existing1280px
viewport proves the actual emitted three-column rule (failure evidence in
production-acceptance.log and var/appearance-20261027212838-29958). A temporary misplaced
new test assertion was removed; final135/1138 run passes. Production font fixture input
was mounted read-only while its CLI also writes a report; the test driver now uses a
separate task-owned report mount. The final production run has no PHP error-level log;
vendor/Doctrine deprecation notices remain informational. Public/private OAuth key file
permissions also match League's requirements without replacing key bytes.

Remaining limits: ChatGPT OAuth **account linking and tool invocation are not verified**;
the user must supply account/workspace access and reachable HTTPS/OAuth endpoints (or a
supported private tunnel arrangement). No tunnel service/profile was installed or claimed
tested. Source component authoring/publishing/admin and screenshot inspection are outside
the composer surface. Existing pre-integration unpublished history cannot be recovered;
node IDs count toward the64KiBdocument cap, and old external validators need the optional-ID
schema extension for new metadata. Chromium is the browser checked here. No required M13
implementation or local acceptance gate remains unfinished.

## M14 — Profile and browser MCP setup, 2026-10-27

The live development application now exposes /profile from navigation. Account details,
passwords, personal token creation/revocation, client configuration, actual browser
MCP/OAuth checks and administrator OAuth client setup use the existing application.
See PROFILE.md for permissions, implementation/file map and the UI workflow.

- `make assets`: pass. No dependency/lockfile/version changes. Existing PHP8.5.12,
  Symfony8.1.9, FrankenPHP1.13.4/Trixie, Twig3.30.0, Tailwind4.3.3, daisyUI5.7.50,
  Stimulus3.2.2 and Playwright1.64.1 remain.
- Additive migration **Version20261027215409** generated/reviewed and installed in dev/test;
  it adds only nullable studio_user.display_name VARCHAR100. Original IDs, emails, hashes,
  roles and enabled flags are preserved. Schema/mapping validation passes. Restarted only
  the development preview-consumer to load the updated User metadata.
- `make check test`: **141 tests / 1,245 assertions**, exit0,1m23.7s. Composer strict,
  container,33YAML/36Twig pass. Final evidence:var/profile-verification/verified-checks.log.
  New real functional flows cover uniqueness/canonicalization, incorrect current password,
  retained attempted fields/cleared secrets, current-session continuity, stale profile
  fingerprint, reader mass-assignment rejection/escaping, password throttling, old-session
  rejection and owned PAT/OAuth access/refresh/code revocation with other users preserved.
  They also cover CSRF/role/foreign-token isolation, one-time secret refresh, config without
  secrets, exact callback registration, hashed client secrets and client deactivation.
- Development browser **3/3pass,17.8s** (browser-acceptance.log): actual profile save/reload,
  native section links preserving unsaved input, one-time mask/show/copy/download controls,
  real bearer initialize/notification/discovery/capabilities calls, cookie omission,
  OAuth discovery checks, read-only scopes, revoked-token rejection, owner metadata and
 1440/768/375pxwidths without horizontal document overflow. Mobile light and desktop dark
  screenshots were visually inspected; a real appearance toggle is restored afterward.
- Admin browser flow registers a real client in the profile, follows local authorization
  and CSRF consent, exchanges code+S256 verifier+client secret, then verifies the resulting
  JWT through the profile's actual MCP test. Disabling the client makes that JWT fail;
  re-enable/disable works through the UI. The external ChatGPT callback is inspected as a
  Location header and **never visited**; no ChatGPT account connection is claimed.
- `make profile-production`: **6/6browser flows /45.6s**, exit0. Includes all3new profile
  flows plus existing login/logout, full manual composition/edit/reorder/nesting/source
  conversion and concurrent manual/MCP revision/preview regression flows. Also runs the
  official SDK network smoke with26tools and exact preview8/restoration9. Actual current
  Twig/CSS/Stimulus assets are production-built and immutable; new profile/account/token/
  client changes require no asset rebuild/redeployment of the running image.
  Fixture:var/appearance-20261027223038-13445; image:local-20261027223039-4d2896b4.
- Read-only production manifest SHA256 before=after:
  `6e8185df5895c5426a17e0b68472712d8cafece6c79b2c01261aee176cbe2049`.
  Fixture fonts3families/12faces/511,916bytes/errors=[]. Production has informational
  vendor/Doctrine deprecations, no error-level PHP log. Evidence:production-acceptance.log
  and the fixture's manifests, network smoke and browser output.
- Original existing fields of **7accounts**,534themes/284families/512items/512drafts/
 114catalogue releases/100theme releases/1renderer preset/375snapshots/1appearance
  preference/6MCPcredentials retain baseline row hashes. Name defaults are nullable and
  no original account was edited during testing. Full font registry still verifies
 42families/208faces/6,713,012bytes/errors=[]. Test actors/credential files are removed,
  production test accounts disabled, OAuth fixture clients disabled, task-owned production
  containers stopped. Existing development8095, production8096 and consumer8110 preserved.

Corrected failures: Symfony's test form helper retained checked default scope fields when
an array override was merged; the functional test now submits the actual intended form
array, and native browser unchecking separately proves scope narrowing. The first browser
and production run found a real405when reloading the POST credential URL after download.
One-time responses now normalize the visible URL to/profile, and GET return routes safely
redirect there with no secret. The failed production run5/6is retained in production.log;
final production6/6passes. Native section links bypass Turbo to retain unsaved fields.
The Origin guard accepts the actual origin only after trusted Host validation, making
same-origin Docker browser checks work while existing hostile host/origin tests pass.

Remaining boundaries: browser verification proves a connection to Studio, not an external
Codex/ChatGPT account link. Actual ChatGPT linking still requires account/workspace access
and reachable HTTPS/OAuth or a supported tunnel setup. Client config/environment still
belongs to the user's client computer; Studio does not modify local client files or
publish a tunnel. Server enablement/origin/proxy/HTTPS policy stays deployment-controlled.
The existing dev-only PHPUnit advisory remains unchanged; no new dependency was added.
No external deployment/publication, branch push or real catalogue deletion occurred.
