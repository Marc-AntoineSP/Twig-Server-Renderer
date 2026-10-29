# Catalogue detail previews

Open a component, block or layout from the catalogue and choose **Load preview**.
The main panel identifies the exact published release. Nothing compiles simply by
visiting a detail page. Named examples and package defaults come from that selected
archive; changing an example, theme or temporary property rebuilds only after the
first explicit load. A different source version starts at its own defaults and waits
for another explicit load. Release, example and immutable theme IDs remain in the URL
for reloads and sharing. Catalogue return links keep the discovery query.

Readers can evaluate published releases without entering an editor. Editors can also
select the current **Saved draft rN**. A draft request captures that revision; if
another save wins first, the request fails with a reload instruction. A draft-only
item tells readers **No published preview**. Missing named examples use the actual
package defaults and give editors an Edit draft link. No unrelated release supplies
a substitute result. Source files match the selected source version and stay escaped
text. The information sidebar retains release-specific source downloads, export
formats/themes/props, installation commands, publication and duplication actions.
It stacks below the preview on narrow screens.

**Properties & renderer settings** exposes schema-driven scalar fields, JSON props,
fonts, text size, surface, colour scheme and zoom. These are temporary controls;
loading or inspecting never saves source, renderer presets or appearance preferences.
The existing editor's explicit Save renderer settings action remains independent.
Width presets and Fit width reuse the renderer viewport; 320/560/800px height controls
bound the scroll region. Keyboard users can focus that region to scroll wide/tall
content. Valid frame resize messages can resize only the internal document, up to
4000px; the surrounding page region remains bounded. Content beyond that height
scrolls inside the frame. Disable scripts and Reset preview remain available.

The existing queued/running/failed/expired job lifecycle supplies status. Failures show
the worker diagnostic and Load preview retries with a new job. Changes mark the last
successful document stale while waiting; obsolete requests/documents cannot become
current. There are no synthetic progress percentages. Explicit version navigation
removes the prior frame rather than relabelling it as another revision.

The reader endpoint `POST /api/v1/catalogue-previews/{itemId}` accepts a release ID
or an editor-only expected draft revision, an example index, transient props, bounded
RendererSettings, sequence and frame channel. It accepts no source, manifest or theme
code payload. Normal session/CSRF checks and the shared reader catalogue still apply.
Preview job status, document and cancellation remain owner-only, private/no-store;
the unrestricted source-preview creation endpoint remains editor-only.

Rendering still uses PreviewJobs, the finite dependency loader, bounded renderer and
fixed compiler workers, DocumentAssembler CSP and an opaque `sandbox="allow-scripts"`
iframe. User markup never enters the studio DOM. Local demonstration buttons/dialogs
can operate inside that frame; native form submission, connections and parent access
remain blocked. Authors should use local button handlers for demonstrations rather
than expecting sandboxed form submission to reach an application endpoint.

Successful unexpired output can be reused only for the same account and complete
rendering inputs: captured release/archive or draft revision, examples/props, dependency
sources, immutable theme/document, preset and toolchain/policy digest. Each job keeps
its own sequence, channel, nonce and owner authorization. Cached body/CSS/JS are
assembled again with the new frame channel; missing/expired results fall back to the
same bounded workers. Existing 30-minute TTL, 60-job owner history and storage quotas
also bound this cache. There is no public private-output cache or new persistent store.

Catalogue cards reserve the same 16:9 preview region. **Show published preview** loads
one available cached release document on demand, scripts disabled and scaled to fit.
The listing initially mounts no preview documents and queues no jobs. Published
archives without a cached thumbnail link to their exact detail preview; unpublished
items say **No published preview**. No thumbnail-generation service is added.

Verification uses owned disposable stores, never the working catalogue:

```sh
python3 tests/BrowserHarness/run.py run --with-checks --keep -- \
  tests/Browser/catalogue_preview.spec.js tests/Browser/release.spec.js \
  tests/Browser/transfer.spec.js tests/Browser/critical.spec.js
# After the retained run is idle, use its exact printed ID:
python3 tests/BrowserHarness/preview-cache.py --run-id browser-<24-hex-ID>
make browser-cleanup RUN=browser-<24-hex-ID>
bash tests/AppearanceProduction/run.sh tests/Browser/catalogue_preview.spec.js tests/Browser/preview.spec.js
```

The new detail scenarios cover published reader access, correct versions/examples,
new publications and saved drafts, stale responses, diagnostic/retry states, missing
examples, local dialogs/forms, strict frame messages, bounded thumbnails, and a small
component/wide block/tall layout at 390/1440px in light/dark. Critical reader preview
coverage also runs in Firefox and WebKit. Simulated transport status ordering is
separate from the real invalid-Twig/worker/cache checks recorded in STATUS.md.

`preview-cache.py` takes the lifecycle lock, verifies the run's database/blob ownership,
warms an exact published release/theme with real workers, stops only that run's two
workers, and verifies reuse with a fresh channel. Changed props and another account
must fail while the workers are unavailable. A finally block restarts both workers.
Use it only after ordinary tests finish; it refuses a busy/unowned run.
