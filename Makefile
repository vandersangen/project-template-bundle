.PHONY: help up down restart shell composer-install composer-update test console cache-clear db-migrate db-create status

help: ## Toon dit help bericht
	@echo 'Gebruik: make [target]'
	@echo ''
	@echo 'Beschikbare commando'\''s:'
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "  %-20s %s\n", $$1, $$2}' $(MAKEFILE_LIST)

## —— Docker 🐳 ————————————————————————————————————————————————————————————————
up: ## Start de Docker containers
	docker-compose -f docker-compose-dev.yaml up -d
	@echo "✅ Containers gestart!"
	@echo "🌐 Web: http://localhost:8081"
	@echo "📧 Mailpit: http://localhost:8026"

down: ## Stop de Docker containers
	docker-compose -f docker-compose-dev.yaml down

restart: down up ## Herstart de Docker containers

build: ## Bouw Docker images opnieuw
	docker-compose -f docker-compose-dev.yaml build

status: ## Toon status van containers
	@docker-compose -f docker-compose-dev.yaml ps

logs: ## Toon logs (gebruik Ctrl+C om te stoppen)
	docker-compose -f docker-compose-dev.yaml logs -f

## —— Composer 📦 ————————————————————————————————————————————————————————————————
composer-install: ## Installeer Composer dependencies
	docker exec bundle_api composer install

composer-update: ## Update Composer dependencies
	docker exec bundle_api composer update

composer-require: ## Installeer een package (gebruik: make composer-require PACKAGE=vendor/package)
	docker exec bundle_api composer require $(PACKAGE)

## —— Symfony 🎵 ————————————————————————————————————————————————————————————————
shell: ## Open een shell in de bundle_api container
	docker exec -it bundle_api bash

console: ## Open Symfony console (gebruik: make console CMD="debug:router")
	docker exec bundle_api php bin/console $(CMD)

cache-clear: ## Clear Symfony cache
	docker exec bundle_api php bin/console cache:clear

## —— Database 🗄️ ————————————————————————————————————————————————————————————————
db-create: ## Maak database aan
	docker exec bundle_api php bin/console doctrine:database:create --if-not-exists

db-migrate: ## Voer database migraties uit
	docker exec bundle_api php bin/console doctrine:migrations:migrate --no-interaction

db-reset: ## Reset database (VERWIJDERT ALLE DATA!)
	docker exec bundle_api php bin/console doctrine:database:drop --force --if-exists
	docker exec bundle_api php bin/console doctrine:database:create
	docker exec bundle_api php bin/console doctrine:migrations:migrate --no-interaction

## —— Testing 🧪 ————————————————————————————————————————————————————————————————
test: ## Voer alle tests uit
	docker exec bundle_api php bin/phpunit

test-coverage: ## Voer tests uit met coverage report
	docker exec bundle_api php bin/phpunit --coverage-html var/coverage

## —— Git 📝 ————————————————————————————————————————————————————————————————
git-status: ## Toon Git status (alleen bundle files)
	@git status

git-add: ## Add bundle changes naar Git
	@git add bundle/
	@git status

git-commit: ## Commit bundle changes (gebruik: make git-commit MSG="Your message")
	@git add bundle/
	@git commit -m "$(MSG)"

git-push: ## Push naar remote
	@git push

## —— Setup 🚀 ————————————————————————————————————————————————————————————————
setup: up composer-install db-create db-migrate ## Volledige setup (eerste keer)
	@echo "✅ Setup compleet!"
	@echo "🌐 Web: http://localhost:8081"
	@echo "📧 Mailpit: http://localhost:8026"

