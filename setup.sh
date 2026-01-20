#!/bin/bash

# =============================================
#  CRM Pro - Setup Script
#  Разворачивает систему одной командой
# =============================================

set -e

BOLD='\033[1m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${BLUE}${BOLD}"
echo "╔═══════════════════════════════════════════╗"
echo "║           CRM Pro - Установка             ║"
echo "╚═══════════════════════════════════════════╝"
echo -e "${NC}"

# Check Docker
if ! command -v docker &> /dev/null; then
    echo -e "${RED}❌ Docker не найден. Установите Docker: https://docs.docker.com/get-docker/${NC}"
    exit 1
fi

if ! command -v docker-compose &> /dev/null && ! docker compose version &> /dev/null; then
    echo -e "${RED}❌ Docker Compose не найден.${NC}"
    exit 1
fi

# Create .env if not exists
if [ ! -f .env ]; then
    echo -e "${YELLOW}📝 Создание .env файла...${NC}"
    cp .env.example .env 2>/dev/null || cat > .env << 'EOF'
APP_NAME="CRM Pro"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=http://localhost:8000

LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=crm_db
DB_USERNAME=crm_user
DB_PASSWORD=crm_secret_password

BROADCAST_DRIVER=log
CACHE_DRIVER=redis
FILESYSTEM_DISK=local
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=1440

REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379
EOF
fi

# Generate APP_KEY if empty
if ! grep -q "APP_KEY=base64:" .env; then
    echo -e "${YELLOW}🔑 Генерация APP_KEY...${NC}"
    APP_KEY=$(openssl rand -base64 32)
    if [[ "$OSTYPE" == "darwin"* ]]; then
        sed -i '' "s|APP_KEY=.*|APP_KEY=base64:${APP_KEY}|" .env
    else
        sed -i "s|APP_KEY=.*|APP_KEY=base64:${APP_KEY}|" .env
    fi
fi

echo -e "${BLUE}🐳 Запуск Docker контейнеров...${NC}"

# Use docker compose or docker-compose
if docker compose version &> /dev/null; then
    DOCKER_COMPOSE="docker compose"
else
    DOCKER_COMPOSE="docker-compose"
fi

$DOCKER_COMPOSE down --remove-orphans 2>/dev/null || true
$DOCKER_COMPOSE up -d --build

echo -e "${YELLOW}⏳ Ожидание запуска PostgreSQL (15 сек)...${NC}"
sleep 15

echo -e "${BLUE}📦 Установка зависимостей...${NC}"
$DOCKER_COMPOSE exec -T app composer install --no-dev --optimize-autoloader

echo -e "${BLUE}🗄️ Миграция базы данных...${NC}"
$DOCKER_COMPOSE exec -T app php artisan migrate --force

echo -e "${BLUE}🌱 Наполнение тестовыми данными...${NC}"
$DOCKER_COMPOSE exec -T app php artisan db:seed --force

echo -e "${BLUE}🔧 Оптимизация...${NC}"
$DOCKER_COMPOSE exec -T app php artisan optimize:clear
$DOCKER_COMPOSE exec -T app php artisan config:cache
$DOCKER_COMPOSE exec -T app php artisan route:cache
$DOCKER_COMPOSE exec -T app php artisan view:cache

echo ""
echo -e "${GREEN}${BOLD}"
echo "╔═══════════════════════════════════════════╗"
echo "║       ✅ Установка завершена!             ║"
echo "╚═══════════════════════════════════════════╝"
echo -e "${NC}"
echo ""
echo -e "${BOLD}🌐 Приложение:${NC}     http://localhost:8000"
echo -e "${BOLD}🔐 Админ-панель:${NC}  http://localhost:8000/admin"
echo ""
echo -e "${BOLD}📧 Тестовые аккаунты:${NC}"
echo -e "   ${GREEN}Админ:${NC}    admin@crm.test / admin123"
echo -e "   ${BLUE}Менеджер:${NC} manager@crm.test / manager123"
echo ""
echo -e "${YELLOW}💡 После входа в админку настройте API ключи:${NC}"
echo "   • Meta Business Suite (Page ID, Access Token)"
echo "   • Telegram Bot Token"
echo "   • Gemini AI API Key"
echo ""
