Implement M01: Catalogue and authentication.

Read AGENTS.md, agent-kit/STATUS.md and this milestone in
docs/styleguide/MILESTONES.md. Inspect the repository and verify prerequisites.
Read the relevant architecture, contracts, security and acceptance sections.

Work: Implement private login/roles and interactive admin creation. Add CatalogueItem, Draft and Release metadata with migrations and source storage. Add searchable/paginated catalogue, detail/create/edit forms, draft save with optimistic revision checking, and example fixtures. Implement structural plus semantic manifest/props validation. Publishing may remain disabled until render/build validation exists.

Required evidence: Create, edit, reload and find a component after container restart. Unauthorized edits fail; CSRF is enforced. Conflicting saves return 412 and preserve data. Validate duplicate names, unsafe paths, invalid props/defaults and immutable version uniqueness. PHPUnit covers actual persistence and permissions.

Complete the implementation and meaningful checks, then update STATUS.md with
exact commands/results and remaining gaps. Do not mark unrun checks passed.
Preserve unrelated work; do not publish/deploy or proceed to the next milestone
unless requested. If something is blocked, continue independent work and report
the concrete blocker and the next useful command.
