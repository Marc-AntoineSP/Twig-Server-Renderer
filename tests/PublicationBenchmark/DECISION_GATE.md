# Publication benchmark decision gate

Establish the baseline before changing publication orchestration. Use only an exact
BrowserHarness database/blob ownership receipt. Profiles are ordinary components with
real portable Stimulus source, one or twenty examples, and a larger two-file source
closure below the existing 1 MiB/file and 2 MiB/closure limits. Defaults add one more
render/build pair. First publication is recorded as a warmup, followed by five measured
immutable versions per profile. Workers receive no injected delays or fake success.

The repository's existing interactive release acceptance waits up to 20 seconds for
publication acknowledgement, and its browser navigation timeout is also 20 seconds.
Use that actual interactive budget as the decision threshold, not a universal latency
promise. Investigate durable orchestration if supported representative workloads
repeatedly exceed it or hit request-chain deadlines, or if disconnect/retry/failure
probes expose an outcome that cannot be safely reconciled with immutable version lookup.
Report ranges and remaining headroom. Worker limits are separate per-call limits;
they must not be mistaken for an aggregate publication deadline.

If supported profiles fit the interactive budget and failure/disconnect outcomes can
be reconciled without duplicate or partial releases, retain synchronous publication,
document its limits and improve only genuinely unclear status/recovery guidance.
A browser disconnect alone does not prove that a queue is needed: verify whether a
complete immutable version committed, how an exact retry behaves, and how the client
can check. No durable completion/recovery guarantee may be claimed for a synchronous
request. Revisit the decision for lower proxy limits, slower supported datasets,
contention, a larger example cap or a requirement to resume across web-process loss.

No measurement here promises cancellation, durable jobs or automatic retries. If the
evidence triggers the job path, reuse the existing Doctrine Messenger transport,
app-side consumer and isolated renderer/compiler; capture immutable request inputs,
deduplicate operations and protect terminal publication with leases/atomic artifacts.
