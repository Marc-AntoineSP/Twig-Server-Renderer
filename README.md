# Styleguide Studio — Codex implementation kit

Prepared 26 October 2026. Proposed project name: Styleguide Studio.

Build a self-hosted Symfony component catalogue, source editor, composition builder,
and theme laboratory. The deliverables are portable Twig templates, Tailwind/daisyUI
CSS, Stimulus controllers, and versioned packages installable in another Symfony app.

**This archive is an implementation kit, not the completed server.** It contains
project instructions, contracts, examples, development infrastructure references,
milestones, acceptance tests, and executable static checks. It does not contain a
bootstrapped Symfony application, a working registry, or a published Composer package.
The implementation commands documented below are contracts for Codex to implement.

## Start with Codex

1. Extract this archive into a separate directory and inspect it.
2. In your Symfony project, merge AGENTS.md with any existing instructions. Copy
   docs/styleguide/, .codex/prompts/, and agent-kit/ into the repository. Do not
   replace existing Docker, Composer, asset, or security files blindly.
3. Open Codex at the project root. Paste the prompt below.
4. Start with M00 and M01. After their checks pass, continue milestone by milestone
   using the numbered prompts in .codex/prompts/.

    Read AGENTS.md, docs/styleguide/PRODUCT.md, docs/styleguide/ARCHITECTURE.md,
    docs/styleguide/MILESTONES.md, and agent-kit/STATUS.md.
    Inspect this repository before editing. Preserve unrelated work.
    Implement M00 and M01 completely, including their automated checks and Docker
    verification. Use agent-kit/reference as a reference, not a blind overlay.
    Record actual dependency versions, checks, blockers, and the next milestone in
    agent-kit/STATUS.md. Do not claim the rest of the product is implemented.

## What you will build

| Area | Result |
| --- | --- |
| Catalogue | Searchable components, blocks, layouts, tags, versions and examples |
| Editor | Twig and Stimulus source, props forms, errors and debounced live preview |
| Renderer | Desktop/tablet/mobile widths, themes, fonts, text sizes, light/dark presets |
| Theme lab | Editable semantic colours, typography, radius and size tokens |
| Composition | Ordered containers and references; blocks nested inside larger layouts |
| Portability | Source ZIP, static HTML preview ZIP, themes and resolved dependencies |
| Installer | Symfony console commands with checksums, lockfile and conflict handling |
| Deployment | Docker development and production, persistent data, backup/restore |

## Technical baseline

- Symfony 8.1 for a new project; PHP 8.5. Audit an existing Symfony 8 project first.
- FrankenPHP 1.13, Debian Trixie; intended development tag:
  dunglas/frankenphp:1.13-php8.5-trixie. Verify availability and record a digest in M00.
- Twig SSR; Tailwind CSS 4; daisyUI 5; Stimulus. No React/Vue/SPA runtime.
- AssetMapper + StimulusBundle for the studio and receiving Symfony application.
- A pinned Node 24 toolchain inside Docker for Tailwind and esbuild. Dynamic editing
  means the server needs a build service after deployment, not only at image build time.
- PostgreSQL for catalogue state; a persistent blob volume for immutable files.
- One private studio with authenticated editors initially. No public arbitrary-code
  authoring, marketplace, tenant billing, or collaborative cursors in version one.

These are project decisions, not claims that every exact version combination has
been executed here. M00 must resolve, lock and verify dependencies. See SOURCES.md.

## How installation will feel after M07

The proposed Composer package is not published yet. M07 creates it under
packages/catalogue-installer-bundle/ and tests it through a Composer path repository.

    # Future public package, after it has actually been published:
    composer require awu/catalogue-installer-bundle

    php bin/console styleguide:registry:add studio https://studio.example.test
    php bin/console styleguide:require awui/alert@1.0.0 --registry=studio --dry-run
    php bin/console styleguide:require awui/alert@1.0.0 --registry=studio
    php bin/console styleguide:theme:install awui/ember@1.0.0 --registry=studio

The installer copies owned source files into the consumer, checks required frontend
dependencies, registers assets through the existing conventions, and records hashes
in styleguide.lock.json. Components remain editable in the receiving project.
It must not quietly replace existing templates, app.css, controllers, or lockfiles.

## Files to read

- AGENTS.md: concise repository rules automatically discoverable by Codex.
- docs/styleguide/PRODUCT.md: scope, screens and user journeys.
- docs/styleguide/ARCHITECTURE.md: boundaries, data ownership and preview pipeline.
- docs/styleguide/CONTRACTS.md: package, API, theme and installer semantics.
- docs/styleguide/SECURITY.md: editable Twig, browser JavaScript and import boundaries.
- docs/styleguide/OPERATIONS.md: deployment, build services, secrets and recovery.
- docs/styleguide/MILESTONES.md: ordered implementation work and gates.
- docs/styleguide/ACCEPTANCE.md: end-to-end scenarios and adversarial checks.
- docs/styleguide/SOURCES.md: official sources and version verification notes.
- agent-kit/schemas/: machine-readable JSON Schema contracts.
- agent-kit/examples/: a dismissible alert, composed block and theme.
- agent-kit/reference/: development Docker, Makefile and build-entry references.
- agent-kit/tools/check_kit.py: integrity and semantic fixture checks.
- .codex/prompts/: kickoff, milestone, continuation and review prompts.

Run the kit's checks with Python 3.10+ (no third-party Python dependency):

    python3 agent-kit/tools/check_kit.py

This check is not a Symfony, Twig, Docker or browser test. Those run during the
implementation milestones. Never mark an unchecked milestone complete.
