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
