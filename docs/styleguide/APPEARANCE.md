# Personal application appearance

The updated development application is at **http://localhost:8095**. Open **Themes**,
choose **Apply to application**, and the actual application shell uses that family's saved token
snapshots. The header shows the active family and mode. **Browse component showcase** opens the selected saved draft without changing settings.
**Preview published theme in component editor** saves separate renderer settings for the
sample component and opens its editor, with the selected immutable version identified. Applying an application theme
does not change a component, composition, renderer preset or export selection.

The existing local production instance at port 8096 uses its independently built image,
database and accounts. Restarting that older image does not install development changes.
The production verification described below uses a separate, disposable local project.

## Choosing an appearance

- The crescent moon means **Switch to dark mode**; the sun means **Switch to light mode**.
  The real button's accessible name and native tooltip describe the same action. Both
  variants stay in the selected family; there is no palette inversion.
- The **Appearance** menu selects families, offers **Use system appearance**, and shows
  available modes and the selected snapshot revisions. System mode follows OS changes.
  Clicking the moon/sun selects an explicit mode; later OS changes then have no effect.
- A single-variant family keeps the requested light/dark/system preference while using
  its available variant. The unavailable toggle is disabled, with an associated reason
  and an editor link. Adding another variant does not silently change previously applied
  snapshots: apply the updated family to select it.
- **Restore default appearance** selects the complete built-in light/dark family and
  system mode. Its independently legible strip reserves document space below the
  compact header controls. **Skip to appearance recovery** reaches it by keyboard.
  Native dialogs and drawers include their own recovery form inside the modal content.

## Navigation and recovery

At narrow widths, **Navigation** opens ordinary links; **Account** contains the full
email, Profile, Sign out and administrator Read tokens. The primary moon/sun mode control
stays visible beside **Appearance**. Desktop primary links remain inline. Active links
use a visible underline and `aria-current` (`location` for a parent section). Native
disclosures work without JavaScript; enhancement adds Escape/focus restoration, outside
click/focus dismissal and cleanup on Turbo visits. They do not trap focus.

Recovery uses fixed safe colours, fonts, borders, focus outlines and 44px controls rather
than selected theme tokens. It never floats over editor fields. During a trial the strip
explains **Revert trial** (restore the saved appearance and keep unsaved editor text) versus
**Restore default appearance** (save Studio default with system mode). Errors gain a safe
explanation and retry path. Save & Apply trial keeps its existing editor behavior.

Modal dialogs make the document behind them inert. The shell adds a safe recovery form
within the dialog's scrollable content, alongside its existing actions; native modal
focus containment and Escape remain. Resetting does not close the dialog or discard
editor inputs. Layer order is document/recovery/content, shell disclosures, then native
top-layer dialogs. Disclosures open below the recovery strip, so reset remains exposed
even when a menu is open. The full-width showcase and details sidebar keep their layout.

With JavaScript, reset uses the existing prepare/load/commit flow in place. Without it,
the CSRF-protected `POST /appearance/reset` form reuses AppearanceManager, reads the
current preference revision, and selects only the built-in default/system mode. It
redirects to Workspace (or Sign in for guests); the no-JS strip tells users to save edits
first because this is a full navigation. Guest reset uses the existing mode cookie;
it cannot select catalogue themes. No new migration or appearance-storage format exists.

Shell controls aim for 44px touch targets. This product target does not establish
conformance: [WCAG 2.2 target-size guidance](https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum.html)
defines a 24px minimum with exceptions. [Focus-not-obscured guidance](https://www.w3.org/WAI/WCAG22/Understanding/focus-not-obscured-minimum.html)
and the [APG disclosure navigation pattern](https://www.w3.org/WAI/ARIA/apg/patterns/disclosure/examples/disclosure-navigation/)
inform the reserved strip, ordinary links and focus restoration; assistive-technology
and manual contrast checks remain separate from automated tests.

Application backgrounds, navigation/header, menu/sidebar, cards, forms, tables, alerts,
native dialogs, buttons and body/heading/code typography inherit semantic tokens. The
intentional device-frame colour and content swatches remain independent. Preview iframe
documents continue to own their styles and keep their existing opaque-origin sandbox.

## Creating and editing

The existing editor supports a stable namespace/slug, editable family display name,
palette, geometry and local/system fonts. **New theme** offers a complete starting pair
or a single variant. The primary editor keeps its existing starting palette; the opposite
variant starts from the complete built-in palette for that mode, retaining chosen fonts
and geometry. You can also **Duplicate/Edit** either built-in or imported families to
copy their complete existing pair. Managed originals remain read-only.

Each variant has a fixed light/dark identity. **Edit family variant** switches within
the same editor, retaining each variant's unsaved inputs. A single-variant editor offers
**Create light variant** or **Create dark variant**; the new palette is explicitly a
starting design, not an assertion that a referenced website uses that mode. Renaming the
family display name preserves its IDs and users' snapshot references.

| Action | Theme data | Application appearance |
| --- | --- | --- |
| Apply to application | Uses validated saved revisions of the available variants | Saves the current user's family/snapshot selection, preserving mode preference |
| Save theme / Create theme | Saves valid data with optimistic revision checks | Keeps the previously applied snapshots |
| Save & Apply | Saves, then prepares and loads the saved family's CSS before selecting it | Updates only the current user's preference |
| Try on application | Does not save the theme | Temporarily applies valid editor tokens in this tab |
| Revert trial | Leaves entered text intact | Restores the pre-trial saved selection |

While a trial is active, valid edits are debounced and a **Trying unsaved changes** banner
offers Revert and Save & Apply. Invalid tokens or failed CSS leave the last valid trial
visible and report the failure. Switching trial variants preserves the original rollback
target. Saving ends the trial; ordinary Save restores the applied snapshot, and Save &
Apply selects the saved revision. Leaving/reloading the editor restores saved appearance.
Unsaved text is guarded on navigation. Trials never update account preferences or other
tabs/users. System-mode rollback resolves the current OS mode rather than freezing an old
OS setting.

If saving succeeds but preparation, stylesheet loading or preference persistence fails,
the theme stays saved and the confirmed appearance stays active. The editor reports the
partial result and offers **Retry Apply saved theme**, including after first creation's
redirect. A missing/corrupt browser font produces a visible fallback notice and readable
system fonts. A missing saved source theme restores the default preference with a notice.

## Persistence and migration

`Version20261027143000` adds three tables:

| Table | Responsibility |
| --- | --- |
| theme_family | Stable ID/key and editable label, explicit nullable light/dark Theme references, built-in flag, optimistic revision |
| appearance_snapshot | Shared immutable saved-revision token/CSS snapshot, source revision, CSS SHA-256; owner and expiry for temporary trials |
| appearance_preference | Current user's family, light/dark snapshot references, requested mode and optimistic revision |

Existing Theme documents, package releases and renderer preferences are not rewritten.
The migration pairs only explicitly managed `website-inspired` records with matching
reference provenance. Independently created themes and editable copies remain separate
single-variant families; display labels and slug suffixes are never used to guess pairs.
New/imported themes are assigned through the existing writer. The built-in default pair
is initialized from validated, versioned palettes without replacing a colliding user name.

Application snapshots are shared per theme revision, not copies of full documents in
every user record. They are separate from immutable semantic package releases: applying a
saved draft requires neither a fabricated SemVer nor publishing a component package.
Saving another revision does not update existing users' snapshots. Foreign keys preserve
snapshots and allow default recovery when a source becomes unavailable.

The portable theme/package contract stays at schemaVersion 1. Individual variant exports,
font inventories/licences and package checksums retain the existing format. Application
family assignments and personal preferences live in the database and its normal backups;
they do not silently appear as unsupported fields in existing exported theme documents.

## Runtime assets and navigation

`ThemeCssGenerator::applicationCss()` reuses the shared validated semantic colours,
geometry and typography. It emits ordinary CSS declarations and only the selected real
font faces/styles/weight ranges. It does not run Tailwind, website code or imported CSS.
The controlled utility/preview compiler remains unchanged and network-restricted.

CSS lives in immutable database snapshots, using the same persistence approach as existing
theme release outputs. URLs include snapshot and CSS hashes under
`/appearance/assets/<snapshot>-<sha256>.css`; they never write AssetMapper's production
manifest. Saved styles require catalogue reader access and are privately cacheable.
Trial styles additionally require their owner, expire after one hour and use `no-store`.
The built-in system-font pair is the only CSS available to anonymous login pages. Every
request checks access before ETag handling. Font URLs use the existing registered local
asset endpoint; no runtime font-CDN requests are introduced.

Initial and dynamically loaded stylesheet links carry SHA-256 subresource integrity.
The controller requires real, non-empty stylesheet rules before confirming a selection;
an empty or corrupt response cannot become an Applied state. A failed saved stylesheet
on reload reports the fallback and leaves Restore default reachable.

Only generated selectors based on validated snapshot hashes reach the application DOM.
The root carries `data-theme`, `data-color-mode`, family and variant snapshot attributes.
Unlayered scoped runtime rules override compiled defaults; `color-scheme` follows the
effective mode. A single Tailwind 4 custom variant follows `data-color-mode`, so a newly
created family needs no new theme-name list or utility rebuild. The appearance controller
preloads candidate styles before changing root state or committing preference, fences
sequences, handles stale revisions and bounds requests/style loads to 15 seconds. A lost
success response is reconciled and rolled back to the last confirmed selection.

Authenticated preferences are authoritative. Anonymous login appearance uses a signed,
HttpOnly, SameSite=Lax mode-only cookie; malformed cookies fall back to system. Guest
cookies and another account's browser cache never override authenticated preferences.
There is no localStorage preference cache. Precedence is temporary trial, authenticated
saved preference (or guest cookie), then default family/system mode.

Initial Twig HTML includes the saved snapshot links and a small blocking local bootstrap
that resolves system mode before paint. Both candidate mode styles are available; font
swapping is separate. Personalized HTML is private/no-store. Existing Turbo navigation
uses dynamic head links, cleans listeners/controllers, and disables personalized snapshot
caching so restoration fetches authoritative state. Browser pageshow handles native cache
restoration. Obsolete appearance links are removed without touching other stylesheets.

Prepared selections are session-bound to the account, tab client and increasing sequence,
valid for five minutes, with at most eight tab slots. Trial history is bounded to 32
snapshots per owner and pruned on later trial requests. Tokens/documents remain subject
to the shared 64 KiB theme limit; appearance requests are capped at 256 KiB and generated
CSS at 64 KiB. Existing imports never gain permission to run JavaScript in the studio.

## Operator and verification commands

Run migrations in Docker, including an existing test database:

```sh
docker compose exec -T app php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec -T app php bin/console doctrine:migrations:migrate --env=test --no-interaction
docker compose exec -T app php bin/console doctrine:schema:validate
make assets
docker compose exec -T -e APP_ENV=test app php bin/phpunit
make browser
make appearance-production
make font-consumer
```

`appearance-production` creates a uniquely named, isolated production project under
ignored `var/appearance-*`, using already pinned dependencies and real immutable image
builds. It migrates its fresh database and registers three real font families from the
verified offline staging store. After production asset compilation it creates, edits,
trials and applies new families/variants through the browser and checks real computed
colours, native control mode, decoded fonts, independent preview settings and account
isolation. It verifies the read-only AssetMapper manifest hash before and after. Its
owned project, volumes and private credentials are removed after verification; sanitized
evidence remains available for review.
Existing development, production and consumer data are preserved.
Production and development browser runs use separate artifact directories, so they can
run against their isolated services without overwriting each other's traces.

Evidence and the completed acceptance mapping are in [VERIFICATION.md](VERIFICATION.md).
Runtime screenshots, font/renderer evidence, baseline hashes and production manifest hashes
are retained under `var/appearance-verification` and the unique production fixture.

Implementation references checked against the installed versions:
[daisyUI themes](https://daisyui.com/docs/themes/),
[Tailwind attribute-driven dark mode](https://tailwindcss.com/docs/dark-mode),
[Turbo head/lifecycle rules](https://turbo.hotwired.dev/handbook/building),
[Turbo dynamic attributes](https://turbo.hotwired.dev/reference/attributes), and
[Playwright clock](https://playwright.dev/docs/clock) for the existing editor debounce test.
