#!/bin/bash
# ═══════════════════════════════════════════════════════════════════════════════
# JGGL CRM — Developer Commands (Bash)
# ═══════════════════════════════════════════════════════════════════════════════
#
# Usage:
#   ./scripts/dev.sh help       — показать все команды
#   ./scripts/dev.sh up         — поднять контейнеры
#   ./scripts/dev.sh test       — запустить тесты
#
# ═══════════════════════════════════════════════════════════════════════════════

set -e

# Configuration
COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.yml}"
DOCKER_COMPOSE="docker compose -f $COMPOSE_FILE"

# Colors
CYAN='\033[0;36m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
RED='\033[0;31m'
NC='\033[0m'

# ─────────────────────────────────────────────────────────────────────────────
# Functions
# ─────────────────────────────────────────────────────────────────────────────

show_help() {
    echo ""
    echo -e "${CYAN}╔═══════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${CYAN}║           JGGL CRM — Developer Commands                       ║${NC}"
    echo -e "${CYAN}╚═══════════════════════════════════════════════════════════════╝${NC}"
    echo ""
    echo -e "  ${GREEN}up${NC}              Поднять контейнеры"
    echo -e "  ${GREEN}down${NC}            Остановить контейнеры"
    echo -e "  ${GREEN}restart${NC}         Перезапустить контейнеры"
    echo -e "  ${GREEN}logs${NC}            Показать логи"
    echo -e "  ${GREEN}ps${NC}              Статус контейнеров"
    echo ""
    echo -e "  ${GREEN}test${NC}            Запустить тесты"
    echo -e "  ${GREEN}lint${NC}            Проверить code style"
    echo -e "  ${GREEN}lint-fix${NC}        Исправить code style"
    echo -e "  ${GREEN}build${NC}           Собрать frontend"
    echo -e "  ${GREEN}doctor${NC}          Диагностика системы"
    echo ""
    echo -e "  ${GREEN}migrate${NC}         Выполнить миграции"
    echo -e "  ${GREEN}seed${NC}            Запустить сиды"
    echo -e "  ${GREEN}reset${NC}           Быстрый reset окружения"
    echo -e "  ${GREEN}fresh${NC}           Полный reset (DROP ALL!)"
    echo ""
    echo -e "  ${GREEN}shell${NC}           Открыть shell в контейнере"
    echo -e "  ${GREEN}tinker${NC}          Открыть Laravel Tinker"
    echo -e "  ${GREEN}install${NC}         Первая установка"
    echo ""
    echo -e "${YELLOW}Quick Start:${NC}"
    echo "  ./scripts/dev.sh install    # Первая установка"
    echo "  ./scripts/dev.sh up         # Поднять контейнеры"
    echo "  ./scripts/dev.sh test       # Запустить тесты"
    echo ""
}

cmd_up() {
    echo -e "${CYAN}▶ Starting containers...${NC}"
    $DOCKER_COMPOSE up -d
    echo -e "${GREEN}✓ Containers started${NC}"
    echo -e "${YELLOW}→ App: http://localhost:8080${NC}"
    echo -e "${YELLOW}→ Admin: http://localhost:8080/admin${NC}"
}

cmd_down() {
    echo -e "${CYAN}▶ Stopping containers...${NC}"
    $DOCKER_COMPOSE down
    echo -e "${GREEN}✓ Containers stopped${NC}"
}

cmd_restart() {
    echo -e "${CYAN}▶ Restarting containers...${NC}"
    $DOCKER_COMPOSE restart
    echo -e "${GREEN}✓ Containers restarted${NC}"
}

cmd_logs() {
    $DOCKER_COMPOSE logs -f
}

cmd_ps() {
    $DOCKER_COMPOSE ps
}

cmd_test() {
    echo -e "${CYAN}▶ Running tests...${NC}"
    $DOCKER_COMPOSE exec -T app php artisan test
    echo -e "${GREEN}✓ Tests completed${NC}"
}

cmd_lint() {
    echo -e "${CYAN}▶ Checking code style...${NC}"
    $DOCKER_COMPOSE exec -T app ./vendor/bin/pint --test
    echo -e "${GREEN}✓ Code style OK${NC}"
}

cmd_lint_fix() {
    echo -e "${CYAN}▶ Fixing code style...${NC}"
    $DOCKER_COMPOSE exec -T app ./vendor/bin/pint
    echo -e "${GREEN}✓ Code style fixed${NC}"
}

cmd_build() {
    echo -e "${CYAN}▶ Building frontend...${NC}"
    npm run build
    echo -e "${GREEN}✓ Frontend built${NC}"
}

cmd_doctor() {
    echo -e "${CYAN}▶ Running diagnostics...${NC}"
    $DOCKER_COMPOSE exec -T app php artisan jggl:doctor
}

cmd_migrate() {
    echo -e "${CYAN}▶ Running migrations...${NC}"
    $DOCKER_COMPOSE exec -T app php artisan migrate
    echo -e "${GREEN}✓ Migrations completed${NC}"
}

cmd_seed() {
    echo -e "${CYAN}▶ Running seeders...${NC}"
    $DOCKER_COMPOSE exec -T app php artisan db:seed
    echo -e "${GREEN}✓ Seeders completed${NC}"
}

cmd_reset() {
    echo -e "${CYAN}▶ Resetting environment...${NC}"
    $DOCKER_COMPOSE exec -T app php artisan config:clear
    $DOCKER_COMPOSE exec -T app php artisan cache:clear
    $DOCKER_COMPOSE exec -T app php artisan view:clear
    $DOCKER_COMPOSE exec -T app php artisan migrate --force
    $DOCKER_COMPOSE exec -T app php artisan db:seed --force
    echo -e "${GREEN}✓ Environment reset completed${NC}"
}

cmd_fresh() {
    echo -e "${YELLOW}⚠ WARNING: This will DELETE ALL DATA!${NC}"
    read -p "Type 'yes' to confirm: " confirm
    if [ "$confirm" != "yes" ]; then
        echo "Cancelled."
        exit 0
    fi
    echo -e "${CYAN}▶ Fresh install...${NC}"
    $DOCKER_COMPOSE exec -T app php artisan migrate:fresh --seed --force
    $DOCKER_COMPOSE exec -T app php artisan config:clear
    $DOCKER_COMPOSE exec -T app php artisan cache:clear
    echo -e "${GREEN}✓ Fresh install completed${NC}"
}

cmd_shell() {
    $DOCKER_COMPOSE exec app bash
}

cmd_tinker() {
    $DOCKER_COMPOSE exec app php artisan tinker
}

cmd_install() {
    echo ""
    echo -e "${CYAN}╔═══════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${CYAN}║           JGGL CRM — First Time Setup                         ║${NC}"
    echo -e "${CYAN}╚═══════════════════════════════════════════════════════════════╝${NC}"
    echo ""
    
    if [ ! -f .env ]; then
        echo -e "${CYAN}▶ Creating .env file...${NC}"
        cp docker/env.example .env
        echo -e "${YELLOW}⚠ Edit .env and set DB_PASSWORD before continuing!${NC}"
        echo "Then run: ./scripts/dev.sh install"
        exit 1
    fi
    
    echo -e "${CYAN}▶ Installing npm dependencies...${NC}"
    npm ci
    
    echo -e "${CYAN}▶ Starting containers...${NC}"
    $DOCKER_COMPOSE up -d --build
    
    echo -e "${CYAN}▶ Waiting for database (15s)...${NC}"
    sleep 15
    
    echo -e "${CYAN}▶ Running migrations...${NC}"
    $DOCKER_COMPOSE exec -T app php artisan migrate --force
    
    echo -e "${CYAN}▶ Running seeders...${NC}"
    $DOCKER_COMPOSE exec -T app php artisan db:seed --force
    
    echo -e "${CYAN}▶ Building frontend...${NC}"
    npm run build
    
    echo ""
    echo -e "${GREEN}╔═══════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║           ✓ Installation Complete!                            ║${NC}"
    echo -e "${GREEN}╚═══════════════════════════════════════════════════════════════╝${NC}"
    echo ""
    echo -e "${YELLOW}→ App:   http://localhost:8080${NC}"
    echo -e "${YELLOW}→ Admin: http://localhost:8080/admin${NC}"
    echo -e "${YELLOW}→ Login: admin@crm.test / admin123${NC}"
    echo ""
}

cmd_cache_clear() {
    echo -e "${CYAN}▶ Clearing caches...${NC}"
    $DOCKER_COMPOSE exec -T app php artisan config:clear
    $DOCKER_COMPOSE exec -T app php artisan cache:clear
    $DOCKER_COMPOSE exec -T app php artisan view:clear
    $DOCKER_COMPOSE exec -T app php artisan route:clear
    echo -e "${GREEN}✓ Caches cleared${NC}"
}

cmd_check() {
    cmd_lint
    cmd_test
    echo -e "${GREEN}✓ All checks passed${NC}"
}

# ─────────────────────────────────────────────────────────────────────────────
# Command Router
# ─────────────────────────────────────────────────────────────────────────────

COMMAND="${1:-help}"

case "$COMMAND" in
    help)       show_help ;;
    up)         cmd_up ;;
    down)       cmd_down ;;
    restart)    cmd_restart ;;
    logs)       cmd_logs ;;
    ps)         cmd_ps ;;
    test)       cmd_test ;;
    lint)       cmd_lint ;;
    lint-fix)   cmd_lint_fix ;;
    build)      cmd_build ;;
    doctor)     cmd_doctor ;;
    migrate)    cmd_migrate ;;
    seed)       cmd_seed ;;
    reset)      cmd_reset ;;
    fresh)      cmd_fresh ;;
    shell)      cmd_shell ;;
    tinker)     cmd_tinker ;;
    install)    cmd_install ;;
    cache-clear) cmd_cache_clear ;;
    check)      cmd_check ;;
    *)
        echo -e "${RED}Unknown command: $COMMAND${NC}"
        echo "Run './scripts/dev.sh help' for available commands."
        exit 1
        ;;
esac
