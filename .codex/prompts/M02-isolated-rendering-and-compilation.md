Implement M02: Isolated rendering and compilation.

Read AGENTS.md, agent-kit/STATUS.md and this milestone in
docs/styleguide/MILESTONES.md. Inspect the repository and verify prerequisites.
Read the relevant architecture, contracts, security and acceptance sections.

Work: Implement finite template loading, dependency graph validation, dedicated Twig sandbox in a bounded subprocess, and a separate fixed Node compiler. Add worker containers with internal-only endpoints, no app/database secrets and explicit caps. Build a preview document with embedded assets and sandboxed srcdoc. Use the included alert as the initial end-to-end fixture.

Required evidence: Alert renders and dismisses inside the iframe. A new literal responsive utility appears after editing. Unknown/app template includes, PHP call paths, loops exceeding budgets and forbidden compiler imports fail cleanly. Browser tests prove no access to parent DOM/storage and validate message source/channel checks. Workers survive failed jobs.

Complete the implementation and meaningful checks, then update STATUS.md with
exact commands/results and remaining gaps. Do not mark unrun checks passed.
Preserve unrelated work; do not publish/deploy or proceed to the next milestone
unless requested. If something is blocked, continue independent work and report
the concrete blocker and the next useful command.
