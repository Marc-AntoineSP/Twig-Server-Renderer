Implement M00: Bootstrap and repository audit.

Read AGENTS.md, agent-kit/STATUS.md and this milestone in
docs/styleguide/MILESTONES.md. Inspect the repository and verify prerequisites.
Read the relevant architecture, contracts, security and acceptance sections.

Work: Inventory the existing Symfony app, instructions, Docker, frontend and dirty worktree. Preserve working features. Resolve Symfony 8/PHP 8.5 compatibility and the FrankenPHP 1.13 Trixie tag. Adapt reference Docker/Compose/Caddy/Makefile; create a Symfony skeleton only if absent. Add PostgreSQL, AssetMapper, StimulusBundle, Twig, Doctrine, security, validation, console, UID and test dependencies. Add fixed Node tooling, locks and a first studio stylesheet. Build a home page and a health endpoint.

Required evidence: A fresh clone boots using documented Docker commands. Verify actual PHP/FrankenPHP versions, required extensions, DB connection, migrations, Twig page, Tailwind/daisyUI styling and one Stimulus action. Run docker compose config, build, readiness and HTTP smoke checks. Record image digests and lockfiles. No local PHP/Node prerequisite.

Complete the implementation and meaningful checks, then update STATUS.md with
exact commands/results and remaining gaps. Do not mark unrun checks passed.
Preserve unrelated work; do not publish/deploy or proceed to the next milestone
unless requested. If something is blocked, continue independent work and report
the concrete blocker and the next useful command.
