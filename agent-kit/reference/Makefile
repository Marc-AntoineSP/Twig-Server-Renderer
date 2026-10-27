COMPOSE ?= docker compose

.PHONY: build up down install assets-install assets migrate test console logs
build:
	$(COMPOSE) build --pull
up:
	$(COMPOSE) up -d --wait app db
down:
	$(COMPOSE) down
install:
	$(COMPOSE) run --rm app composer install
assets-install:
	$(COMPOSE) run --rm node npm ci
assets:
	$(COMPOSE) run --rm node npm run build:css
migrate:
	$(COMPOSE) exec app php bin/console doctrine:migrations:migrate --no-interaction
test:
	$(COMPOSE) exec app php bin/phpunit
console:
	$(COMPOSE) exec app php bin/console $(ARGS)
logs:
	$(COMPOSE) logs --tail=100 -f app
