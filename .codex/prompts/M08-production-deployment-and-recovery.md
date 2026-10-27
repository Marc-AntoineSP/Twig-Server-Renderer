Implement M08: Production deployment and recovery.

Read AGENTS.md, agent-kit/STATUS.md and this milestone in
docs/styleguide/MILESTONES.md. Inspect the repository and verify prerequisites.
Read the relevant architecture, contracts, security and acceptance sections.

Work: Build immutable production app/worker images with locked dependencies, compiled studio assets, least privilege, configured limits, private networks, health checks, secrets and HTTPS/proxy support. Add CI checks, migration/release procedure, cleanup quotas, backup/restore and operational documentation. Finish all acceptance scenarios.

Required evidence: Deploy into a clean Docker environment and exercise create -> edit -> preview -> compose -> theme -> export -> install. Restart services without losing data. Restore DB/blobs into fresh storage and repeat export/install. Confirm no debug/profiler/default credentials/exposed DB or worker ports. Verify resource caps, graceful shutdown and secret redaction.

Complete the implementation and meaningful checks, then update STATUS.md with
exact commands/results and remaining gaps. Do not mark unrun checks passed.
Preserve unrelated work; do not publish/deploy or proceed to the next milestone
unless requested. If something is blocked, continue independent work and report
the concrete blocker and the next useful command.
