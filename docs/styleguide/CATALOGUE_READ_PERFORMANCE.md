# Catalogue reads and legacy family maintenance

Catalogue pages still select/count/filter before loading their twelve IDs. A single
bounded entity fetch restores that exact ID order in PHP. No collection joins, cache,
search service or schema/index change is involved. A deletion between the statements
omits the missing card without refilling the page; counts represent the earlier count
snapshot and a later request clamps the page normally.

MCP search still evaluates the existing actor voter on candidates before applying its
accessible offset/limit. Only those selected IDs receive scalar draft metadata and the
latest ten release IDs/versions in two queries. Source maps and release manifests are
not hydrated for enrichment. Results, fields, favorites, limits and IDs remain the same;
release timestamp ties now use descending release ID for deterministic ordering.
Candidate scanning remains dependent on offset and visibility, and can hydrate many
candidates for a narrow credential. The change removes per-result enrichment queries;
it does not make all search work constant.

Theme cards request one bounded scalar family summary after pagination. Appearance
HTML includes the currently applied family/variant even with JavaScript disabled.
Opening Appearance requests the existing private family endpoint. Closing/disconnecting
cancels that load; obsolete responses cannot replace a newer selection. Failed loads
keep current choices and can be retried by reopening. A current applied snapshot stays
selectable even if its family is absent from a later list.

The family endpoint already had constant SQL count before this improvement. It now
selects IDs, labels and mode flags rather than hydrating all family entities/proxies.
It also exposes unpaired legacy themes through a read-only single-variant fallback.
No endpoint latency improvement is claimed: this compatibility check adds database
work in the measured fixture. Anonymous responses select only Studio default.

## Deployment and explicit backfill

Deploy the read fallback and explicit writer paths first. Existing unpaired themes
remain visible on gallery/detail/API reads and in the on-demand selector. Their temporary
family identifier is their stable theme ID. Explicit Apply/Save/import/create operations
resolve a real persisted family; returned state contains its real family ID. Read
lookups never attach/flush legacy families. Existing first-install default initialization,
appearance snapshot materialization and recovery of a deleted saved preference retain
their earlier behavior; these are separate from missing-family repair.

After deploying, an operator may explicitly run the following in the application's
Docker CLI, against the intended database and with normal backup/maintenance procedures:

```sh
docker compose exec -T app php bin/console styleguide:themes:backfill-families --limit=100
```

For production use the repository's production Compose files/environment instead.
This command is **not** run by an HTTP read or deployment hook. Repeat until
`remaining=0`. Limits are 1–1000. Each attachment commits independently, selects only
remaining orphans, serializes the find/create check with the existing family advisory
lock, and safely resumes after interruption. Concurrently deleted rows are skipped.
Already attached themes and existing variants are preserved. Explicit managed website
provenance can pair opposite modes; an occupied mode creates a separate legacy family
instead of replacing content. No document, release or preference is rewritten. No new
migration, dependency or configuration is required. All executions for this improvement
used owned disposable resources; no working catalogue backfill was performed.

## Reproduce safely

Use the existing isolated browser harness. Each PHP entry point verifies both database
and blob ownership; there is no default URL/database argument pointing at working data.
The deterministic seeder refuses an existing fixture receipt. If interrupted during
seeding, clean that exact owned run and start a fresh one; do not reseed partial data.

```sh
make browser-start
# Use the exact printed RUN, with its private ownership receipt retained.
python3 tests/ReadPerformance/run.py seed --run-id "$RUN"
python3 tests/ReadPerformance/run.py measure --run-id "$RUN" --label before
# Apply the implementation in this checkout, retaining the same run/data.
python3 tests/ReadPerformance/run.py measure --run-id "$RUN" --label after
python3 tests/ReadPerformance/run.py compare --run-id "$RUN"
make browser-cleanup RUN="$RUN"
```

`measure` rebuilds the app snapshot, clears its APP_DEBUG=0 dev cache and recreates the
app/consumer. It preserves the database and blob volumes. It refuses to overwrite
labelled evidence. `--label sample` supports a single current-source measurement.
Retain this benchmark directory when measuring older application code; only harness
instrumentation differs. Run before unrelated browser tests, which create extra data and sanitize artifact
fingerprints. Compare requires identical runtime/client parameters and intact hashes;
it refuses sanitized evidence.
All timing cases are reads. The separately labelled legacy probe detaches/restores only
its verified single fixture family, refuses preferences/built-ins/paired families and
always restores it in a `finally` block. Never use that fixture reset on working data.

The seed adds 1040 catalogue drafts (1000 permitted by the test token, 40 outside its
scope), 1536 real archive-backed releases across 128 items with twelve versions each,
334 personal catalogue favorites and 128 families/193 themes including one orphan.
Source JSON includes representative large component files. These validated fixtures
exercise reads; they do not claim to exercise publication workers. With the existing
harness seed, the measured dataset is 1126 items/drafts, 2030 releases, 431 favorites,
197 themes and 131 families. The orphan is explicitly paired for the main measurements,
then separately detached for the legacy probe. All main counts match before/after.

## Observed measurements, 2026-10-29

Owned run: `browser-9a0fdb92ce8553730122ee18`, baseline application commit `0f92f2b`.
WSL2 Linux 6.18.54.1, x86_64, Intel Core i5-12500H; Docker reports 10 logical
CPUs, PHP memory limit 256 MiB. Runtime: FrankenPHP
1.13.4, PHP 8.5.12, Symfony 8.1.9, PostgreSQL 17.11. Existing dependency/image locks
are unchanged. Page size is twelve; actor is an enabled editor, with a compose:read PAT
restricted to `read-bench/*`. No cross-actor cache is introduced.

Each case has one excluded warmup and five measured samples, warm PostgreSQL/OS caches,
and a fresh request entity manager. KernelBrowser dev/debug=true includes the complete
Symfony/auth/rendering lifecycle and in-memory DBAL instrumentation. SQL duration is
reported separately; it includes driver round trips. MCP persisted profiling remains
disabled, including credentials. Real HTTP uses the serving dev/debug=false FrankenPHP
app over the local internal Docker network, without SQL instrumentation. Curl retains
connect/TTFB/total timings and decoded response size. These separate series cannot be
subtracted to infer an exact rendering budget. No cold-cache or production-load latency
claim is made.

Times below are milliseconds, median [min–max], before → after. SQL counts are identical across all five samples of each case.

| Case | SQL | Instrumented request | Real HTTP | DB median |
| --- | --- | --- | --- | --- |
| catalogue-name | 25 → 12 | 55.3 [53.9–56.6] → 55.2 [52.4–55.9] | 44.5 [43.7–56.6] → 45.3 [41.2–47.9] | 15.1 → 14.7 |
| catalogue-recent-page2 | 25 → 12 | 53.9 [52.2–57.2] → 53.0 [50.7–54.0] | 43.2 [40.4–51.7] → 41.6 [39.6–42.5] | 15.1 → 13.3 |
| catalogue-favorites | 25 → 12 | 250.3 [241.2–263.5] → 56.2 [54.8–66.1] | 229.7 [226.7–233.7] → 45.0 [44.2–50.0] | 210.5 → 15.3 |
| themes-page | 26 → 13 | 67.1 [63.7–70.5] → 55.7 [54.2–57.6] | 46.0 [42.3–49.0] → 42.6 [41.2–45.2] | 16.3 → 11.9 |
| appearance-editor | 3 → 3 | 24.3 [20.7–25.0] → 23.3 [21.2–25.0] | 19.0 [17.7–19.4] → 22.0 [20.5–22.6] | 3.1 → 4.5 |
| mcp-1 | 6 → 6 | 38.4 [36.8–41.9] → 40.6 [39.3–43.4] | 36.4 [35.9–43.0] → 41.7 [40.4–43.6] | 11.7 → 12.7 |
| mcp-20 | 44 → 6 | 68.3 [66.7–73.8] → 43.2 [42.4–43.9] | 60.3 [59.4–63.0] → 42.8 [41.8–43.7] | 27.7 → 13.7 |
| mcp-50 | 104 → 6 | 117.0 [111.3–120.1] → 50.1 [46.6–54.8] | 98.2 [88.4–106.4] → 47.9 [43.9–49.1] | 52.4 → 17.9 |
| mcp-favorites | 104 → 6 | 126.6 [113.2–143.1] → 44.8 [43.2–47.2] | 88.1 [79.2–95.5] → 44.3 [41.3–49.7] | 57.9 → 13.7 |

Representative instrumented response sizes (minimum bytes across five samples; SSR CSRF/token data varies):

| Case | Before | After |
| --- | ---: | ---: |
| catalogue-name | 74085 | 63108 |
| catalogue-recent-page2 | 73812 | 62761 |
| catalogue-favorites | 77356 | 66294 |
| themes-page | 102972 | 91933 |
| appearance-editor | 14341 | 14341 |
| mcp-1 | 1318 | 1318 |
| mcp-20 | 18303 | 18303 |
| mcp-50 | 45122 | 45122 |
| mcp-favorites | 40819 | 40819 |

All nine normalized result fingerprints match: card text/identity links for HTML and
canonical structured content for JSON/MCP. Shell option reduction is excluded from
card fingerprints and tested separately. Every measured main read records zero SQL
writes. The isolated orphan GET previously inserted one family (130 → 131); after the
change it returns the same light variant with zero writes (130 → 130).

Actual EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON) plans are retained for the observed
favorites count/page SQL, ordinary catalogue IDs, releases and family choices. The
old favorite OR/LEFT JOIN repeatedly scanned an owner's rows for 1040 candidates
(377735 rows removed by its join filter, 6668 shared-buffer hits). Splitting direct
catalogue and typed-theme favorites into independent EXISTS predicates lets PostgreSQL
hash the relevant favorite identities once; the count plan uses 36 buffer hits and
1.408 ms, and the name-page plan uses the existing catalogue_name_unique index,
16 buffer hits and 0.405 ms. These plans are single diagnostic executions, distinct
from the five request samples. The ordinary ID scan is small, bounded sorts remain
in memory, and releases already use their item index. No new index is justified by
this evidence; large-catalogue substring search still warrants separate measurement.

`read-*.json` retains each sample and SQL text without parameter arrays (EXPLAIN
plans can contain fixture identities/search text, never credential queries);
`http-*.json` retains HTTP samples; `legacy-*.json` retains the isolated repair probe;
`comparison.json` retains semantic equality and aggregate ranges. They live under the
owned run's ignored artifacts directory and are sanitized at teardown. Credential,
cookie/session and ownership files remain private and are removed at cleanup.
Regression coverage uses query bounds and real result semantics, never elapsed-time
thresholds. See STATUS.md for actual checks, corrected failures and cleanup evidence.
