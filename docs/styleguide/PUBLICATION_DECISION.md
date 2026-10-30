# Publication decision and recovery

Decision for improvement prompt 11, measured 2026-10-30 against application commit
9a1189c: **retain synchronous publication for the measured interactive workload**.
No publication job entity, queue routing, status endpoint, polling, cancellation,
resumable staging or automatic retry was added. Existing preview jobs and their
Doctrine Messenger consumer are unchanged.

The benchmark gate was set before changing application code. The repository's existing
release acceptance waits 20 seconds for acknowledgement and browser navigation uses
a 20-second timeout. That existing interaction budget is a practical decision threshold,
not a contractual SLA or a universal acceptable duration. All measured supported
profiles fit it, with the large profile leaving only about 2.25 seconds of headroom
in the five-sample series (2.07 seconds for its excluded first response).
This supports a narrow decision to keep the simpler current workflow, not a claim that
all allowed workloads or concurrent production traffic will always fit.

Revisit durable orchestration if supported workloads repeatedly exceed that budget,
a deployed proxy has a shorter deadline, contention regularly interrupts publication,
the 20-example limit increases, or the product requires progress/resumption across
web-process loss. A queue would make the workflow durable; it would not increase the
single compiler's throughput. If adopted later, reuse the current Doctrine transport,
app-side consumer and isolated workers, with captured inputs, deduplication, execution-time
authorization, leased completion, immutable artifact staging and explicit recovery rules.
Those job guarantees are unimplemented in this change.

## What the user sees

The native Publish release form describes the limit and the synchronous request.
After a valid submit, its focused Stimulus controller disables repeated submissions
from that document and shows the actual saved revision/version being requested.
The text describes validation before commit; it does not invent a backend stage,
percentage, durable job or cancellation guarantee. The usual successful redirect and
`Release published immutably.` acknowledgement remain unchanged. Native validation
runs before the submitting state; Back/pageshow restores the form's controls. Without
JavaScript the same form/CSRF/POST/redirect workflow still works.

If the connection is lost, **reload the item page and check that exact version in
Releases before retrying**. Navigating away or aborting the HTTP response does not
confirm server-side cancellation. A request can finish and commit after the client
has stopped waiting. An exact retry with the unchanged expected draft revision receives HTTP409 because
the immutable version exists; it does not replace it or return a deduplicated successful operation receipt.
The reader-authorized item page and Preview release / immutable source ZIP links let
an editor inspect that result. The private registry's existing version/manifest/archive
routes also support reconciliation with an appropriately scoped registry read token.
Do not infer an outcome solely from a network error or silently allocate a new version.

A validation failure (422) or stale draft revision (412) requires correcting/reloading
the draft and an explicit new submission. Check for an existing version after any
uncertain outcome. The API keeps its sanitized error contract and request receipt;
HTTP503/worker_unavailable does not expose worker addresses, credentials or stack traces.
A known failed worker attempt with no release can be retried explicitly after recovery.
No job will resume automatically after a web-process restart, and no durable progress
can be recovered from another page.

## Existing execution and integrity limits

Publication requires ROLE_EDITOR, valid CSRF, a stable requested SemVer and the expected
saved draft revision. It validates schemas/defaults/examples, resolves exact immutable
dependencies, then renders and builds defaults plus each example. The shared package
validator permits 30 stored examples, while publication has an existing stricter limit
of **20 examples plus defaults**. The form now states the publication limit.

The captured source objects are copied before work. Immediately before inserting the
release the service refreshes and optimistically checks the draft revision. A newer
save rejects publication with412 rather than publishing that new work. An immutable
(item,version) database constraint defines conflicts; same-version retries with a matching draft revision get409; a stale revision is
checked first and returns412.

Current limits, verified in source and the real Docker setup:

| Boundary | Limit |
| --- | --- |
| Studio PHP | 256 MiB memory; PHP execution settings are not an aggregate wall-clock guarantee |
| Per source file / total package text | 1 MiB /2 MiB; dependency closure2 MiB and200 files |
| Worker input | 3 MiB JSON per render/build request |
| Render child | 2 seconds wall time,128 MiB, bounded AST/depth and1 MiB HTML |
| Render client call | 5-second deadline including transport |
| Compiler child | 10-second supervisor; nested Tailwind7 seconds; fixed memory/output caps |
| Build client call | 12 seconds including bounded busy retry |
| Compiler concurrency | One build at a time; HTTP429 busy is retried inside that per-call deadline |
| Immutable source ZIP | 10 MiB; bounded inventory and content hashes |

These per-call bounds repeat for every example; 21 pairs must not be mistaken for one
17-second overall deadline. The checked-in Caddy app/reverse-proxy configuration adds
no explicit total publication response deadline. Other operator proxy/client deadlines
must be checked before adopting these local observations. The benchmark client allows
120 seconds so the 20-second decision threshold is observed rather than imposed.

ArchiveStore writes a complete temporary ZIP, atomically installs its content-addressed
blob and only then commits the release row. Every measured success was read back through
the actual PackageArchive/ReleaseSourceReader inventory, checksum and manifest checks,
with sources matching the fixture. A crash after blob installation but before the DB
commit can leave an unreferenced blob; it is not a visible release. Existing owned
storage cleanup/retention rules apply. No new generic deletion or cleanup of published
outputs is introduced. A thumbnail is derived/optional and is not a completion gate
for the immutable source package.

## Measurements

Owned run `browser-04e5a62bb7375548a0b637c8`, real authenticated editor and the existing
FrankenPHP1.13.4 /PHP8.5.12 /Symfony8.1.9 /PostgreSQL17.11 stack. Linux x86_64 under WSL2,
Core i5-12500H,10 logical CPUs visible to Docker, PHP memory limit256 MiB. Existing
image/dependency locks were reused. The source fixtures use real portable Stimulus,
Twig, schemas and examples; the larger profile has two sizeable bounded Twig files.
No delay, stub worker, cached success or simulated publication is used.

Real HTTP to the serving dev/debug=false app over the internal Docker network, no
other preview/build traffic, one excluded first publication followed by five measured
new immutable versions per profile. The first response of each case is retained in
logs. Each worker render/build uses its real subprocess/toolchain. These are warm
local observations; no cold-host, concurrency, production load or throughput claim.

| Profile | Examples + default | Source bytes /files | Median ms | Min–max ms |
| --- | ---: | ---: | ---: | ---: |
| Normal | 1 +1 | 1092 /6 | 1471.138 | 1409.404–1600.393 |
| Example-heavy | 20 +1 | 3970 /6 | 15510.319 | 15215.166–15643.659 |
| Large source + examples | 20 +1 | 1409969 /7 | 17486.554 | 17252.898–17746.342 |

The excluded first responses were1614.189,15619.701 and17934.711 ms respectively.
Curl connect/TTFB/total time and response/archive sizes are retained separately; response
bodies or cookies are not exported. This is whole-request time, not an isolated compiler
or database timing claim. The application execution path is retained, so no artificial
before/after performance improvement is claimed for the guidance change.

A real late-example division-by-zero failure returned422/release_build_failed after
2254.715 ms: zero visible releases and zero new archives. The 21-example draft was
stored through the normal valid-draft API and publication rejected it in34.405 ms,
without worker output or archives. These are publication validation results, not mocks.

A real client timeout after150.522 ms on the20-example profile observed exactly one
complete9.0.0 release around16.0 seconds after disconnect. Its source ZIP matched the
requested fixture; an exact retry returned409 in29.778 ms. This demonstrates both the
uncertain client outcome and a safe reconciliation path. It does not demonstrate a
durable job or promise that every disconnected request completes.

Stopping only the owned compiler service caused503/worker_unavailable in119.094 ms,
with no release. Restarting that exact service and explicitly retrying the same version
succeeded in1533.095 ms with a complete matching ZIP. Other failure/interruption phases,
host failure, concurrent large publications and lease recovery are not claimed here.

## Reproduce without touching working content

```sh
make browser-start
# Use the exact printed RUN, whose private ownership receipt must remain present.
python3 tests/PublicationBenchmark/run.py --run-id "$RUN"
make browser-cleanup RUN="$RUN"
```

The driver checks topology/resource ownership, rebuilds its app snapshot, clears the
owned APP_DEBUG=0 dev cache, and creates fixtures through real login/CSRF/create/save
HTTP calls. Both database/blob proof and an HMAC challenge pin the HTTP target before
login. Existing fixture receipts/measurement versions are refused. Use a fresh run if
seeding or measurement is interrupted; do not overwrite partial evidence or reseed.
The worker-failure probe stops only that exact run's compiler under its lifecycle lock
and restarts it in `finally`. The driver prints the exact cleanup command; resources
remain available until explicit cleanup, including after a failed run.

Raw samples and outcomes are retained as publication-baseline.json,
publication-worker-unavailable.json and publication-worker-retry.json in the owned
artifacts directory. Fixture/account/ownership receipts and cookies remain private and
are deleted by exact-run cleanup. Artifact sanitation also removes hash-like fields;
the source equality assertions run before that step and retain their boolean outcomes.
The initial baseline was captured before application edits; its already measured run
then used `--faults-only` to finish the worker-outage/retry probes without reseeding.

See STATUS.md for actual Docker lint/build/test/UI commands, corrected failures,
responsive keyboard/axe evidence, changed files and resource cleanup.
