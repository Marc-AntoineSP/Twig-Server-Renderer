Implement M03: Live editor and renderer controls.

Read AGENTS.md, agent-kit/STATUS.md and this milestone in
docs/styleguide/MILESTONES.md. Inspect the repository and verify prerequisites.
Read the relevant architecture, contracts, security and acceptance sections.

Work: Implement Twig/JS/props/example tabs, unsaved indicators, 300 ms preview debounce, pending/error/stale states and sequence handling. Add job polling and cancellation/obsolescence handling. Add viewport width, zoom, root text/font controls and reset. Persist source edits separately from transient previews. Syntax highlighting is optional after the full editing flow works.

Required evidence: Type Twig and Stimulus changes and see current previews without reload. Invalid source shows useful location and leaves a visibly stale last-good preview. Slower old jobs cannot replace newer output. Reload restores saved source/preset. At 375/768/1280 widths responsive layout behaves correctly. Tab/keyboard access and iframe reset work.

Complete the implementation and meaningful checks, then update STATUS.md with
exact commands/results and remaining gaps. Do not mark unrun checks passed.
Preserve unrelated work; do not publish/deploy or proceed to the next milestone
unless requested. If something is blocked, continue independent work and report
the concrete blocker and the next useful command.
