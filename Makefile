COMPOSE ?= docker compose
export LOCAL_UID := $(shell id -u)
export LOCAL_GID := $(shell id -g)
.NOTPARALLEL: bootstrap

.PHONY: bootstrap build install assets-install assets up down migrate console test check logs browser
bootstrap: build install assets-install assets mcp-init migrate up
build:
	mkdir -p var/catalogue
	$(COMPOSE) build --pull
install:
	mkdir -p var/catalogue
	$(COMPOSE) run --rm app composer install --no-interaction
assets-install:
	$(COMPOSE) run --rm node npm ci
assets:
	$(COMPOSE) run --rm node npm run build:css
up:
	$(COMPOSE) up -d --wait app database render-worker build-worker preview-consumer
down:
	$(COMPOSE) down
migrate:
	$(COMPOSE) run --rm app php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
mcp-init:
	$(COMPOSE) run --rm --no-deps app php tools/mcp/init.php
mcp-smoke:
	$(COMPOSE) exec -T app php bin/console styleguide:mcp:smoke
console:
	$(COMPOSE) exec app php bin/console $(ARGS)
test:
	$(COMPOSE) exec app php bin/console doctrine:database:create --env=test --if-not-exists
	$(COMPOSE) exec app php bin/console doctrine:migrations:migrate --env=test --no-interaction --allow-no-migration
	$(COMPOSE) exec -e APP_ENV=test app php bin/phpunit
check:
	$(COMPOSE) config --quiet
	$(COMPOSE) exec app composer validate --strict
	$(COMPOSE) exec app php bin/console lint:container
	$(COMPOSE) exec app php bin/console lint:yaml config
	$(COMPOSE) exec app php bin/console lint:twig templates
browser:
	$(COMPOSE) exec -T app php bin/console styleguide:test:accounts
	@status=0; $(COMPOSE) run --rm browser npm run test:browser || status=$$?; $(COMPOSE) exec -T app php bin/console styleguide:test:accounts --cleanup; exit $$status
logs:
	$(COMPOSE) logs --tail=100 -f app

.PHONY: library-deps fonts-download fonts-sync fonts-dry-run themes-research themes-candidates themes-collect themes-dry-run
FONT_ARGS ?=
RESEARCH_ARGS ?=
library-deps:
	$(COMPOSE) run --rm --no-deps node npm ci --prefix tools/library --ignore-scripts --no-audit --no-fund
fonts-download: library-deps
	$(COMPOSE) run --rm --no-deps node node tools/library/fonts-download.mjs $(FONT_ARGS)
fonts-sync: library-deps
	COMPOSE='$(COMPOSE)' bash tools/library/sync.sh fonts $(FONT_ARGS)
fonts-dry-run:
	$(COMPOSE) run --rm --no-deps node node tools/library/fonts-download.mjs --dry-run $(FONT_ARGS)
	$(COMPOSE) exec -T app php bin/console styleguide:fonts:sync --dry-run $(FONT_ARGS)
themes-research: library-deps
	$(COMPOSE) run --rm --no-deps research-browser node tools/library/themes-collect.mjs $(RESEARCH_ARGS)
themes-candidates:
	$(COMPOSE) run --rm --no-deps node node tools/library/themes-candidates.mjs
themes-collect: library-deps
	COMPOSE='$(COMPOSE)' bash tools/library/sync.sh themes
themes-dry-run:
	$(COMPOSE) exec -T app php bin/console styleguide:themes:collect --dry-run

.PHONY: font-consumer
font-consumer:
	COMPOSE='$(COMPOSE)' bash tests/FontConsumer/run.sh

.PHONY: appearance-production
appearance-production:
	bash tests/AppearanceProduction/run.sh

.PHONY: theme-showcase-production
theme-showcase-production:
	bash tests/AppearanceProduction/run.sh tests/Browser/appearance.spec.js tests/Browser/theme_components.spec.js

.PHONY: preview-navigation-production
preview-navigation-production:
	bash tests/AppearanceProduction/run.sh tests/Browser/preview.spec.js tests/Browser/theme_components.spec.js tests/Browser/authentication.spec.js

.PHONY: consumer
consumer:
	COMPOSE='$(COMPOSE)' bash tests/Consumer/run.sh

PRODUCTION ?= docker compose --env-file var/production/compose.env -f compose.production.yaml
.PHONY: prod-init prod-build prod-up prod-migrate prod-check prod-down
prod-init:
	docker run --rm --user $(LOCAL_UID):$(LOCAL_GID) --workdir /workspace --volume '$(CURDIR):/workspace' --entrypoint php dunglas/frankenphp:1.13-php8.5-trixie@sha256:ada77754f475352a9105b7961f89ab17ece97048514799532d2d27a1dea2fda7 tools/production/init.php
prod-build:
	$(PRODUCTION) config --quiet
	$(PRODUCTION) build --pull
prod-migrate:
	$(PRODUCTION) up -d --wait database render-worker build-worker
	$(PRODUCTION) run --rm --no-deps app php tools/mcp/init.php
	$(PRODUCTION) run --rm --no-deps app php bin/console doctrine:migrations:migrate --no-interaction
prod-up:
	$(PRODUCTION) up -d --wait
prod-check:
	$(PRODUCTION) exec -T app php bin/console doctrine:schema:validate
	$(PRODUCTION) exec -T app php bin/console lint:container --env=prod --no-debug
	$(PRODUCTION) exec -T app php bin/console styleguide:cleanup
	$(PRODUCTION) exec -T app php bin/console styleguide:storage:verify
prod-down:
	$(PRODUCTION) -f compose.local-tls.yaml down

.PHONY: prod-test
prod-test:
	PRODUCTION='$(PRODUCTION)' bash tests/Production/run.sh

.PHONY: mcp-init mcp-smoke compose-mcp-production
compose-mcp-production:
	bash tests/AppearanceProduction/run.sh tests/Browser/composition.spec.js tests/Browser/compose_mcp.spec.js tests/Browser/authentication.spec.js

.PHONY: profile-production
profile-production:
	bash tests/AppearanceProduction/run.sh tests/Browser/profile.spec.js tests/Browser/authentication.spec.js tests/Browser/composition.spec.js tests/Browser/compose_mcp.spec.js
