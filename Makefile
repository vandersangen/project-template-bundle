COMPOSE_FILE ?= docker-compose-dev.yaml
DOCKER_COMPOSE = docker compose -f $(COMPOSE_FILE)

.PHONY: up build down restart logs ps migrate init help

help:
	@echo "Available commands:"
	@echo ""
	@echo "Docker Compose:"
	@echo "  make up              - Start the containers"
	@echo "  make build           - Rebuild the containers"
	@echo "  make down            - Stop the containers"
	@echo "  make restart         - Restart the containers"
	@echo "  make logs            - View logs"
	@echo "  make ps              - Container status"
	@echo "  make setup           - Full setup (build, start, composer, database, migrations)"
	@echo ""
	@echo "Database & Data:"
	@echo "  make migrate         - Run Symfony database migrations"
	@echo "  make init            - Initialize the project (composer install, etc)"
	@echo ""
	@echo "Testing:"
	@echo "  make test            - Run PHPUnit tests"
	@echo ""
	@echo "Code Quality:"
	@echo "  make lint            - Run GrumPHP (phpcs, phpmd, phpunit, rector, composer audit)"
	@echo "  make phpcs           - Run PHP CodeSniffer (code style check)"
	@echo "  make phpcbf          - Run PHP Code Beautifier (auto-fix code style)"
	@echo "  make phpmd           - Run PHP Mess Detector (code quality check)"
	@echo "  make rector          - Run Rector (code modernization)"
	@echo ""
	@echo "CI/CD (used by GitHub Actions):"
	@echo "  make ci-setup        - Setup CI environment (build, start, composer, databases, migrations)"
	@echo "  make ci-test         - Run tests in CI environment"
	@echo "  make ci-coverage     - Run tests with coverage in CI environment"
	@echo "  make ci-quality      - Run code quality checks in CI environment"
	@echo "  make ci-cleanup      - Cleanup CI environment (stop containers)"


up:
	$(DOCKER_COMPOSE) up -d

up-rebuild: ## Start; --force rebuild
	@$(DOCKER_COMPOSE) up --build --force-recreate --detach

up-db:
	@$(DOCKER_COMPOSE) up db --build --force-recreate --detach

up-mailer:
	@$(DOCKER_COMPOSE) up mailer --build --force-recreate --detach

setup:
	$(DOCKER_COMPOSE) up -d --build --remove-orphans
	$(DOCKER_COMPOSE) exec php composer install
	$(DOCKER_COMPOSE) exec php php bin/console doctrine:database:create --if-not-exists
	$(DOCKER_COMPOSE) exec php php bin/console doctrine:migrations:migrate --no-interaction
	@echo "Setup complete!"

ci-setup:
	$(DOCKER_COMPOSE) up -d --build
	$(DOCKER_COMPOSE) exec -T php composer install --no-interaction
	$(DOCKER_COMPOSE) exec -T php php bin/console lexik:jwt:generate-keypair --skip-if-exists
	$(DOCKER_COMPOSE) exec -T php php bin/console doctrine:database:create --if-not-exists
	$(DOCKER_COMPOSE) exec -T php php bin/console doctrine:migrations:migrate --no-interaction
	$(DOCKER_COMPOSE) exec -T php php bin/console doctrine:database:create --if-not-exists --env=test
	$(DOCKER_COMPOSE) exec -T php php bin/console doctrine:migrations:migrate --no-interaction --env=test
	$(DOCKER_COMPOSE) exec -T php php bin/console bundle:master-data:load --env=test --no-interaction
	@echo "CI setup complete!"

ci-test:
	$(DOCKER_COMPOSE) exec -T php vendor/bin/phpunit --testdox

ci-coverage:
	$(DOCKER_COMPOSE) exec -T php vendor/bin/phpunit --coverage-text --coverage-clover=coverage.xml
	docker cp ptb_php:/var/www/coverage.xml ./coverage.xml || true

ci-quality:
	$(DOCKER_COMPOSE) exec -T php vendor/bin/phpcs --standard=phpcs.xml src/
	@$(DOCKER_COMPOSE) exec -T php bash -c 'php -d error_reporting="E_ALL & ~E_DEPRECATED" vendor/bin/phpmd src text phpmd.xml 2>&1 | grep -E "^/var/www/src/.+\.(php):[0-9]+" || echo "✓ No PHPMD violations found"'
	$(DOCKER_COMPOSE) exec -T php vendor/bin/rector process --dry-run
	$(DOCKER_COMPOSE) exec -T php composer audit --abandoned=ignore

ci-cleanup:
	$(DOCKER_COMPOSE) down -v

down:
	$(DOCKER_COMPOSE) down

restart: down up

logs:
	$(DOCKER_COMPOSE) logs -f

ps:
	$(DOCKER_COMPOSE) ps

migrate:
	$(DOCKER_COMPOSE) exec php php bin/console doctrine:migrations:migrate --no-interaction

init:
	$(DOCKER_COMPOSE) exec php composer install

test:
	$(DOCKER_COMPOSE) exec php ./vendor/bin/phpunit

phpcs:
	$(DOCKER_COMPOSE) exec php ./vendor/bin/phpcs --standard=phpcs.xml src/

phpcbf:
	$(DOCKER_COMPOSE) exec php ./vendor/bin/phpcbf --standard=phpcs.xml src/

phpmd:
	@echo "Running PHPMD with PHP 8.5 compatibility handling..."
	@$(DOCKER_COMPOSE) exec -T php bash -c 'output=$$(php -d error_reporting="E_ALL & ~E_DEPRECATED" ./vendor/bin/phpmd src text phpmd.xml 2>&1); violations=$$(echo "$$output" | grep -E "^/var/www/src/.+\.(php):[0-9]+" || true); if [ -n "$$violations" ]; then echo "$$violations"; exit 2; else echo "✓ No PHPMD violations found (PHP 8.5 parsing errors ignored)"; exit 0; fi'

lint:
	$(DOCKER_COMPOSE) exec php ./vendor/bin/grumphp run

rector:
	$(DOCKER_COMPOSE) exec php ./vendor/bin/rector process

bash:
	$(DOCKER_COMPOSE) exec php bash

cc:
	$(DOCKER_COMPOSE) exec php php bin/console cache:clear

rcc:
	$(DOCKER_COMPOSE) exec php rm -rf var/cache
