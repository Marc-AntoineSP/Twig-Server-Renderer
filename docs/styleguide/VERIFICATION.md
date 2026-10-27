# Acceptance evidence

This maps the supplied A01–A38 scenarios to executed tests and operator checks. Detailed
commands, versions, failures and corrections are in agent-kit/STATUS.md. The final clean
checkout and latest production policy run are recorded there when they finish. No remote
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
