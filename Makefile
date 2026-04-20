.PHONY: up down build migrate fixtures jwt front front-build front-watch front-logs front-shell test test-watch test-coverage test-filter cc stan lint workers queue-status failed-messages retry-failed redis-keys redis-flush cache-warm

up:
	docker compose up -d

down:
	docker compose down

build:
	docker compose build --no-cache

migrate:
	docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction

fixtures:
	docker compose exec php php bin/console doctrine:fixtures:load --no-interaction

jwt:
	docker compose exec php php bin/console lexik:jwt:generate-keypair --overwrite

front:
	npm run dev --prefix .

front-install:
	docker compose run --rm node npm install

front-build:
	docker compose run --rm node sh -c "npm install && npm run build"

front-watch:
	docker compose logs -f node

front-logs:
	docker compose logs --tail=50 node

front-shell:
	docker compose run --rm node sh

test:
	APP_ENV=test php bin/phpunit --testdox

test-watch:
	APP_ENV=test php bin/phpunit --testdox --watch

test-coverage:
	APP_ENV=test php bin/phpunit --coverage-html var/coverage

test-filter:
	APP_ENV=test php bin/phpunit --filter=$(filter)

cc:
	docker compose exec php php bin/console cache:clear

stan:
	docker compose exec php vendor/bin/phpstan analyse src --level=6

lint:
	docker compose exec php vendor/bin/php-cs-fixer fix src --dry-run --diff

# ── RabbitMQ / Messenger ───────────────────────────────────────────────────────

workers:
	docker compose up -d worker_ticket worker_notification worker_payment worker_reservation

queue-status:
	docker compose exec rabbitmq rabbitmqctl list_queues name messages consumers

failed-messages:
	docker compose exec php php bin/console messenger:failed:show

retry-failed:
	docker compose exec php php bin/console messenger:failed:retry --all

# ── Redis / Cache ──────────────────────────────────────────────────────────────

redis-keys:
	docker compose exec redis redis-cli KEYS '*'

redis-flush:
	docker compose exec redis redis-cli FLUSHALL

cache-warm:
	docker compose exec php php bin/console app:cache:warm
