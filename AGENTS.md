# Styleguide Studio — repository instructions

## Mission and constraints

Implement docs/styleguide/PRODUCT.md as a Docker-hosted Symfony application.
FrankenPHP 1.13 on Debian Trixie is mandatory. Use PHP 8.5, Symfony 8,
Twig SSR, Tailwind 4, daisyUI 5, and Stimulus. Use AssetMapper for studio JS;
Node in Docker compiles CSS and preview JS. No React, Vue, SPA, PHP-code uploads,
arbitrary npm plugin installation, or server-side execution of imported JavaScript.

## Before changes

Read agent-kit/STATUS.md and the current milestone in docs/styleguide/MILESTONES.md.
Read ARCHITECTURE.md plus the relevant CONTRACTS.md/SECURITY.md sections.
Inspect the existing repository and its instructions. Preserve unrelated edits.
An existing app is not an empty folder: adapt services, routes and assets in place.
When dependency versions are unresolved, check official docs and the registry,
record real results, and commit lockfiles. Do not invent image digests.

## Implementation rules

- Implement one independently verifiable milestone at a time; finish its gates.
- Keep the application a modular monolith. Use focused services, DTOs and Doctrine
  repositories; avoid generic event-sourcing, a plugin framework or excessive layers.
- Use PHP attributes and current supported Symfony APIs; run framework commands in Docker.
- Twig content belongs to a dedicated sandboxed renderer, never the studio Twig service.
- User templates receive only bounded arrays/scalars. No app, request, user, entity,
  service container, filesystem loader, arbitrary include path, or Twig PHP extension.
- Stimulus preview code runs only in the opaque-origin sandboxed iframe.
- Rendering and compilation have process limits; workers receive no studio secrets.
- All variant classes are complete literal class names; materialize dynamic sources
  for Tailwind. Editor success must mean the emitted CSS contains the edited classes.
- Package paths and dependencies follow CONTRACTS.md and schemas; releases are immutable.
- Never execute shell commands, Composer scripts, npm scripts or plugins from imports.
- Imports, exports and installs use the same validation rules and locked dependency graph.
- Do not overwrite modified consumer files. Use staging, hashes, lockfile and rollback.
- Persist design tokens as typed data; generate CSS from those tokens.
- No fake controls, hardcoded success responses, simulated persistence or mocked
  integration in a milestone declared complete.

## Verification and handoff

Add tests for contracts, source isolation, dependency resolution and actual user flows.
Do not add tests that merely duplicate trivial getters or implementation structure.
Run the milestone's checks; report commands, results and any unavailable environments.
Never claim Docker or browser checks passed if they were not run.
Update agent-kit/STATUS.md after work: completed gates, changed files, actual versions,
decisions, remaining risks, failed checks and the exact next step.
Do not deploy, publish packages, or push branches unless the user asked for that action.
The numbered prompt files are manually selected task prompts, not automatically
registered Codex slash commands. Do not invent Codex configuration keys.
