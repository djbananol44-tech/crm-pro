#!/bin/bash
# ═══════════════════════════════════════════════════════════════════════════════
#  JGGL CRM — One-Command Installer for Ubuntu 22.04 / 24.04
#  Usage: curl -fsSL https://raw.githubusercontent.com/.../install.sh | sudo bash
#
#  Options:
#    --dev    Build locally (development)
#    --prod   Pull pre-built images (production, default)
# ═══════════════════════════════════════════════════════════════════════════════

set -e

# ─────────────────────────────────────────────────────────────────────────────
# Colors
# ─────────────────────────────────────────────────────────────────────────────
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

# ─────────────────────────────────────────────────────────────────────────────
# Config
# ─────────────────────────────────────────────────────────────────────────────
INSTALL_DIR="${JGGL_INSTALL_DIR:-/opt/jggl-crm}"
DOMAIN="${JGGL_DOMAIN:-jgglgocrm.org}"
REPO_URL="${JGGL_REPO:-https://github.com/djbananol44-tech/crm-pro.git}"
APP_IMAGE="${APP_IMAGE:-ghcr.io/djbananol44-tech/crm-pro:latest}"
WEB_PORT="${WEB_PORT:-8080}"
ADMIN_EMAIL="admin@crm.test"
ADMIN_PASS="admin123"

# Mode: prod (pull images) or dev (build locally)
MODE="prod"
if [[ "$1" == "--dev" ]]; then
    MODE="dev"
fi

# ─────────────────────────────────────────────────────────────────────────────
# Functions
# ─────────────────────────────────────────────────────────────────────────────
log_info()  { echo -e "${BLUE}ℹ️  $1${NC}"; }
log_ok()    { echo -e "${GREEN}✅ $1${NC}"; }
log_warn()  { echo -e "${YELLOW}⚠️  $1${NC}"; }
log_error() { echo -e "${RED}❌ $1${NC}"; }
log_step()  { echo -e "\n${CYAN}${BOLD}▶ $1${NC}"; }

check_root() {
    if [[ $EUID -ne 0 ]]; then
        log_error "Запустите скрипт с правами root: sudo bash install.sh"
        exit 1
    fi
}

check_os() {
    if [[ -f /etc/os-release ]]; then
        . /etc/os-release
        if [[ "$ID" != "ubuntu" ]] || ! [[ "$VERSION_ID" =~ ^(22|24) ]]; then
            log_warn "Рекомендуется Ubuntu 22.04 или 24.04. Текущая: $PRETTY_NAME"
        fi
    fi
}

install_docker() {
    if command -v docker &>/dev/null && docker compose version &>/dev/null; then
        log_ok "Docker и Docker Compose уже установлены"
        return 0
    fi

    log_step "Установка Docker..."
    
    apt-get remove -y docker docker-engine docker.io containerd runc 2>/dev/null || true
    apt-get update -qq
    apt-get install -y -qq ca-certificates curl gnupg git

    install -m 0755 -d /etc/apt/keyrings
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg 2>/dev/null || true
    chmod a+r /etc/apt/keyrings/docker.gpg

    echo \
      "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu \
      $(. /etc/os-release && echo "$VERSION_CODENAME") stable" | \
      tee /etc/apt/sources.list.d/docker.list > /dev/null

    apt-get update -qq
    apt-get install -y -qq docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

    systemctl enable docker
    systemctl start docker

    log_ok "Docker установлен"
}

clone_or_update_repo() {
    log_step "Загрузка JGGL CRM..."

    if ! command -v git &>/dev/null; then
        apt-get install -y -qq git
    fi

    if [[ -d "$INSTALL_DIR/.git" ]]; then
        log_info "Обновление существующей установки..."
        cd "$INSTALL_DIR"
        git fetch origin 2>/dev/null || true
        git reset --hard origin/main 2>/dev/null || git reset --hard origin/master 2>/dev/null || true
    else
        rm -rf "$INSTALL_DIR"
        git clone --depth 1 "$REPO_URL" "$INSTALL_DIR" || {
            log_error "Не удалось клонировать репозиторий: $REPO_URL"
            exit 1
        }
        cd "$INSTALL_DIR"
    fi

    log_ok "Код загружен в $INSTALL_DIR"
}

create_env() {
    log_step "Настройка окружения..."

    cd "$INSTALL_DIR"

    if [[ -f .env ]] && grep -q "^APP_KEY=base64:" .env; then
        log_info ".env уже существует, пропускаем..."
        return 0
    fi

    APP_KEY="base64:$(openssl rand -base64 32)"
    DB_PASSWORD="$(openssl rand -hex 16)"
    REDIS_PASSWORD="$(openssl rand -hex 16)"

    cat > .env << EOF
# ═══════════════════════════════════════════════════════════════════════════
# JGGL CRM — Production Environment
# Generated: $(date -Iseconds)
# ═══════════════════════════════════════════════════════════════════════════

APP_NAME="JGGL CRM"
APP_ENV=production
APP_KEY=${APP_KEY}
APP_DEBUG=false
APP_URL=https://${DOMAIN}

# Docker Image (for production mode)
APP_IMAGE=${APP_IMAGE}

# Database
DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=crm
DB_USERNAME=crm
DB_PASSWORD=${DB_PASSWORD}

# Redis
REDIS_HOST=redis
REDIS_PASSWORD=${REDIS_PASSWORD}
REDIS_PORT=6379

# Cache & Queue
CACHE_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

# Logging
LOG_CHANNEL=stack
LOG_LEVEL=warning

# Web Port
WEB_PORT=${WEB_PORT}
EOF

    log_ok "Файл .env создан"
}

start_containers() {
    log_step "Запуск контейнеров (режим: ${MODE})..."
    
    cd "$INSTALL_DIR"
    
    docker compose down --remove-orphans 2>/dev/null || true
    
    if [[ "$MODE" == "prod" ]]; then
        # Production: pull pre-built images
        log_info "Загрузка готовых образов из GHCR..."
        docker compose -f docker-compose.prod.yml pull --quiet 2>/dev/null || {
            log_warn "Не удалось загрузить из GHCR, переключаемся на локальную сборку"
            MODE="dev"
        }
    fi
    
    if [[ "$MODE" == "prod" ]]; then
        docker compose -f docker-compose.prod.yml up -d
    else
        # Development: build locally
        log_info "Локальная сборка образов..."
        docker compose up -d --build
    fi

    log_ok "Контейнеры запущены"
}

wait_for_healthy() {
    log_step "Ожидание готовности сервисов..."

    local compose_file="docker-compose.yml"
    [[ "$MODE" == "prod" ]] && compose_file="docker-compose.prod.yml"

    local max_wait=180
    local waited=0
    local interval=5

    # Wait for DB
    echo -n "  ⏳ PostgreSQL "
    while [[ $waited -lt $max_wait ]]; do
        if docker compose -f "$compose_file" exec -T db pg_isready -U crm 2>/dev/null | grep -q "accepting"; then
            echo -e " ${GREEN}✓${NC}"
            break
        fi
        echo -n "."
        sleep $interval
        ((waited+=interval))
    done

    # Wait for Redis
    echo -n "  ⏳ Redis "
    waited=0
    while [[ $waited -lt $max_wait ]]; do
        if docker compose -f "$compose_file" exec -T redis redis-cli ping 2>/dev/null | grep -q "PONG"; then
            echo -e " ${GREEN}✓${NC}"
            break
        fi
        echo -n "."
        sleep $interval
        ((waited+=interval))
    done

    # Wait for App
    echo -n "  ⏳ Laravel "
    waited=0
    while [[ $waited -lt $max_wait ]]; do
        if docker compose -f "$compose_file" exec -T app php -v 2>/dev/null | grep -q "PHP"; then
            echo -e " ${GREEN}✓${NC}"
            break
        fi
        echo -n "."
        sleep $interval
        ((waited+=interval))
    done

    sleep 5
    log_ok "Все сервисы готовы"
}

run_migrations() {
    log_step "Миграции и начальные данные..."

    cd "$INSTALL_DIR"

    local compose_file="docker-compose.yml"
    [[ "$MODE" == "prod" ]] && compose_file="docker-compose.prod.yml"

    docker compose -f "$compose_file" exec -T app php artisan migrate --force --no-interaction 2>&1 || {
        log_warn "Миграции уже выполнены или произошла ошибка"
    }

    docker compose -f "$compose_file" exec -T app php artisan db:seed --force --no-interaction 2>&1 || {
        log_info "Сиды уже выполнены"
    }

    docker compose -f "$compose_file" exec -T app php artisan settings:encrypt 2>/dev/null || true
    docker compose -f "$compose_file" exec -T app php artisan config:clear 2>/dev/null || true
    docker compose -f "$compose_file" exec -T app php artisan cache:clear 2>/dev/null || true
    docker compose -f "$compose_file" exec -T app php artisan view:clear 2>/dev/null || true

    log_ok "База данных готова"
}

run_doctor() {
    log_step "Диагностика системы..."
    
    cd "$INSTALL_DIR"

    local compose_file="docker-compose.yml"
    [[ "$MODE" == "prod" ]] && compose_file="docker-compose.prod.yml"

    echo ""
    docker compose -f "$compose_file" exec -T app php artisan jggl:doctor --no-interaction 2>/dev/null || {
        log_warn "Диагностика пропущена"
    }
}

print_success() {
    local SERVER_IP
    SERVER_IP=$(hostname -I 2>/dev/null | awk '{print $1}' || echo "localhost")

    echo ""
    echo -e "${GREEN}╔═══════════════════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║                                                                           ║${NC}"
    echo -e "${GREEN}║   ${BOLD}🎉 JGGL CRM успешно установлена!${NC}${GREEN}                                      ║${NC}"
    echo -e "${GREEN}║                                                                           ║${NC}"
    echo -e "${GREEN}╚═══════════════════════════════════════════════════════════════════════════╝${NC}"
    echo ""
    echo -e "  ${BOLD}🌐 Веб-интерфейс:${NC}"
    echo -e "     • https://${DOMAIN}"
    echo -e "     • http://${SERVER_IP}:${WEB_PORT}"
    echo ""
    echo -e "  ${BOLD}🔐 Админ-панель:${NC} /admin"
    echo -e "  ${BOLD}📧 Логин:${NC}    ${YELLOW}${ADMIN_EMAIL}${NC}"
    echo -e "  ${BOLD}🔑 Пароль:${NC}   ${YELLOW}${ADMIN_PASS}${NC}"
    echo ""
    echo -e "  ${BOLD}📁 Директория:${NC} ${INSTALL_DIR}"
    echo -e "  ${BOLD}🔧 Режим:${NC}     ${MODE}"
    echo ""
    echo -e "${CYAN}─────────────────────────────────────────────────────────────────────────────${NC}"
    echo -e "  ${BOLD}Обновление (production):${NC}"
    echo -e "     cd ${INSTALL_DIR} && ./deploy.sh"
    echo ""
    echo -e "  ${BOLD}Диагностика:${NC}"
    local cf="docker-compose.yml"
    [[ "$MODE" == "prod" ]] && cf="docker-compose.prod.yml"
    echo -e "     docker compose -f ${cf} exec app php artisan jggl:doctor"
    echo -e "${CYAN}─────────────────────────────────────────────────────────────────────────────${NC}"
    echo ""
}

# ─────────────────────────────────────────────────────────────────────────────
# Main
# ─────────────────────────────────────────────────────────────────────────────
main() {
    echo ""
    echo -e "${CYAN}${BOLD}"
    echo "     ╦╔═╗╔═╗╦    ╔═╗╦═╗╔╦╗"
    echo "     ║║ ╦║ ╦║    ║  ╠╦╝║║║"
    echo "    ╚╝╚═╝╚═╝╩═╝  ╚═╝╩╚═╩ ╩"
    echo -e "${NC}"
    echo -e "    ${BOLD}One-Command Installer v2.0${NC}"
    echo -e "    Mode: ${YELLOW}${MODE}${NC}"
    echo ""

    check_root
    check_os
    install_docker
    clone_or_update_repo
    create_env
    start_containers
    wait_for_healthy
    run_migrations
    run_doctor
    print_success
}

main "$@"
