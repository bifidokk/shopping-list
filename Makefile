.PHONY: help up down build restart logs shell db-shell test test-setup migrate migrate-test phpstan cs-fix cs-check clear-cache composer-install

# Default target
.DEFAULT_GOAL := help

# Docker Compose command
DC = docker compose
DC_EXEC = $(DC) exec php
DC_EXEC_TEST = $(DC_EXEC) env APP_ENV=test

##
## Project Setup
## ———————————————————————————————————————————————————————————————————————————————

help: ## Show this help message
	@grep -E '(^[a-zA-Z0-9_-]+:.*?##.*$$)|(^##)' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}{printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}' | sed -e 's/\[32m##/[33m/'

build: ## Build Docker containers
	$(DC) build

up: ## Start all containers (dev profile)
	$(DC) --profile dev up -d

up-test: ## Start all containers (dev + test profiles)
	$(DC) --profile dev --profile test up -d

down: ## Stop all containers
	$(DC) down

restart: ## Restart all containers
	$(DC) restart

logs: ## Show container logs (use: make logs SERVICE=php)
	$(DC) logs -f $(SERVICE)

ps: ## Show running containers
	$(DC) ps

##
## Shell Access
## ———————————————————————————————————————————————————————————————————————————————

shell: ## Enter PHP container shell
	$(DC_EXEC) sh

db-shell: ## Enter PostgreSQL shell (dev database)
	$(DC) exec database psql -U app -d app

db-shell-test: ## Enter PostgreSQL shell (test database)
	$(DC) exec database_test psql -U test -d shopping_list_test

##
## Database
## ———————————————————————————————————————————————————————————————————————————————

migrate: ## Run database migrations (dev)
	$(DC_EXEC) bin/console doctrine:migrations:migrate --no-interaction

migrate-test: ## Run database migrations (test)
	$(DC_EXEC_TEST) bin/console doctrine:migrations:migrate --no-interaction

migrate-status: ## Show migration status (dev)
	$(DC_EXEC) bin/console doctrine:migrations:status

migrate-status-test: ## Show migration status (test)
	$(DC_EXEC_TEST) bin/console doctrine:migrations:status

db-create: ## Create database (dev)
	$(DC_EXEC) bin/console doctrine:database:create --if-not-exists

db-create-test: ## Create test database
	$(DC_EXEC_TEST) bin/console doctrine:database:create --if-not-exists

db-drop: ## Drop database (dev) - DANGEROUS!
	$(DC_EXEC) bin/console doctrine:database:drop --force --if-exists

db-drop-test: ## Drop test database
	$(DC_EXEC_TEST) bin/console doctrine:database:drop --force --if-exists

db-reset: down db-drop db-create migrate ## Reset database (dev) - DANGEROUS!
	@echo "Database reset complete"

db-reset-test: db-drop-test db-create-test migrate-test ## Reset test database
	@echo "Test database reset complete"

fixtures: ## Load fixtures (test environment)
	$(DC_EXEC_TEST) bin/console doctrine:fixtures:load --no-interaction

##
## Testing
## ———————————————————————————————————————————————————————————————————————————————

test: ## Run all tests
	$(DC_EXEC) bin/phpunit

test-unit: ## Run unit tests only
	$(DC_EXEC) bin/phpunit tests/Service/

test-integration: ## Run integration tests only
	$(DC_EXEC) bin/phpunit tests/Integration/

test-coverage: ## Run tests with coverage report
	$(DC_EXEC) bin/phpunit --coverage-html var/coverage

test-setup: up-test migrate-test ## Setup test environment and run tests
	$(DC_EXEC) bin/phpunit

test-filter: ## Run specific test (use: make test-filter FILTER=testMethodName)
	$(DC_EXEC) bin/phpunit --filter $(FILTER)

##
## Code Quality
## ———————————————————————————————————————————————————————————————————————————————

phpstan: ## Run PHPStan static analysis
	@echo "Warming up cache..."
	@$(DC_EXEC) bin/console cache:warmup --env=dev > /dev/null 2>&1 || true
	$(DC_EXEC) php -d memory_limit=-1 vendor/bin/phpstan analyse

phpstan-baseline: ## Generate PHPStan baseline
	@echo "Warming up cache..."
	@$(DC_EXEC) bin/console cache:warmup --env=dev > /dev/null 2>&1 || true
	$(DC_EXEC) php -d memory_limit=-1 vendor/bin/phpstan analyse --generate-baseline

cs-fix: ## Fix code style issues
	$(DC_EXEC) vendor/bin/php-cs-fixer fix

cs-check: ## Check code style without fixing
	$(DC_EXEC) vendor/bin/php-cs-fixer fix --dry-run --diff

qa: phpstan cs-check test ## Run all quality checks (PHPStan + CS + Tests)

##
## Composer
## ———————————————————————————————————————————————————————————————————————————————

composer-install: ## Install composer dependencies
	$(DC_EXEC) composer install

composer-update: ## Update composer dependencies
	$(DC_EXEC) composer update

composer-require: ## Install new package (use: make composer-require PKG=vendor/package)
	$(DC_EXEC) composer require $(PKG)

composer-dump: ## Dump autoload
	$(DC_EXEC) composer dump-autoload

##
## Cache
## ———————————————————————————————————————————————————————————————————————————————

clear-cache: ## Clear Symfony cache (dev)
	$(DC_EXEC) bin/console cache:clear

clear-cache-test: ## Clear Symfony cache (test)
	$(DC_EXEC_TEST) bin/console cache:clear

clear-cache-all: ## Clear all caches (dev + test + doctrine)
	$(DC_EXEC) rm -rf var/cache/*
	$(DC_EXEC_TEST) bin/console doctrine:cache:clear-metadata
	$(DC_EXEC_TEST) bin/console doctrine:cache:clear-query
	$(DC_EXEC_TEST) bin/console doctrine:cache:clear-result
	@echo "All caches cleared"

##
## CI/CD Pipeline
## ———————————————————————————————————————————————————————————————————————————————

ci: ## Run full CI pipeline (quality checks + tests)
	@echo "Running CI pipeline..."
	@$(MAKE) phpstan
	@$(MAKE) cs-check
	@$(MAKE) test
	@echo "✅ CI pipeline completed successfully"

deploy-check: ## Pre-deployment checks
	@echo "Running pre-deployment checks..."
	@$(MAKE) composer-install
	@$(MAKE) migrate-status
	@$(MAKE) ci
	@echo "✅ Ready for deployment"

##
## Development Shortcuts
## ———————————————————————————————————————————————————————————————————————————————

dev: up migrate fixtures ## Start dev environment with migrations and fixtures
	@echo "✅ Development environment ready"
	@echo "🌐 API: http://localhost:8080"
	@echo "🗄️  Database: localhost:5432"

fresh: down build up migrate fixtures ## Fresh install with rebuild
	@echo "✅ Fresh installation complete"

watch-logs: ## Watch all logs
	$(DC) logs -f

routes: ## Show all routes
	$(DC_EXEC) bin/console debug:router

container-list: ## Show all available console commands
	$(DC_EXEC) bin/console list
