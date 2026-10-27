Implement M06: Imports, exports and registry.

Read AGENTS.md, agent-kit/STATUS.md and this milestone in
docs/styleguide/MILESTONES.md. Inspect the repository and verify prerequisites.
Read the relevant architecture, contracts, security and acceptance sections.

Work: Implement immutable releases, source/resolved collection/static/theme exports, archive and per-file checksums, scoped read tokens and registry endpoints. Implement local ZIP/manifest/YAML/theme imports with validate/dry-run and transactional apply. Produce clear export instructions, licences and complete dependency closures. Use the schema and path rules consistently.

Required evidence: Export a layout with dependencies and theme, import into a clean studio and compare normalized data and rendering. A static download runs with no studio requests. Test traversal/duplicate entries/symlinks/ZIP bombs/missing files/checksum mismatch and auth. Publication fails when validation/build fails. Source and compiled CSS are labelled correctly.

Complete the implementation and meaningful checks, then update STATUS.md with
exact commands/results and remaining gaps. Do not mark unrun checks passed.
Preserve unrelated work; do not publish/deploy or proceed to the next milestone
unless requested. If something is blocked, continue independent work and report
the concrete blocker and the next useful command.
