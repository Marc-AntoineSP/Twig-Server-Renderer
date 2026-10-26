# Implementation status

Last updated: 2026-10-26. Scope of this implementation: M00 and M01 only.

| Milestone | Status | Evidence |
| --- | --- | --- |
| M00 Bootstrap | Complete | Fresh clone Docker bootstrap, lint, PHPUnit and Chromium all pass |
| M01 Catalogue/authentication | Not started | Starts only after M00 gates |
| M02–M08 | Not started | Existing design/contracts only |

## Repository audit and preservation

Base: `main` at `c5d2497`, an existing Symfony 8.1 webapp skeleton. No application
controllers/entities or working catalogue existed. Retained Doctrine, Forms, Security,
Twig, AssetMapper, Stimulus, Turbo, Messenger and existing optional Mailpit service.
The original Compose ran only PostgreSQL and Mailpit; no app or Node service existed.
User work on arrival: modified AGENTS.md, staged README.md, untracked .codex/, .idea/,
SHA256SUMS, docs/styleguide/ and agent-kit/. Unrelated files are not staged or rewritten.
Only requested STATUS.md and directly used kit resources enter implementation commits.

Stack: `feature/m00-bootstrap` → `feature/m01-authentication` →
`feature/m01-catalogue` (later branches created after their predecessors pass).
Branches are local; no push or deployment is authorized.

## M00 implementation and resolved versions

Docker development stack, non-root UID/GID support, PostgreSQL volume, blob volume,
loopback port 8095, Make workflow, Twig home, database readiness, local AssetMapper
modules, fixed CSS toolchain and Chromium tests. Documentation:
`docs/styleguide/DEVELOPMENT.md`. The pre-existing generated CSS is now ignored;
`make assets` builds it from studio.input.css. AssetMapper excludes the compiler input.

Observed in containers: FrankenPHP **1.13.4**, PHP **8.5.12 ZTS**, Caddy **2.11.7**,
Debian **13.7 Trixie**, Symfony **8.1.9**, PostgreSQL **17.11**, Node **24.22.1**,
Composer **2.10.3**, Doctrine ORM **3.7.5** / DBAL **4.5.0** / bundle **3.3.3**,
Twig **3.30.0**, StimulusBundle **3.6.0**, browser Stimulus **3.2.2**,
PHPUnit **13.4.3**, Tailwind/CLI **4.3.3**, daisyUI **5.7.50**,
esbuild **0.28.2**, Playwright **1.64.1**. Composer and npm locks committed.
`composer.json` now explicitly requires PHP ^8.5 and symfony/uid 8.1.*.
Required extensions verified: ctype, iconv, intl, pdo_pgsql, zip, mbstring, dom, xml,
xmlwriter and OPcache. Symfony 8.1 requires PHP >=8.4; PHP 8.5 meets that requirement.

Registry index digests resolved with `docker buildx imagetools inspect` (not layer IDs):

| Image | Digest |
| --- | --- |
| dunglas/frankenphp:1.13-php8.5-trixie | sha256:ada77754f475352a9105b7961f89ab17ece97048514799532d2d27a1dea2fda7 |
| composer:2 | sha256:b6dcd72ca98fa19d0e5b8476de179859704891fad06dd9e0b96833d4c430a5fc |
| node:24-trixie-slim | sha256:43b9ba1142b8dcf33fa8f922ca1be85714adc444e0586c7cf7726daaeabc1bea |
| postgres:17-trixie | sha256:b8faeff7712663c2773a190181e1246e37e364b887611c04658c28b44e2408fe |
| mcr.microsoft.com/playwright:v1.64.1-noble | sha256:47b510dd9230bf0b9bfc9b20102241e577ea7a99334ab4c3ca19f19770e9b8ea |

Official references checked: [Symfony 8.1](https://symfony.com/releases/8.1),
[FrankenPHP Docker](https://frankenphp.dev/docs/docker/),
[Tailwind CLI](https://tailwindcss.com/docs/installation/tailwind-cli),
[daisyUI installation](https://daisyui.com/docs/install/). Exact installed versions
above come from runtime/lock output, rather than documentation assumptions.

## M00 actual checks

- `docker compose config --quiet`: pass.
- `docker compose build --pull`: app image built successfully.
- `composer require php:^8.5 symfony/uid:8.1.*`: Flex recipe/lock update successful.
- `make migrate up`: pass; both app/database healthy. No domain entities yet, so the
  migration runner correctly reports no registered migrations (allowed at bootstrap).
- `make check`: pass (strict Composer validation; container; 24 YAML; 2 Twig files).
- `make test`: **2 tests, 13 assertions**, pass against PostgreSQL test database.
- `docker compose exec -T app composer audit --locked`: no advisories.
- `docker compose run --rm node npm run build:css`: real Tailwind/daisyUI compilation passes.
  npm install audit: zero vulnerabilities.
- `curl --fail http://localhost:8095/`: 200 Twig page. `/health/ready`: 200 ready.
- Stopped this stack's database: `/health/ready` returned **503 unavailable** while
  `/health` remained **200 ok**; restarted database/app to healthy.
- `python3 agent-kit/tools/check_kit.py`: pass (kit fixture/static verification only).
- `make browser`: **1 Chromium test passed**, actual CSS + Stimulus interaction, no page errors.
- Fresh clone `/tmp/styleguide-m00-clean` (no vendor/node_modules/generated CSS):
  `STUDIO_PORT=8097 make COMPOSE='docker compose -p styleguide-m00-clean' bootstrap check test browser`:
  **exit 0**, image build + dependency installs + compile + migration runner + readiness +
  lint + **2 PHPUnit tests/13 assertions** + **1 Chromium test** passed. Separate fresh
  volumes; `curl --fail http://localhost:8097/health/ready` returned ready.
- First fresh clone found Docker created a root-owned bind-mount parent for the nested
  blob volume. Make now creates `var/catalogue` before starting Docker. The second
  fresh clone above verifies the fix with no pre-existing runtime directories.

Failures corrected during M00: original Composer lacked name/description for strict
validation; added metadata and regenerated lock. PHPUnit inherited APP_ENV=dev from
Compose; Make now explicitly runs it with APP_ENV=test. AssetMapper tried compiling
Tailwind source; excluded studio.input.css. Chromium enforces HSTS on the `app` DNS
name; uses the `studio` network alias. A custom browser build hit a Debian HTTP mirror
hash mismatch, then HTTPS trust failure; replaced with the official pinned Playwright
image. An early browser assertion expected inline-flex on a flex item (computed display
is blockified); corrected the assertion to verify actual component styling.

M00 gates completed and recorded before starting M01. Next step: M01 authentication.
Production images, isolated preview execution, release publishing, registry and consumer
installation remain M02–M08. No claim that those boundaries have been tested yet.
