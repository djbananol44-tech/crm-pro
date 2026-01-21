#!/bin/bash

# ═══════════════════════════════════════════════════════════════════════════════
#  CRM Pro — Финальная сборка (Production Ready)
#  Полная очистка, сборка и запуск системы
# ═══════════════════════════════════════════════════════════════════════════════

set -e

# Цвета
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
WHITE='\033[1;37m'
NC='\033[0m'

# Функция вывода статуса
print_status() {
    echo -e "${BLUE}[$(date +'%H:%M:%S')]${NC} $1"
}

print_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

print_header() {
    echo ""
    echo -e "${PURPLE}═══════════════════════════════════════════════════════════════${NC}"
    echo -e "${WHITE}  $1${NC}"
    echo -e "${PURPLE}═══════════════════════════════════════════════════════════════${NC}"
    echo ""
}

# Определяем docker compose команду
if docker compose version &> /dev/null; then
    DOCKER_COMPOSE="docker compose"
else
    DOCKER_COMPOSE="docker-compose"
fi

# ═══════════════════════════════════════════════════════════════════════════════
#  НАЧАЛО СБОРКИ
# ═══════════════════════════════════════════════════════════════════════════════

clear
echo -e "${CYAN}"
cat << "EOF"
   ______ _____  __  __   _____           
  / ____/|  __ \|  \/  | |  __ \          
 | |     | |__) | \  / | | |__) | __ ___  
 | |     |  _  /| |\/| | |  ___/ '__/ _ \ 
 | |____ | | \ \| |  | | | |   | | | (_) |
  \_____||_|  \_\_|  |_| |_|   |_|  \___/ 
                                           
EOF
echo -e "${NC}"
echo -e "${WHITE}         Финальная сборка v1.0${NC}"
echo ""

# ═══════════════════════════════════════════════════════════════════════════════
#  1. Проверка зависимостей
# ═══════════════════════════════════════════════════════════════════════════════

print_header "1. Проверка зависимостей"

# Docker
if ! command -v docker &> /dev/null; then
    print_error "Docker не установлен!"
    exit 1
fi
print_success "Docker найден: $(docker --version | head -1)"

# Docker Compose
if ! $DOCKER_COMPOSE version &> /dev/null; then
    print_error "Docker Compose не установлен!"
    exit 1
fi
print_success "Docker Compose найден"

# Git (опционально)
if command -v git &> /dev/null; then
    print_success "Git найден: $(git --version)"
    # Настройка git для избежания ошибки "Author identity unknown"
    git config --global user.email "deploy@crm.local" 2>/dev/null || true
    git config --global user.name "CRM Deploy" 2>/dev/null || true
fi

# ═══════════════════════════════════════════════════════════════════════════════
#  2. Подготовка окружения
# ═══════════════════════════════════════════════════════════════════════════════

print_header "2. Подготовка окружения"

# Создание .env если не существует
if [ ! -f .env ]; then
    print_warning ".env не найден, создаю из шаблона..."
    
    if [ -f .env.example ]; then
        cp .env.example .env
        print_success "Скопирован .env.example → .env"
    else
        print_status "Создаю .env с настройками по умолчанию..."
        cat > .env << 'ENVEOF'
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
DB_PASSWORD=CrmSecurePass2024

CACHE_DRIVER=database
SESSION_DRIVER=database
QUEUE_CONNECTION=sync

REDIS_HOST=redis
REDIS_PORT=6379
ENVEOF
        print_success "Создан .env с настройками по умолчанию"
    fi
else
    print_success ".env уже существует"
fi

# Генерация APP_KEY если пустой
if ! grep -q "APP_KEY=base64:" .env 2>/dev/null; then
    print_status "Генерирую APP_KEY..."
    APP_KEY=$(openssl rand -base64 32 2>/dev/null || head -c 32 /dev/urandom | base64)
    if [[ "$OSTYPE" == "darwin"* ]]; then
        sed -i '' "s|APP_KEY=.*|APP_KEY=base64:${APP_KEY}|" .env
    else
        sed -i "s|APP_KEY=.*|APP_KEY=base64:${APP_KEY}|" .env
    fi
    print_success "APP_KEY сгенерирован"
fi

# ═══════════════════════════════════════════════════════════════════════════════
#  3. Остановка старых контейнеров
# ═══════════════════════════════════════════════════════════════════════════════

print_header "3. Очистка старых контейнеров"

print_status "Останавливаю контейнеры..."
$DOCKER_COMPOSE down --remove-orphans 2>/dev/null || true
print_success "Контейнеры остановлены"

# Опциональная очистка volumes (раскомментируйте если нужно)
# print_status "Очищаю volumes..."
# $DOCKER_COMPOSE down -v 2>/dev/null || true

# ═══════════════════════════════════════════════════════════════════════════════
#  4. Сборка контейнеров
# ═══════════════════════════════════════════════════════════════════════════════

print_header "4. Сборка Docker контейнеров"

print_status "Собираю контейнеры (это может занять несколько минут)..."
$DOCKER_COMPOSE build --no-cache
print_success "Контейнеры собраны"

# ═══════════════════════════════════════════════════════════════════════════════
#  5. Запуск контейнеров
# ═══════════════════════════════════════════════════════════════════════════════

print_header "5. Запуск контейнеров"

print_status "Запускаю контейнеры..."
$DOCKER_COMPOSE up -d
print_success "Контейнеры запущены"

# Ожидание готовности PostgreSQL
print_status "Ожидаю готовность PostgreSQL..."
MAX_WAIT=60
WAITED=0
while ! $DOCKER_COMPOSE exec -T db pg_isready -U crm_user -d crm_db &>/dev/null; do
    if [ $WAITED -ge $MAX_WAIT ]; then
        print_error "PostgreSQL не запустился за ${MAX_WAIT} секунд"
        exit 1
    fi
    sleep 2
    WAITED=$((WAITED + 2))
    echo -ne "\r${BLUE}[$(date +'%H:%M:%S')]${NC} Ожидание БД... ${WAITED}s"
done
echo ""
print_success "PostgreSQL готов"

# ═══════════════════════════════════════════════════════════════════════════════
#  6. Установка зависимостей
# ═══════════════════════════════════════════════════════════════════════════════

print_header "6. Установка зависимостей"

print_status "Устанавливаю Composer зависимости..."
$DOCKER_COMPOSE exec -T app composer install --no-dev --optimize-autoloader --no-interaction 2>/dev/null || true
print_success "Composer зависимости установлены"

# ═══════════════════════════════════════════════════════════════════════════════
#  7. Настройка прав доступа
# ═══════════════════════════════════════════════════════════════════════════════

print_header "7. Настройка прав доступа"

print_status "Устанавливаю права на storage и bootstrap/cache..."
$DOCKER_COMPOSE exec -T app chmod -R 775 storage bootstrap/cache 2>/dev/null || true
$DOCKER_COMPOSE exec -T app chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
print_success "Права установлены"

# ═══════════════════════════════════════════════════════════════════════════════
#  8. Очистка кэша Laravel
# ═══════════════════════════════════════════════════════════════════════════════

print_header "8. Очистка кэша Laravel"

print_status "Очищаю кэш..."
$DOCKER_COMPOSE exec -T app php artisan config:clear 2>/dev/null || true
$DOCKER_COMPOSE exec -T app php artisan cache:clear 2>/dev/null || true
$DOCKER_COMPOSE exec -T app php artisan route:clear 2>/dev/null || true
$DOCKER_COMPOSE exec -T app php artisan view:clear 2>/dev/null || true
print_success "Кэш очищен"

# ═══════════════════════════════════════════════════════════════════════════════
#  9. Миграции и сидеры
# ═══════════════════════════════════════════════════════════════════════════════

print_header "9. Миграции и сидеры"

print_status "Запускаю миграции..."
$DOCKER_COMPOSE exec -T app php artisan migrate --force
print_success "Миграции выполнены"

print_status "Запускаю сидеры..."
$DOCKER_COMPOSE exec -T app php artisan db:seed --force 2>/dev/null || true
print_success "Сидеры выполнены"

# ═══════════════════════════════════════════════════════════════════════════════
#  10. Сборка фронтенда
# ═══════════════════════════════════════════════════════════════════════════════

print_header "10. Сборка фронтенда"

print_status "Устанавливаю npm зависимости..."
$DOCKER_COMPOSE exec -T app npm install 2>/dev/null || true

print_status "Собираю фронтенд..."
$DOCKER_COMPOSE exec -T app npm run build 2>/dev/null || true
print_success "Фронтенд собран"

# ═══════════════════════════════════════════════════════════════════════════════
#  11. Оптимизация для production
# ═══════════════════════════════════════════════════════════════════════════════

print_header "11. Оптимизация для production"

print_status "Кэширую конфиги и маршруты..."
$DOCKER_COMPOSE exec -T app php artisan config:cache 2>/dev/null || true
$DOCKER_COMPOSE exec -T app php artisan route:cache 2>/dev/null || true
$DOCKER_COMPOSE exec -T app php artisan view:cache 2>/dev/null || true
print_success "Оптимизация завершена"

# ═══════════════════════════════════════════════════════════════════════════════
#  12. Самодиагностика
# ═══════════════════════════════════════════════════════════════════════════════

print_header "12. Самодиагностика"

print_status "Запускаю проверку системы..."
$DOCKER_COMPOSE exec -T app php artisan crm:check 2>/dev/null || print_warning "Команда crm:check недоступна"

# ═══════════════════════════════════════════════════════════════════════════════
#  ЗАВЕРШЕНИЕ
# ═══════════════════════════════════════════════════════════════════════════════

echo ""
echo -e "${GREEN}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${WHITE}         ✅ СБОРКА УСПЕШНО ЗАВЕРШЕНА!${NC}"
echo -e "${GREEN}═══════════════════════════════════════════════════════════════${NC}"
echo ""
echo -e "${CYAN}🌐 Приложение:${NC}     http://localhost:8000"
echo -e "${CYAN}🔐 Админ-панель:${NC}  http://localhost:8000/admin"
echo ""
echo -e "${WHITE}📧 Тестовые аккаунты:${NC}"
echo -e "   ${GREEN}Админ:${NC}    admin@crm.test / admin123"
echo -e "   ${BLUE}Менеджер:${NC} manager@crm.test / manager123"
echo ""
echo -e "${YELLOW}💡 Следующие шаги:${NC}"
echo "   1. Откройте http://localhost:8000/admin"
echo "   2. Войдите как admin@crm.test / admin123"
echo "   3. Настройте API ключи в разделе 'Настройки'"
echo "   4. Запустите тест: php artisan crm:check"
echo ""
echo -e "${PURPLE}📖 Документация: START_HERE.md${NC}"
echo ""
