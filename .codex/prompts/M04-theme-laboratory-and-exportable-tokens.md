Implement M04: Theme laboratory and exportable tokens.

Read AGENTS.md, agent-kit/STATUS.md and this milestone in
docs/styleguide/MILESTONES.md. Inspect the repository and verify prerequisites.
Read the relevant architecture, contracts, security and acceptance sections.

Work: Implement typed theme persistence and immutable theme releases, all semantic/content colours, geometry, per-theme typography, font registry and renderer presets. Start with system fonts, then bounded licensed WOFF2 assets if supported. Add two-preset comparison, canonical JSON/YAML import/export, generated CSS source and compiled CSS outputs.

Required evidence: Change colours/fonts/text/radius and save/reload them. Two themes can be compared without altering studio styles. Export app.css plus fragment/typography/compiled CSS; reimport theme.json exactly. Generated CSS round-trips; inconsistent edited CSS is reported. No remote font/CDN dependency. Invalid CSS/token injection is rejected.

Complete the implementation and meaningful checks, then update STATUS.md with
exact commands/results and remaining gaps. Do not mark unrun checks passed.
Preserve unrelated work; do not publish/deploy or proceed to the next milestone
unless requested. If something is blocked, continue independent work and report
the concrete blocker and the next useful command.
