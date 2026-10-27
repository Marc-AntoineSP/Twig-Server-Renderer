Implement M07: Symfony consumer installer bundle.

Read AGENTS.md, agent-kit/STATUS.md and this milestone in
docs/styleguide/MILESTONES.md. Inspect the repository and verify prerequisites.
Read the relevant architecture, contracts, security and acceptance sections.

Work: Build packages/catalogue-installer-bundle with the documented registry, require, install, theme, update, remove and doctor commands. Test via a local Composer path repository; do not pretend the package is already on Packagist. Resolve pinned dependencies, validate compatibility, stage/journal writes, integrate CSS/Stimulus, and maintain styleguide.lock.json.

Required evidence: In a fresh compatible Symfony consumer, install the alert and a composed layout by command; Twig renders and Stimulus works. Tailwind includes required classes in production build. Reinstall is idempotent. Modified files and unowned collisions abort without partial writes. Interrupt/recover a staged install. Update/remove respect dependents and ownership; no network calls remain in installed UI.

Complete the implementation and meaningful checks, then update STATUS.md with
exact commands/results and remaining gaps. Do not mark unrun checks passed.
Preserve unrelated work; do not publish/deploy or proceed to the next milestone
unless requested. If something is blocked, continue independent work and report
the concrete blocker and the next useful command.
