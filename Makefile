COMPOSE ?= docker compose
export LOCAL_UID := $(shell id -u)
export LOCAL_GID := $(shell id -g)
.NOTPARALLEL: bootstrap

.PHONY: bootstrap build install assets-install assets up down migrate console test check logs browser
bootstrap: build install assets-install assets migrate up
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

.PHONY: consumer
consumer:
	COMPOSE='$(COMPOSE)' bash tests/Consumer/run.sh
