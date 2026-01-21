# ═══════════════════════════════════════════════════════════════════════════════
# JGGL CRM — Developer Commands
# ═══════════════════════════════════════════════════════════════════════════════
#
# Usage:
#   make help       — показать все команды
#   make up         — поднять контейнеры
#   make test       — запустить тесты
#   make lint       — проверить code style
#
# ═══════════════════════════════════════════════════════════════════════════════

.PHONY: help up down restart logs test lint build doctor reset fresh shell db-shell redis-shell migrate seed cache-clear install

# Default target
.DEFAULT_GOAL := help

# ─────────────────────────────────────────────────────────────────────────────
# Configuration
# ─────────────────────────────────────────────────────────────────────────────
COMPOSE_FILE ?= docker-compose.yml
DOCKER_COMPOSE = docker compose -f $(COMPOSE_FILE)
EXEC_APP = $(DOCKER_COMPOSE) exec -T app
EXEC_APP_IT = $(DOCKER_COMPOSE) exec app

# Colors
CYAN := \033[0;36m
GREEN := \033[0;32m
YELLOW := \033[0;33m
NC := \033[0m

# ─────────────────────────────────────────────────────────────────────────────
# Help
# ─────────────────────────────────────────────────────────────────────────────
help: ## Показать справку по командам
	@echo ""
	@echo "$(CYAN)╔═══════════════════════════════════════════════════════════════╗$(NC)"
	@echo "$(CYAN)║           JGGL CRM — Developer Commands                       ║$(NC)"
	@echo "$(CYAN)╚═══════════════════════════════════════════════════════════════╝$(NC)"
	@echo ""
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  $(GREEN)%-15s$(NC) %s\n", $$1, $$2}'
	@echo ""
	@echo "$(YELLOW)Quick Start:$(NC)"
	@echo "  make install    # Первая установка"
	@echo "  make up         # Поднять контейнеры"
	@echo "  make test       # Запустить тесты"
	@echo ""

# ─────────────────────────────────────────────────────────────────────────────
# Docker Lifecycle
# ─────────────────────────────────────────────────────────────────────────────
up: ## Поднять контейнеры
	@echo "$(CYAN)▶ Starting containers...$(NC)"
	$(DOCKER_COMPOSE) up -d
	@echo "$(GREEN)✓ Containers started$(NC)"
	@echo "$(YELLOW)→ App: http://localhost:8080$(NC)"
	@echo "$(YELLOW)→ Admin: http://localhost:8080/admin$(NC)"

down: ## Остановить контейнеры
	@echo "$(CYAN)▶ Stopping containers...$(NC)"
	$(DOCKER_COMPOSE) down
	@echo "$(GREEN)✓ Containers stopped$(NC)"

restart: ## Перезапустить контейнеры
	@echo "$(CYAN)▶ Restarting containers...$(NC)"
	$(DOCKER_COMPOSE) restart
	@echo "$(GREEN)✓ Containers restarted$(NC)"

logs: ## Показать логи (все сервисы)
	$(DOCKER_COMPOSE) logs -f

logs-app: ## Показать логи app контейнера
	$(DOCKER_COMPOSE) logs -f app

ps: ## Статус контейнеров
	$(DOCKER_COMPOSE) ps

# ─────────────────────────────────────────────────────────────────────────────
# Development
# ─────────────────────────────────────────────────────────────────────────────
test: ## Запустить тесты
	@echo "$(CYAN)▶ Running tests...$(NC)"
	$(EXEC_APP) php artisan test
	@echo "$(GREEN)✓ Tests completed$(NC)"

test-filter: ## Запустить тесты с фильтром (usage: make test-filter FILTER=SearchTest)
	@echo "$(CYAN)▶ Running filtered tests...$(NC)"
	$(EXEC_APP) php artisan test --filter=$(FILTER)

lint: ## Проверить code style (Pint)
	@echo "$(CYAN)▶ Checking code style...$(NC)"
	$(EXEC_APP) ./vendor/bin/pint --test
	@echo "$(GREEN)✓ Code style OK$(NC)"

lint-fix: ## Исправить code style (Pint)
	@echo "$(CYAN)▶ Fixing code style...$(NC)"
	$(EXEC_APP) ./vendor/bin/pint
	@echo "$(GREEN)✓ Code style fixed$(NC)"

build: ## Собрать frontend (production)
	@echo "$(CYAN)▶ Building frontend...$(NC)"
	npm run build
	@echo "$(GREEN)✓ Frontend built$(NC)"

dev: ## Запустить frontend в dev режиме (watch)
	npm run dev

doctor: ## Диагностика системы
	@echo "$(CYAN)▶ Running diagnostics...$(NC)"
	$(EXEC_APP) php artisan jggl:doctor

# ─────────────────────────────────────────────────────────────────────────────
# Database & Migrations
# ─────────────────────────────────────────────────────────────────────────────
migrate: ## Выполнить миграции
	@echo "$(CYAN)▶ Running migrations...$(NC)"
	$(EXEC_APP) php artisan migrate
	@echo "$(GREEN)✓ Migrations completed$(NC)"

migrate-fresh: ## Пересоздать БД (DROP ALL + migrate)
	@echo "$(YELLOW)⚠ This will DROP all tables!$(NC)"
	@read -p "Continue? [y/N] " confirm && [ "$$confirm" = "y" ] || exit 1
	$(EXEC_APP) php artisan migrate:fresh
	@echo "$(GREEN)✓ Database recreated$(NC)"

seed: ## Запустить сиды
	@echo "$(CYAN)▶ Running seeders...$(NC)"
	$(EXEC_APP) php artisan db:seed
	@echo "$(GREEN)✓ Seeders completed$(NC)"

migrate-status: ## Статус миграций
	$(EXEC_APP) php artisan migrate:status

# ─────────────────────────────────────────────────────────────────────────────
# Cache Management
# ─────────────────────────────────────────────────────────────────────────────
cache-clear: ## Очистить все кэши Laravel
	@echo "$(CYAN)▶ Clearing caches...$(NC)"
	$(EXEC_APP) php artisan config:clear
	$(EXEC_APP) php artisan cache:clear
	$(EXEC_APP) php artisan view:clear
	$(EXEC_APP) php artisan route:clear
	$(EXEC_APP) php artisan event:clear
	@echo "$(GREEN)✓ Caches cleared$(NC)"

cache-warm: ## Прогреть кэши (production)
	@echo "$(CYAN)▶ Warming caches...$(NC)"
	$(EXEC_APP) php artisan config:cache
	$(EXEC_APP) php artisan route:cache
	$(EXEC_APP) php artisan view:cache
	@echo "$(GREEN)✓ Caches warmed$(NC)"

# ─────────────────────────────────────────────────────────────────────────────
# Reset & Fresh Install
# ─────────────────────────────────────────────────────────────────────────────
reset: ## Быстрый reset: очистить кэши + миграции + сиды
	@echo "$(CYAN)▶ Resetting environment...$(NC)"
	$(EXEC_APP) php artisan config:clear
	$(EXEC_APP) php artisan cache:clear
	$(EXEC_APP) php artisan view:clear
	$(EXEC_APP) php artisan migrate --force
	$(EXEC_APP) php artisan db:seed --force
	@echo "$(GREEN)✓ Environment reset completed$(NC)"

fresh: ## Полный reset: DROP ALL + migrate + seed (DANGEROUS!)
	@echo "$(YELLOW)⚠ WARNING: This will DELETE ALL DATA!$(NC)"
	@read -p "Type 'yes' to confirm: " confirm && [ "$$confirm" = "yes" ] || exit 1
	@echo "$(CYAN)▶ Fresh install...$(NC)"
	$(EXEC_APP) php artisan migrate:fresh --seed --force
	$(EXEC_APP) php artisan config:clear
	$(EXEC_APP) php artisan cache:clear
	@echo "$(GREEN)✓ Fresh install completed$(NC)"

# ─────────────────────────────────────────────────────────────────────────────
# First Time Setup
# ─────────────────────────────────────────────────────────────────────────────
install: ## Первая установка (для новичков)
	@echo "$(CYAN)╔═══════════════════════════════════════════════════════════════╗$(NC)"
	@echo "$(CYAN)║           JGGL CRM — First Time Setup                         ║$(NC)"
	@echo "$(CYAN)╚═══════════════════════════════════════════════════════════════╝$(NC)"
	@echo ""
	@if [ ! -f .env ]; then \
		echo "$(CYAN)▶ Creating .env file...$(NC)"; \
		cp docker/env.example .env; \
		echo "$(YELLOW)⚠ Edit .env and set DB_PASSWORD before continuing!$(NC)"; \
		exit 1; \
	fi
	@echo "$(CYAN)▶ Installing npm dependencies...$(NC)"
	npm ci
	@echo "$(CYAN)▶ Starting containers...$(NC)"
	$(DOCKER_COMPOSE) up -d --build
	@echo "$(CYAN)▶ Waiting for database (15s)...$(NC)"
	@sleep 15
	@echo "$(CYAN)▶ Running migrations...$(NC)"
	$(EXEC_APP) php artisan migrate --force
	@echo "$(CYAN)▶ Running seeders...$(NC)"
	$(EXEC_APP) php artisan db:seed --force
	@echo "$(CYAN)▶ Building frontend...$(NC)"
	npm run build
	@echo ""
	@echo "$(GREEN)╔═══════════════════════════════════════════════════════════════╗$(NC)"
	@echo "$(GREEN)║           ✓ Installation Complete!                            ║$(NC)"
	@echo "$(GREEN)╚═══════════════════════════════════════════════════════════════╝$(NC)"
	@echo ""
	@echo "$(YELLOW)→ App:   http://localhost:8080$(NC)"
	@echo "$(YELLOW)→ Admin: http://localhost:8080/admin$(NC)"
	@echo "$(YELLOW)→ Login: admin@crm.test / admin123$(NC)"
	@echo ""

# ─────────────────────────────────────────────────────────────────────────────
# Shell Access
# ─────────────────────────────────────────────────────────────────────────────
shell: ## Открыть shell в app контейнере
	$(EXEC_APP_IT) bash

tinker: ## Открыть Laravel Tinker
	$(EXEC_APP_IT) php artisan tinker

db-shell: ## Открыть psql shell
	$(DOCKER_COMPOSE) exec db psql -U crm -d crm

redis-shell: ## Открыть redis-cli
	$(DOCKER_COMPOSE) exec redis redis-cli

# ─────────────────────────────────────────────────────────────────────────────
# Utilities
# ─────────────────────────────────────────────────────────────────────────────
reindex: ## Переиндексировать лиды (FTS)
	@echo "$(CYAN)▶ Reindexing leads...$(NC)"
	$(EXEC_APP) php artisan crm:reindex-leads
	@echo "$(GREEN)✓ Reindexing completed$(NC)"

queue-work: ## Запустить queue worker (foreground)
	$(EXEC_APP_IT) php artisan queue:work --verbose

queue-status: ## Статус очередей
	$(DOCKER_COMPOSE) exec redis redis-cli LLEN queues:default
	$(DOCKER_COMPOSE) exec redis redis-cli LLEN queues:meta
	$(DOCKER_COMPOSE) exec redis redis-cli LLEN queues:ai

# ─────────────────────────────────────────────────────────────────────────────
# CI/CD
# ─────────────────────────────────────────────────────────────────────────────
ci: lint test build ## Запустить все CI проверки
	@echo "$(GREEN)✓ All CI checks passed$(NC)"

check: ## Быстрая проверка (lint + test)
	@$(MAKE) lint
	@$(MAKE) test
	@echo "$(GREEN)✓ All checks passed$(NC)"
