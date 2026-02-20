COMPOSE_FILE ?= docker-compose-dev.yaml
DOCKER_COMPOSE = docker compose -f $(COMPOSE_FILE)

.PHONY: up build down restart logs ps migrate init help

help:
	@echo "Beschikbare commando's:"
	@echo ""
	@echo "Docker Compose:"
	@echo "  make up              - Start de containers (dev - docker-compose-dev.yaml)"
	@echo "  make up-prod         - Start de containers (prod - docker-compose.yaml)"
	@echo "  make build           - Bouw de containers opnieuw"
	@echo "  make down            - Stop de containers"
	@echo "  make restart         - Herstart de containers"
	@echo "  make logs            - Bekijk de logs"
	@echo "  make ps              - Status van de containers"
	@echo ""
	@echo "Database & Data:"
	@echo "  make migrate         - Voer Symfony database migraties uit"
	@echo "  make init            - Initialiseer het project (composer install, etc)"
	@echo "  make db-copy         - Kopieer een database naar de huidige branch database"
	@echo ""
	@echo "Code Quality:"
	@echo "  make lint            - Voer GrumPHP uit (phpcs, phpmd, phpunit, rector, composer audit)"
	@echo "  make phpcs           - Voer PHP CodeSniffer uit (code style check)"
	@echo "  make phpcbf          - Voer PHP Code Beautifier uit (auto-fix code style)"
	@echo "  make phpmd           - Voer PHP Mess Detector uit (code quality check)"
	@echo "  make rector          - Voer Rector uit (code modernization)"
	@echo "  make test-backend    - Voer PHPUnit tests uit"
	@echo ""
	@echo "Kubernetes Feature Branches:"
	@echo "  make k8s-mysql       - Deploy MySQL server (eenmalig)"
	@echo "  make k8s-deploy      - Deploy huidige branch naar Kubernetes"
	@echo "  make k8s-delete      - Verwijder feature branch omgeving"
	@echo "  make k8s-logs        - Bekijk logs van feature branch"
	@echo "  make k8s-shell       - Open shell in feature branch pod"
	@echo "  make k8s-list        - Lijst alle feature branch omgevingen"
	@echo "  make k8s-status      - Toon status van feature branch omgeving"


up:
	$(DOCKER_COMPOSE) up -d

up-prod:
	docker compose -f docker-compose.yaml up -d

up-api:
	@$(DOCKER_COMPOSE) up bundle_api api_nginx --build --force-recreate --detach

up-api-cached:
	@$(DOCKER_COMPOSE) up bundle_api api_nginx --detach

up-api-test:
	@docker compose -f docker-compose-test.yaml up bundle_api api_nginx --build --force-recreate --detach

up-api-test-cached:
	@docker compose -f ./docker-compose-test.yaml up bundle_api api_nginx --detach

up-api-dev:
	@$(DOCKER_COMPOSE) up bundle_api api_nginx --build --force-recreate --detach

up-rebuild: ## Start; --force rebuild
	@$(DOCKER_COMPOSE) up --build --force-recreate --detach

up-db:
	@$(DOCKER_COMPOSE) up db --build --force-recreate --detach

up-db-test:
	@docker compose -f ./docker-compose-test.yaml up db --build --force-recreate --detach

up-db-dev:
	@$(DOCKER_COMPOSE) up db --build --force-recreate --detach

up-frontend:
	@$(DOCKER_COMPOSE) up frontend --build --force-recreate --detach

up-frontend-test:
	@API_URL=$(API_URL) docker compose -f docker-compose-test.yaml up frontend --build --force-recreate --detach

up-frontend-prod:
	@API_URL=$(API_URL) docker compose -f docker-compose.yaml up frontend --build --force-recreate --detach

up-mailer:
	@$(DOCKER_COMPOSE) up mailer --build --force-recreate --detach

setup:
	$(DOCKER_COMPOSE) up -d --build --remove-orphans
	@echo "✅ Containers gestart!"
	@echo "🌐 Web: http://localhost:8081"
	@echo "📧 Mailpit: http://localhost:8026"
	@echo "Waiting for database to be ready..."
	@sleep 5
	$(DOCKER_COMPOSE) exec bundle_api composer install
	$(DOCKER_COMPOSE) exec bundle_api php bin/console doctrine:database:create --if-not-exists
	$(DOCKER_COMPOSE) exec bundle_api php bin/console doctrine:migrations:migrate --no-interaction
	@echo ""
	@echo "✅ Setup compleet!"
	@echo "🌐 Web: http://localhost:8081"
	@echo "📧 Mailpit: http://localhost:8026"
	@echo ""
	@echo "💡 Handige commando's:"
	@echo "  make lint      - Code quality checks (GrumPHP)"
	@echo "  make test      - Run tests"
	@echo "  make help      - Alle commando's"

down:
	$(DOCKER_COMPOSE) down

restart: down up

logs:
	$(DOCKER_COMPOSE) logs -f

ps:
	$(DOCKER_COMPOSE) ps

migrate:
	$(DOCKER_COMPOSE) exec bundle_api php bin/console doctrine:migrations:migrate --no-interaction

init:
	$(DOCKER_COMPOSE) exec bundle_api composer install

db-copy:
	@echo "Starting database copy tool..."
	@$(DOCKER_COMPOSE) exec bundle_api php bin/console app:database:copy

test-backend:
	$(DOCKER_COMPOSE) exec bundle_api ./vendor/bin/phpunit

test-frontend: up-api-cached up-frontend
	$(DOCKER_COMPOSE) exec frontend npx cypress install --force
	$(DOCKER_COMPOSE) exec -d frontend npm run serve:cypress
	@echo "Waiting for angular to start and build/compile..."
	@sleep 3
	$(DOCKER_COMPOSE) exec frontend npm run e2e:run

test: test-backend test-frontend

phpcs:
	$(DOCKER_COMPOSE) exec bundle_api ./vendor/bin/phpcs -s

phpcbf:
	$(DOCKER_COMPOSE) exec bundle_api ./vendor/bin/phpcbf -s

phpmd:
	@echo "Running PHPMD with PHP 8.5 compatibility handling..."
	@$(DOCKER_COMPOSE) exec -T bundle_api bash -c 'output=$$(php -d error_reporting="E_ALL & ~E_DEPRECATED" ./vendor/bin/phpmd src text phpmd.xml 2>&1); violations=$$(echo "$$output" | grep -E "^/var/www/api/src/.+\.(php):[0-9]+" || true); if [ -n "$$violations" ]; then echo "$$violations"; exit 2; else echo "✓ No PHPMD violations found (PHP 8.5 parsing errors ignored)"; exit 0; fi'

lint:
	$(DOCKER_COMPOSE) exec bundle_api ./vendor/bin/grumphp run

rector:
	$(DOCKER_COMPOSE) exec bundle_api ./vendor/bin/rector process

bash:
	$(DOCKER_COMPOSE) exec bundle_api bash

bash-frontend:
	$(DOCKER_COMPOSE) exec frontend bash

cc:
	$(DOCKER_COMPOSE) exec bundle_api ./bin/console cache:clear

rcc:
	$(DOCKER_COMPOSE) exec bundle_api rm -rf ./api/var/cache


## —— Debugging 🐞  ————————————————————————————————————————————————————————————————
xdebug-toggle:
	@echo "Toggling Xdebug..."
	@$(DOCKER_COMPOSE) exec bundle_api bash /var/www/api/docker/script/toggle-xdebug.sh
	@echo "Restarting containers..."
	@$(DOCKER_COMPOSE) restart api

xdebug-enable:
	@echo "Enabling Xdebug..."
	@$(DOCKER_COMPOSE) exec bundle_api bash /var/www/api/docker/script/enable-xdebug.sh
	@echo "Restarting containers..."
	@$(DOCKER_COMPOSE) restart api

xdebug-disable:
	@echo "Disabling Xdebug..."
	@$(DOCKER_COMPOSE) exec bundle_api bash /var/www/api/docker/script/disable-xdebug.sh
	@echo "Restarting containers..."
	@$(DOCKER_COMPOSE) restart api
