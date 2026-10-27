# Acceptance scenarios

These are implementation tests, not assertions that the kit already passes them.
Use PHPUnit for server logic/integration and Playwright for browser isolation and UI.
Use a real clean Symfony consumer fixture for installation; mocking file writes is
insufficient. Verify production assets as well as development assets.

| ID | Scenario | Required result |
| --- | --- | --- |
| A01 | Fresh Docker setup | Documented commands boot app/DB; versions and health verified |
| A02 | Save/restart | Source, tags, theme and props survive app/DB restart |
| A03 | Concurrent edit | Second stale save fails 412 without overwriting first |
| A04 | Edit literal class | A newly typed md:grid-cols-3 changes the preview at the breakpoint |
| A05 | Dynamic variant | Complete variant class map compiles all supported states |
| A06 | Stimulus | Alert dismisses, accordion opens/closes, progress updates; cleanup works |
| A07 | Stale result race | A slow older job cannot replace a newer preview |
| A08 | Twig diagnostics | Invalid Twig reports useful package/line and no production stack trace |
| A09 | Props | Missing required/invalid enum/oversized JSON is rejected clearly |
| A10 | Template boundary | app/request/services/filesystem/application includes are inaccessible |
| A11 | Render resource limit | Recursive/huge template is killed and next job still succeeds |
| A12 | Browser boundary | Imported JS cannot access parent document/session/localStorage |
| A13 | Message boundary | Wrong source/channel/oversized messages are ignored |
| A14 | CSS boundary | Theme and classes do not restyle the studio UI |
| A15 | Compiler boundary | Imported plugins/configs/remote JS/escaping paths are rejected |
| A16 | Palette | All semantic/content tokens and geometry persist and generate valid CSS |
| A17 | Typography | Preset fonts/default size work; explicit component sizes stay explicit |
| A18 | Theme round-trip | Canonical JSON export/import produces equivalent tokens/fonts |
| A19 | CSS round-trip | Own generated CSS imports; manually conflicting CSS reports mismatch |
| A20 | Compose | Components -> block -> layout keeps order, props and dependencies |
| A21 | Graph validation | Cycles, conflicting versions and limits fail before publish |
| A22 | Twig literal injection | Composition text '{{ dangerous }}' is displayed literally |
| A23 | Source export | Package contains all declared files, hashes, licences and exact deps |
| A24 | Static export | HTML/CSS/JS run without contacting the catalogue server |
| A25 | Malicious archive | Traversal, drive path, symlink, duplicate/case collision and ZIP bomb fail |
| A26 | Release integrity | Changed bytes/hash mismatch fail; same release cannot be republished |
| A27 | Consumer integration | Installed Twig + Stimulus work after production asset compilation |
| A28 | Install idempotence | Same package produces no duplicate imports or changed unrelated files |
| A29 | Install conflict | Local modification/unowned file aborts with complete rollback |
| A30 | Install crash | Journal recovery restores a consistent preinstall or committed state |
| A31 | Update/remove | Dependents protected; local edits preserved; owned hashes updated |
| A32 | Registry auth | Expired/revoked/wrong-scope tokens fail; no token in logs/ZIP/lockfile |
| A33 | Access control | Reader cannot edit; anonymous cannot fetch drafts/jobs/private releases |
| A34 | Accessibility | Focus/keyboard/reduced-motion behaviours tested on sample components |
| A35 | Backup restore | Fresh DB/blob restore reproduces saved content and installation |
| A36 | Production boundaries | No public DB/workers/debug; persistent data and limits verified |
| A37 | Document assembly | Closing script/style sequences and Unicode cannot escape wrapper |
| A38 | Cleanup | Expired jobs/temp files removed; referenced releases never removed |

Initial showcase: button variants, dismissible alert, native-details accordion,
progress bar with Stimulus demo values, card, tabs, navigation, stats, form fields,
modal/dialog, pricing block, dashboard layout. Build the first three deeply before
adding the rest. Behaviour and source portability matter more than a large seed count.
