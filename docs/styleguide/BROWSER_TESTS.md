# Disposable browser acceptance

Run `make browser` from a checkout with Docker Compose and Python 3. No development
stack, host PHP, host Node or host browser is needed. The command builds the locked
test images, initializes an empty disposable database and blob volume, migrates,
seeds, runs Playwright and removes its containers, volumes, networks and credentials.
It never runs `styleguide:test:accounts` against the development stack. Development
data is never used to supply missing fixtures.

```sh
make browser
make browser-smoke
make browser-visual
make browser BROWSER_ARGS='tests/Browser/favorites.spec.js --project=chromium'

# Retain an environment for repeated checks. Copy its printed exact run ID.
make browser-start
make browser-seed RUN=browser-<24 hex characters>
make browser-run RUN=browser-<24 hex characters> BROWSER_ARGS='tests/Browser/critical.spec.js'
make browser-check RUN=browser-<24 hex characters>
make browser-cleanup RUN=browser-<24 hex characters>
```

`browser-check` runs strict Composer validation, container/YAML/Twig lint and all
PHPUnit tests in Docker. Its `_test` database lives in the same disposable PostgreSQL
service. The PHP OAuth tests use their established canonical loopback resource URL
inside that CLI process; browser and worker requests use the private test networks.

Each invocation creates `var/browser-runs/browser-<random 96-bit ID>/`. Start/seed/run/
cleanup require that directory's receipt, private ownership secret and canonical
configuration. Normal tests publish no ports. Two independent runs can coexist;
operations within one run are serialized. Images contain the source snapshot from
their build: create a new run after code changes. `--no-build` is a diagnostic shortcut
for already built images, not the normal verification command.

The standalone `compose.browser.yaml` does not merge development configuration. App
and queue consumer share only their run's runtime and blob volumes. PostgreSQL, Caddy
state, session files, locks, caches, OAuth keys and artifacts never share working
storage. Renderer/compiler remain separate, bounded, internal workers with no studio
source or database credentials. All runtime networks are internal. Mail uses
`null://null`; registry publication and packages remain inside the disposable studio.
Build-time dependency/font acquisition uses the pinned manifests and locked tools.

Before the first migration, initialization requires an empty database and blob root.
It writes matching ownership sentinels into the PostgreSQL database comment and blob
root. This metadata does not add an unmapped table or change application migrations.
CLI account/seed commands recheck both sentinels. Before any browser suite, and again
before login, the application must answer a fresh HMAC challenge using the mounted
run secret and verified sentinels. A URL, account-file prefix or environment flag
cannot authorize mutations. `/health/browser-run` returns 404 outside verified test
environments. Production retains its normal authentication and deployment policy.

The bounded seed contains four users with random passwords (reader, editor, admin and
a separate visual-test editor), four core catalogue items,
three core immutable catalogue releases, a light/dark theme family with two immutable
theme releases, default appearance variants, two editor favorites and a separate
visual editor favorite. Reference-picker load data adds82 items/491 releases and81
editor favorites (including one item with250 versions and an old nonfavorite), for86
items/494 catalogue releases in total. These extra archives are schema/inventory validated,
not claimed to have passed publication workers; acceptance also inserts/renders the old
release and publishes its own two-version source through real workers. The shared
catalogue is still visible to readers; favorites and captured previews are personal.
Seed names, labels, palette and displayed timestamps are stable; generated IDs and
credentials remain random. Five pinned real font families support the existing font
tests, including Manrope's unsupported italic case. The old hardcoded GitHub theme
dependency is replaced by the seeded theme release. Only image copies of hashed
repository fixtures have CRLF normalized; working fixture files are preserved.

## Failure, interruption and artifacts

Normal runs clean up on success, failure, SIGINT and SIGTERM. SIGKILL, daemon loss or
host shutdown cannot execute cleanup: use the exact printed cleanup command after
recovery. `browser-start` and `run.py run --keep` deliberately retain only that run's
resources and mode-0600 private files for debugging. Cleanup verifies Docker labels,
mounts and networks before removing the exact Compose project; it never prunes Docker
or deletes catalogue rows by a user-supplied prefix. Keep the receipt/private files
until cleanup finishes. No deployment or branch push is part of these commands.
Per-run worker image tags are removed; reusable locked app/browser images and Docker
build cache remain available for the next run.

Artifacts remain under the run's `artifacts/invocation-*/`: a bounded browser log,
accessibility reports, and failure screenshots/context/pixel diffs. Each invocation
has its own directory. Playwright stops after ten failures. Traces stay disabled for
credential flows because network/snapshot traces can capture secrets. Profile/registry
teardown scrubs one-time fields before automatic screenshots; textual artifacts
redact account secrets, tokens, JWTs and long hexadecimal secrets/hashes. Private
credentials are outside artifacts and are removed at teardown. Do not upload the
whole run directory or retained secrets to CI artifacts.

## Engines, accessibility and visual review

The digest-pinned Playwright 1.64.1 Noble image supplies Chromium, Firefox and WebKit;
engines are not downloaded during a run. Chromium retains the complete existing
suite. Firefox and WebKit run the four `critical.spec.js` flows: navigation and
personal favorites/reader denial, immutable reference/property persistence and unsaved
navigation, mobile theme disclosure/showcase dialog focus, and read-only published
catalogue previewing. `studio.test` is a
per-project network alias: WebKit rejects the single-label host's session cookies.

The focused flows run locked `@axe-core/playwright` 4.14.0 with WCAG 2 A/AA and 2.1 A/AA
tags. Reports retain violations and incomplete findings. No rules are broadly disabled.
Preview iframes and `#component-showcase` are excluded from **application-control**
audits: untrusted/generated content and independently chosen demonstration palettes
need their own diagnostics. Keyboard interaction with trusted showcase dialogs is
still exercised. These checks do not certify full WCAG conformance or screen-reader
compatibility.

`visual.spec.js` compares twelve Chromium/Linux images: catalogue, composer and theme
detail, each at 390px/1440px and light/dark. Data and locale/timezone are fixed; preview
completion, stylesheets and fonts must finish before capture. Reduced motion and
disabled screenshot animations/caret stabilize these tests without hiding loaders or
masking controls. There are no screenshot masks. Other existing tests keep normal
motion so their timer/reduced-motion assertions retain meaning.

`workspace.spec.js` checks reader/editor homepage permissions, personal item/theme
shortcuts, keyboard destinations and layout at 390px/1440px in light/dark mode. Its
eight full-page screenshots and axe reports are review artifacts, rather than pixel
baselines. `bootstrap.spec.js` creates, resumes and saves a real draft from the homepage.
PHPUnit covers ordering, limits, missing/deleted targets and independent empty states.

`shell.spec.js` adds 320px/390px/1440px light/dark disclosure, account, active-link and
recovery checks, long translated labels/emails, keyboard dismissal/restoration and Turbo
back navigation. It also exercises an unreadable saved/trial theme, a real recovery
failure/retry, no-JavaScript forms/navigation/logout, touch drawer recovery and 200% text
reflow plus Chromium's 2× pinch zoom. The existing critical suite checks modal recovery
and Tab containment in all three engines. These are automated checks, not a manual
screen-reader session or browser-UI zoom certification. Shell geometry and contrast
audits do not establish application-wide WCAG conformance.

`theme_actions.spec.js` distinguishes read-only draft browsing, exact published-release
selection and application appearance, including a publication between form display and
submit, a draft-only opposite variant and reader denial. Six width/mode cases cover
320px/390px/1440px, keyboard navigation, wrapped labels and application-control axe scans.
Their screenshots/reports are retained as review artifacts. Existing showcase tests
continue to check exports, renderer selection, responsive layout and opaque editor frames.

Baselines live in `tests/Browser/baselines/chromium/`. Missing or changed images fail;
ordinary runs never write baselines. To generate reviewable candidates deliberately:

```sh
make browser-baselines
# Review the PNG changes before committing. CI refuses this operation.
```

To prove detection on a retained run, inject a test-only heading size regression:

```sh
python3 tests/BrowserHarness/run.py test --run-id "$RUN" --visual-probe -- \
  tests/Browser/visual.spec.js --project=chromium --grep '1440px light'
```

This command succeeds only when a pixel comparison fails and all baseline hashes
remain unchanged. It preserves the deliberate failure/diff as evidence.

Additional live safety checks, using two exact owned runs:

```sh
python3 tests/BrowserHarness/safety.py "$RUN" "$SECOND_RUN"
python3 tests/BrowserHarness/lifecycle.py
```

The first checks idempotent seeding, mismatched run/database/blob rejection and
unchanged row counts, and compares live run resource names to development configuration
without modifying working data. The second executes real failed/interrupted normal
commands and checks that no matching containers, volumes, networks or credentials remain.

## Manual verification checklist

Use disposable data, never the working library. Automation exercises keyboard actions;
it does not imply a human used a keyboard or screen reader. The default harness exposes
no host port; interactive review requires environment-specific local forwarding. Internal
Docker networks may ignore published ports, so verify forwarding before reporting a
manual check. No interactive forwarding is installed by this harness.

- With Tab/Shift+Tab, follow navigation, Appearance and profile controls. Open disclosures
  with Enter/Space, close dialogs with Escape, and check visible focus/restoration.
- Star an item, enable Favorites only, navigate back and repeat as another account. Check
  pressed-state and pending/failure announcements without an unexpected reload.
- Insert a released reference, navigate the tree with arrows, edit existing JSON props,
  cancel leaving unsaved work, save and reload. Check labels and status announcements.
- At narrow/wide widths and light/dark appearance, inspect reading order, overflow and
  contrast of application controls separately from the theme demonstration.
- With a real screen reader (for example NVDA/Firefox or VoiceOver/Safari), check landmark
  names, disclosure state, star state, tree selection, validation/status announcements
  and modal focus. Record reader/browser versions and any unverified items.

See `agent-kit/STATUS.md` for measured checks and explicitly unrun manual checks.

## Existing integration drivers

The maintained production appearance/profile commands now use the same ownership
challenge, isolated acquisition fixtures, internal networks and scoped teardown. They
build immutable production assets and can run without a development app. Their host
port defaults to an automatically assigned loopback port.

The older `consumer`, `font-consumer` and production backup/recovery drivers now check
database/blob ownership **before** fixture/account/publication/restart mutations. They
cannot operate on the working studio. Their additional consumer/restore topology has
not been migrated into `compose.browser.yaml`; those full integration flows require
that separate follow-up and were not rerun here. CI now runs the owned PHP/browser
lifecycle and immutable profile/composer/authentication checks instead of those unsafe
legacy targets. The existing PHPUnit installer/portable-contract coverage is retained;
this is not a fresh claim about complete consumer installation or restore acceptance.


`editing_protection.spec.js` covers source/composition/theme dirty header and Back/Forward
navigation, native reload/close cancellation, explicit discard, failed and delayed saves,
invalid JSON entered during a pending source save, external conflicts, theme family labels
and inactive variants. The critical suite also checks canceled Back in all three engines.
`composition_history.spec.js` checks four 390/1440 light/dark history panels with keyboard,
focus, axe and review screenshots, explicit local discard, restore persistence/preview,
external-write conflict/reconciliation, empty history and paging beyond twenty records.
The standalone production driver explicitly accepts these two specs and retains its
owned-resource checks. Native confirmation behavior is browser-controlled; automated
checks are not human screen-reader or browser-UI certification. See EDITING.md/STATUS.md.


`discovery.spec.js` exercises direct URLs and Back/Forward for catalogue, themes and
fonts at 390/1440 widths in light/dark, with keyboard, axe and review captures. It
checks IME composition, actual delayed/aborted older GETs, HTTP/network/missing-frame
failure recovery, caret continuity and long chips. Owned thirteen-favorite fixtures
prove confirmed removal, complete count/page refresh, last-page clamp, empty results,
explicit Undo success/failure and later-filter exclusions; regular listings keep cards.
A JavaScript-disabled context verifies all three GET forms. Existing critical checks
exercise the same enhanced filter/favorites controls across three engines. The production
driver explicitly accepts this spec and retains its ownership/immutable-asset gates.

The existing critical dialog workflow also freezes its opening fade during a keyboard
check: transparent controls remain in native tab order, while hidden/disabled controls
stay excluded. Test styling is restored before capture. This regression rejects the
previous opacity-based focus filter; normal focus wrapping and Escape restoration
are still asserted in all three engines.


Catalogue detail coverage is in `catalogue_preview.spec.js`, including twelve retained
screenshots/axe reports (component, wide block, tall layout at390/1440 in light/dark),
actual isolated publications/draft failures, reader-only previewing, sample/theme URLs,
obsolete document rejection and explicitly requested cached thumbnails. The critical
suite additionally checks the reader preview in Chromium/Firefox/WebKit. Status-order
transport fixtures are labelled separately from real worker checks. See
CATALOGUE_PREVIEWS.md and the prompt06 STATUS entry for commands and measured outcomes.


`make browser BROWSER_ARGS='tests/Browser/reference_picker.spec.js tests/Browser/composition.spec.js tests/Browser/critical.spec.js'`
checks bounded discovery, exact versions, stale responses/IME, unavailable metadata,
unsaved props, native modal keyboard/focus and no-JavaScript browsing. The picker suite
also captures390/1440 light/dark screenshots and axe reports.
`bash tests/AppearanceProduction/run.sh tests/Browser/reference_picker.spec.js tests/Browser/composition.spec.js tests/Browser/compose_mcp.spec.js`
uses the same verified disposable ownership guard and seeds load data through a
maintenance-only read-only mount; its private fixture metadata is removed at teardown.
`tests/ReferencePicker/measure.php` measures real authenticated HTML/JSON bytes and SQL
counts with the profiler only inside a verified owned run. It is not a production route.


## Composition property inspector

`instance_properties.spec.js` creates actual alert releases and compositions only after
harness ownership verification. Its edge schema is shared with a functional manual/MCP
parity check. Coverage includes exact persisted primitive/structured/unknown values,
omitted defaults, invalid text across selection/modes/Turbo navigation, node-specific
server errors, held preview responses, replacement schemas and source/catalogue control
reuse. Four keyboard/axe scenarios retain fresh 390/1440 light/dark screenshots.
The critical composer flow edits its message through the form in all three engines;
existing JSON command tests deliberately open Advanced JSON. The production runner
accepts this spec and executes it against immutable AssetMapper output. Use the existing
owned harness and cleanup workflow above; no working catalogue fixtures are permitted.

The source debounce check installs its clock before navigation at a fixed initial
time, then pauses at a fixed later virtual time. It never compares the emulated clock
to host Date.now; the 299ms/1ms assertions remain intact. This follows the installed
[Playwright clock workflow](https://playwright.dev/docs/api/class-clock#clock-pause-at).


Prompt 09 adds `font_pairing_contrast.spec.js`: requested face/subset byte hashes,
no eager/third-party pairing downloads, real family/weight/style rendering, unavailable
faces, declared-coverage and decode fallbacks, draft-preserving explicit transfer
including captured normal-only family definitions,
save/export consistency, unchanged application appearance, unsaved/saved contrast,
unsupported alpha/intermediate input, focusable token/example links, delayed responses
and 390/1440 px light/dark keyboard/axe checks. It is also accepted by the immutable
production harness. See FONT_PAIRING_AND_CONTRAST.md and the measured STATUS handoff.
When rebuilding an existing disposable run, clear its dev cache before recreating the
app/consumer: that topology intentionally uses APP_DEBUG=0 and retains its runtime volume.


Prompt 10 adds read_performance.spec.js for initial current-only appearance options,
on-demand loading, preserved selections, failed-load reopen/retry, cancellation and
390/1440 light/dark native keyboard/axe checks. The immutable-production driver accepts
this spec. Functional ReadPerformanceTest checks real query bounds, sorting/counts,
MCP scope-before-pagination, draft/editor/favorite/top-ten-release semantics, empty
results and explicit bounded/restartable legacy-family backfill. Query instrumentation
is enabled only in its test kernel; MCP persisted profiling stays disabled. Use
python3 tests/ReadPerformance/run.py with an exact owned run for deterministic large
read fixtures and separate in-process/HTTP measurements. See CATALOGUE_READ_PERFORMANCE.md
for safe commands and actual results; no elapsed-time threshold is part of a test.
