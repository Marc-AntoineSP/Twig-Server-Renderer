# Official sources and verification notes

Checked 26 October 2026. These establish framework behaviour; architecture choices in
this kit are proposed project decisions. Resolve exact packages/images again in M00.

| Source | Why it matters |
| --- | --- |
| https://frankenphp.dev/docs/docker/ | Debian Trixie tag format, extensions, Docker/non-root operation |
| https://symfony.com/releases/8.1 | Symfony 8.1 baseline and PHP >= 8.4 requirement |
| https://symfony.com/doc/current/frontend/asset_mapper.html | Studio assets, importmaps and deployment compilation |
| https://symfony.com/bundles/StimulusBundle/current/index.html | Consumer controller discovery and registration |
| https://twig.symfony.com/doc/3.x/sandbox.html | Dedicated sandbox, allowed capabilities and resource/output limitations |
| https://tailwindcss.com/docs/detecting-classes-in-source-files | Literal class scanning and explicit sources |
| https://tailwindcss.com/docs/installation/tailwind-cli | Containerized Tailwind build tool |
| https://daisyui.com/docs/themes/ | CSS theme configuration and semantic variables |
| https://stimulus.hotwired.dev/reference/controllers | Controller identifiers and browser module conventions |
| https://esbuild.github.io/api/ | Browser bundling with a controlled resolver |
| https://developers.openai.com/codex/guides/agents-md | Root AGENTS.md instruction discovery |

Version caveats: image tag existence and platform digests are verified during bootstrap;
this kit contains no invented digest. Symfony 8.1 was documented as maintained when
checked; maintenance horizons change. Use the installed Twig API, especially across the
3.29 sandbox change. Do not assume a documentation page labelled current exactly matches
the versions already installed in a user's repository.
