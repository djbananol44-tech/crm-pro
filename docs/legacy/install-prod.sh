#!/bin/bash

# ═══════════════════════════════════════════════════════════════
# 🚀 JGGL CRM — Production Installation Script (HTTPS)
# ═══════════════════════════════════════════════════════════════
#
# Использование:
#   chmod +x install-prod.sh
#   sudo ./install-prod.sh your-domain.com admin@domain.com
#
# ═══════════════════════════════════════════════════════════════

set -e

# Цвета для вывода
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

print_header() {
    echo -e "\n${BLUE}═══════════════════════════════════════════════════════════${NC}"
    echo -e "${BLUE}  $1${NC}"
    echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}\n"
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

# ─────────────────────────────────────────────────────────────────
# Проверка аргументов
# ─────────────────────────────────────────────────────────────────
DOMAIN=${1:-}
ACME_EMAIL=${2:-}
INSTALL_DIR=${3:-/opt/crm}

if [ -z "$DOMAIN" ]; then
    print_error "Укажите домен: sudo ./install-prod.sh your-domain.com admin@domain.com"
    exit 1
fi

if [ -z "$ACME_EMAIL" ]; then
    print_warning "Email для Let's Encrypt не указан, используется admin@${DOMAIN}"
    ACME_EMAIL="admin@${DOMAIN}"
fi

print_header "🚀 JGGL CRM — Production Installation"
echo "Домен: ${DOMAIN}"
echo "Email: ${ACME_EMAIL}"
echo "Директория: ${INSTALL_DIR}"

# ─────────────────────────────────────────────────────────────────
# 1. Проверка DNS
# ─────────────────────────────────────────────────────────────────
print_header "1️⃣ Проверка DNS"

SERVER_IP=$(curl -s ifconfig.me || curl -s icanhazip.com || echo "unknown")
DNS_IP=$(dig +short ${DOMAIN} 2>/dev/null | head -1 || echo "")

echo "IP сервера: ${SERVER_IP}"
echo "DNS запись: ${DNS_IP}"

if [ "$SERVER_IP" != "$DNS_IP" ]; then
    print_warning "DNS не настроен или не распространился!"
    print_warning "Убедитесь, что A-запись ${DOMAIN} указывает на ${SERVER_IP}"
    read -p "Продолжить всё равно? (y/N) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
else
    print_success "DNS настроен корректно"
fi

# ─────────────────────────────────────────────────────────────────
# 2. Установка Docker
# ─────────────────────────────────────────────────────────────────
print_header "2️⃣ Проверка Docker"

if ! command -v docker &> /dev/null; then
    echo "Установка Docker..."
    curl -fsSL https://get.docker.com | sh
    systemctl enable docker
    systemctl start docker
    print_success "Docker установлен"
else
    print_success "Docker уже установлен: $(docker --version)"
fi

# ─────────────────────────────────────────────────────────────────
# 3. Клонирование/обновление репозитория
# ─────────────────────────────────────────────────────────────────
print_header "3️⃣ Подготовка проекта"

mkdir -p ${INSTALL_DIR}
cd ${INSTALL_DIR}

if [ -d ".git" ]; then
    echo "Обновление репозитория..."
    git fetch --all
    git reset --hard origin/main
    print_success "Репозиторий обновлён"
else
    echo "Клонирование репозитория..."
    git clone https://github.com/djbananol44-tech/crm-pro.git .
    print_success "Репозиторий склонирован"
fi

# ─────────────────────────────────────────────────────────────────
# 4. Создание .env
# ─────────────────────────────────────────────────────────────────
print_header "4️⃣ Настройка окружения"

if [ ! -f ".env" ]; then
    cp docker/env.example .env
    
    # Генерация APP_KEY
    APP_KEY=$(openssl rand -base64 32)
    sed -i "s|APP_KEY=|APP_KEY=base64:${APP_KEY}|" .env
    
    # Установка домена
    sed -i "s|APP_URL=.*|APP_URL=https://${DOMAIN}|" .env
    sed -i "s|DOMAIN=.*|DOMAIN=${DOMAIN}|" .env
    sed -i "s|ACME_EMAIL=.*|ACME_EMAIL=${ACME_EMAIL}|" .env
    
    # Генерация безопасного пароля БД
    DB_PASS=$(openssl rand -base64 16 | tr -dc 'a-zA-Z0-9' | head -c 20)
    sed -i "s|DB_PASSWORD=.*|DB_PASSWORD=${DB_PASS}|" .env
    
    print_success ".env создан с безопасными настройками"
else
    # Обновляем домен в существующем .env
    sed -i "s|DOMAIN=.*|DOMAIN=${DOMAIN}|" .env
    sed -i "s|ACME_EMAIL=.*|ACME_EMAIL=${ACME_EMAIL}|" .env
    sed -i "s|APP_URL=.*|APP_URL=https://${DOMAIN}|" .env
    print_success ".env обновлён"
fi

# ─────────────────────────────────────────────────────────────────
# 5. Остановка старых контейнеров
# ─────────────────────────────────────────────────────────────────
print_header "5️⃣ Остановка старых контейнеров"

docker compose -f docker-compose.prod.yml down 2>/dev/null || true
docker compose down 2>/dev/null || true

# Освобождаем порты 80/443
pkill -f "nginx" 2>/dev/null || true
systemctl stop nginx 2>/dev/null || true
systemctl stop apache2 2>/dev/null || true

print_success "Порты 80/443 освобождены"

# ─────────────────────────────────────────────────────────────────
# 6. Запуск Docker Compose
# ─────────────────────────────────────────────────────────────────
print_header "6️⃣ Запуск контейнеров"

docker compose -f docker-compose.prod.yml up -d --build

echo "Ожидание запуска сервисов (60 секунд)..."
sleep 60

print_success "Контейнеры запущены"

# ─────────────────────────────────────────────────────────────────
# 7. Миграции и сиды
# ─────────────────────────────────────────────────────────────────
print_header "7️⃣ Настройка базы данных"

docker compose -f docker-compose.prod.yml exec -T app php artisan migrate --force
docker compose -f docker-compose.prod.yml exec -T app php artisan db:seed --force
docker compose -f docker-compose.prod.yml exec -T app php artisan optimize:clear
docker compose -f docker-compose.prod.yml exec -T app php artisan config:cache
docker compose -f docker-compose.prod.yml exec -T app php artisan route:cache
docker compose -f docker-compose.prod.yml exec -T app php artisan view:cache

print_success "База данных настроена"

# ─────────────────────────────────────────────────────────────────
# 8. Установка systemd service
# ─────────────────────────────────────────────────────────────────
print_header "8️⃣ Настройка автозапуска"

# Обновляем путь в service файле
sed -i "s|WorkingDirectory=.*|WorkingDirectory=${INSTALL_DIR}|" docker/systemd/crm-pro.service

cp docker/systemd/crm-pro.service /etc/systemd/system/
systemctl daemon-reload
systemctl enable crm-pro

print_success "Systemd service установлен"

# ─────────────────────────────────────────────────────────────────
# 9. Проверка SSL
# ─────────────────────────────────────────────────────────────────
print_header "9️⃣ Проверка SSL"

echo "Ожидание выпуска сертификата (до 60 секунд)..."
sleep 30

# Проверяем HTTPS
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 "https://${DOMAIN}" 2>/dev/null || echo "000")

if [ "$HTTP_CODE" = "200" ] || [ "$HTTP_CODE" = "302" ]; then
    print_success "HTTPS работает! (HTTP ${HTTP_CODE})"
else
    print_warning "HTTPS пока не доступен (HTTP ${HTTP_CODE})"
    print_warning "Сертификат может выпускаться до 5 минут"
    echo "Проверьте логи: docker compose -f docker-compose.prod.yml logs traefik"
fi

# ─────────────────────────────────────────────────────────────────
# 10. Финальная информация
# ─────────────────────────────────────────────────────────────────
print_header "✅ Установка завершена!"

echo -e "${GREEN}═══════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}  🌐 JGGL CRM доступен по адресу:${NC}"
echo -e "${GREEN}     https://${DOMAIN}${NC}"
echo -e "${GREEN}═══════════════════════════════════════════════════════════${NC}"
echo ""
echo -e "${YELLOW}📧 Логин администратора: admin@crm.test${NC}"
echo -e "${YELLOW}🔑 Пароль администратора: admin123${NC}"
echo ""
echo -e "${YELLOW}📧 Логин менеджера: manager@crm.test${NC}"
echo -e "${YELLOW}🔑 Пароль менеджера: manager123${NC}"
echo ""
echo -e "${BLUE}Полезные команды:${NC}"
echo "  Статус:     docker compose -f docker-compose.prod.yml ps"
echo "  Логи:       docker compose -f docker-compose.prod.yml logs -f"
echo "  Логи SSL:   docker compose -f docker-compose.prod.yml logs traefik"
echo "  Перезапуск: systemctl restart crm-pro"
echo "  Диагностика: docker compose -f docker-compose.prod.yml exec app php artisan crm:check"
echo ""
echo -e "${BLUE}SSL сертификат обновляется автоматически через Traefik.${NC}"
echo -e "${BLUE}Проверка: docker compose -f docker-compose.prod.yml exec traefik cat /letsencrypt/acme.json | jq '.letsencrypt.Certificates[0].domain'${NC}"
