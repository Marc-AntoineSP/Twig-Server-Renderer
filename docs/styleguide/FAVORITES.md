# Personal favorites

Use the star beside a theme, component, block or layout on its listing or detail
page. The same button appears in source/theme editors and beside the composition
builder title. An outlined star adds a favorite; a filled star removes it. These
are personal database records, available in subsequent sessions and other devices.
They never apply a theme, change a draft, publish a release or change permissions.

Choose **Favorites only**, then **Find items** or **Find themes**. Clear favorite
filter keeps the other search controls. Catalogue search includes its existing
name/title/description/tag search, kind and sort. Theme search includes names,
reference titles/surfaces, family labels, mode and sort. Both filter in SQL before
12-item pagination. URLs use `favorites=1` alongside `q`, `kind`/`mode`, `sort`,
and `page`. Opening a detail page carries an application-relative `returnTo` for
its back link; other origins and paths are rejected. Stars show their current
state even when Favorites only is off. After removing a favorite, a filtered card
stays visible with its updated star until the list is searched or refreshed.

In the composition builder, Favorites only narrows both the insertion picker and
the selected node's immutable-reference picker. An existing nonfavorite reference
remains visible as “current, outside favorites”; filtering cannot change the tree.
Unpublished drafts stay unavailable. Choices include favorite releases beyond the
existing 200-recent-release window and exclude the composition itself. Commands
continue through CompositionWorkspace's existing permission, schema, dependency,
cycle and parent validation. Changing favorites never marks the tree as dirty.

`compose_search_catalogue` accepts the optional boolean `favoritesOnly`; every
result includes `favorite`. Its owner comes from the authenticated PAT/OAuth
identity. Namespace/scope restrictions still apply before accessible pagination.
There are no favorite mutation tools or additional credential scopes.

## Persistence and security

`personal_favorite` has a stable ULID, owner FK and exactly one target FK:
`theme_id` or `catalogue_item_id`. The database CHECK enforces that choice; unique
indexes enforce one favorite per owner/target, including concurrent duplicate adds.
Foreign keys cascade target/account deletion. Only this new table is migrated.
Theme variants use Theme IDs, not family/revision/release IDs. Published catalogue
theme packages resolve to the same typed variant instead of creating a second
favorite identity. The light/dark label remains visible.

Browser endpoints are `GET`, `PUT` (add), and `DELETE` (remove) at
`/api/v1/favorites/{theme|catalogue}/{id}`. Mutations require the existing `api`
CSRF header. PUT/DELETE are explicit and idempotent; the server never blindly
toggles. They resolve the user from authentication, return authoritative state,
and use private/no-store responses. The shared studio catalogue remains available
to enabled readers; editor permissions are unnecessary for saving personal choices.
The existing private application redirects anonymous page visits to sign-in and
returns 401 for anonymous API requests. There is no anonymous catalogue browsing.

Favorite status uses one owner-scoped joined query per request, cached only on the
current request. Deleted/unsupported targets do not enter status maps or counts.
MCP additionally uses its existing scoped voter for every returned item. No other
user's ID or preference is accepted as browser/tool input. Buttons disable while
pending, synchronize duplicate buttons, and reconcile interrupted writes by reading
the actual server state. Errors remain visible; retrying an explicit operation is
safe. Studio Twig, inline SVG icons, AssetMapper Stimulus, theme styles, existing
Apply/edit/download actions, and the opaque renderer sandbox are reused.

## Migration and checks

Development: `make migrate` (already applied during implementation).
Other existing production installations, after their normal authorized code update:

```sh
docker compose --env-file var/production/compose.env -f compose.production.yaml exec -T app php bin/console doctrine:migrations:migrate --no-interaction
```

Migration: `Version20261028072749`. Existing production on port 8096 is not deployed
by this task. `make favorites-production` builds a separate disposable production
project and runs favorites plus existing authentication/manual composer/MCP flows.
It verifies new post-build data against read-only AssetMapper assets.

Acceptance commands: `make check`, `make test`, `make browser`,
`make favorites-production`, `doctrine:schema:validate`, and `git diff --check`.
Functional coverage includes persistence, independent users, reader/anonymous/CSRF
access, repeated add/remove, catalogue/theme revisions, deleted/disabled targets,
theme mode, search/tags/kind/sort, pagination, return links and MCP scope isolation.
Browser coverage includes real cross-session login, keyboard star activation,
pending/lost responses, all three reference kinds, unpublished exclusion, existing
nonfavorite references, mobile/desktop, light/dark, Apply independence and sandbox.
Measured results and evidence are recorded in STATUS.md and VERIFICATION.md.

## Changed files

- Persistence/API: `src/Entity/Favorite.php`, `src/Favorites/Favorites.php`,
  `src/Controller/FavoriteController.php`, `src/Twig/FavoriteExtension.php`,
  `migrations/Version20261028072749.php`.
- Listing queries: `src/Catalogue/SearchQuery.php`,
  `src/Repository/CatalogueRepository.php`, `src/Controller/CatalogueController.php`,
  `src/Theme/ThemeSearchQuery.php`, `src/Theme/ThemeCatalogue.php`,
  `src/Controller/ThemeController.php`.
- Browser UI: `assets/controllers/favorite_controller.js`,
  `assets/controllers/composition_controller.js`, all five partials in
  `templates/favorites/`, catalogue `index/show/edit.html.twig`, theme
  `index/edit/_detail_sidebar.html.twig`, `templates/composition/edit.html.twig`,
  `src/Controller/CompositionController.php`.
- MCP: `src/Mcp/ComposeApplication.php`, `src/Mcp/Tool/ComposeSchemas.php`,
  `src/Mcp/Tool/ComposeTools.php`, `tools/mcp/generate-tool-schemas.py`.
- Tests/docs: `tests/Functional/FavoriteTest.php`,
  `tests/Functional/ComposeMcpTest.php`, `tests/Browser/favorites.spec.js`,
  `tests/Browser/theme_components.spec.js`,
  `tests/AppearanceProduction/run.sh`, `Makefile`, this document,
  `docs/styleguide/MILESTONES.md`, `docs/styleguide/VERIFICATION.md`,
  `docs/styleguide/ARCHITECTURE.md`, `agent-kit/STATUS.md`.

No dependency upgrades, uploaded source changes or imported font/theme rewrites.
