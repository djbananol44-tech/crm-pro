#!/bin/bash

# =============================================
#  CRM Pro - Ubuntu 24.04 Deployment Script
#  Хост: crmgojggl
#  Репозиторий: github.com/djbananol44-tech/crm-pro
# =============================================

set -e

# Цвета
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
BOLD='\033[1m'
NC='\033[0m'

echo -e "${BLUE}${BOLD}"
echo "╔══════════════════════════════════════════════════╗"
echo "║     CRM Pro - Развертывание на Ubuntu 24.04     ║"
echo "║              Хост: crmgojggl                     ║"
echo "╚══════════════════════════════════════════════════╝"
echo -e "${NC}"

# === 1. Обновление системы ===
echo -e "${YELLOW}[1/7] Обновление системы...${NC}"
sudo apt update && sudo apt upgrade -y

# === 2. Установка Docker ===
echo -e "${YELLOW}[2/7] Установка Docker...${NC}"
if ! command -v docker &> /dev/null; then
    # Удаляем старые версии
    sudo apt remove -y docker docker-engine docker.io containerd runc 2>/dev/null || true
    
    # Установка зависимостей
    sudo apt install -y ca-certificates curl gnupg lsb-release
    
    # Добавляем GPG ключ Docker
    sudo install -m 0755 -d /etc/apt/keyrings
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg
    sudo chmod a+r /etc/apt/keyrings/docker.gpg
    
    # Добавляем репозиторий
    echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
    
    # Установка Docker
    sudo apt update
    sudo apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
    
    # Добавляем пользователя в группу docker
    sudo usermod -aG docker $USER
    
    echo -e "${GREEN}✓ Docker установлен${NC}"
else
    echo -e "${GREEN}✓ Docker уже установлен${NC}"
fi

# === 3. Установка Git ===
echo -e "${YELLOW}[3/7] Установка Git...${NC}"
if ! command -v git &> /dev/null; then
    sudo apt install -y git
    echo -e "${GREEN}✓ Git установлен${NC}"
else
    echo -e "${GREEN}✓ Git уже установлен${NC}"
fi

# === 4. Клонирование репозитория ===
echo -e "${YELLOW}[4/7] Клонирование репозитория...${NC}"
cd /opt
if [ -d "crm-pro" ]; then
    echo -e "${YELLOW}Папка crm-pro уже существует. Обновляем...${NC}"
    cd crm-pro
    sudo git pull origin main
else
    sudo git clone https://github.com/djbananol44-tech/crm-pro.git
    cd crm-pro
fi
sudo chown -R $USER:$USER /opt/crm-pro

# === 5. Настройка окружения ===
echo -e "${YELLOW}[5/7] Настройка окружения...${NC}"
cd /opt/crm-pro

if [ ! -f .env ]; then
    cat > .env << 'EOF'
APP_NAME="CRM Pro"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=http://crmgojggl:8000

LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=crm_db
DB_USERNAME=crm_user
DB_PASSWORD=CrmSecurePass2024!

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
    echo -e "${GREEN}✓ Файл .env создан${NC}"
fi

# Обновляем docker-compose с паролем
sed -i 's/crm_secret_password/CrmSecurePass2024!/g' docker-compose.yml 2>/dev/null || true

# === 6. Запуск Docker контейнеров ===
echo -e "${YELLOW}[6/7] Запуск Docker контейнеров...${NC}"
sudo docker compose down 2>/dev/null || true
sudo docker compose up -d --build

echo -e "${YELLOW}⏳ Ожидание запуска PostgreSQL (30 сек)...${NC}"
sleep 30

# === 7. Инициализация приложения ===
echo -e "${YELLOW}[7/7] Инициализация приложения...${NC}"

# Генерация APP_KEY
sudo docker compose exec -T app php artisan key:generate --force

# Миграции
sudo docker compose exec -T app php artisan migrate --force

# Сидеры
sudo docker compose exec -T app php artisan db:seed --force

# Оптимизация
sudo docker compose exec -T app php artisan optimize:clear
sudo docker compose exec -T app php artisan config:cache
sudo docker compose exec -T app php artisan route:cache
sudo docker compose exec -T app php artisan view:cache

# === 8. Настройка файрвола ===
echo -e "${YELLOW}Настройка файрвола...${NC}"
sudo ufw allow 8000/tcp 2>/dev/null || true
sudo ufw allow 80/tcp 2>/dev/null || true
sudo ufw allow 443/tcp 2>/dev/null || true

# === Готово! ===
echo ""
echo -e "${GREEN}${BOLD}"
echo "╔══════════════════════════════════════════════════╗"
echo "║         ✅ Развертывание завершено!              ║"
echo "╚══════════════════════════════════════════════════╝"
echo -e "${NC}"
echo ""
echo -e "${BOLD}🌐 Приложение:${NC}     http://crmgojggl:8000"
echo -e "${BOLD}🔐 Админ-панель:${NC}  http://crmgojggl:8000/admin"
echo ""
echo -e "${BOLD}📧 Тестовые аккаунты:${NC}"
echo -e "   ${GREEN}Админ:${NC}    admin@crm.test / admin123"
echo -e "   ${BLUE}Менеджер:${NC} manager@crm.test / manager123"
echo ""
echo -e "${YELLOW}💡 Следующие шаги:${NC}"
echo "   1. Войдите в админку"
echo "   2. Настройте Meta API, Telegram Bot, Gemini AI"
echo "   3. Настройте Webhooks (нужен HTTPS для production)"
echo ""
echo -e "${BOLD}📂 Путь к проекту:${NC} /opt/crm-pro"
echo -e "${BOLD}📋 Логи:${NC}           sudo docker compose logs -f app"
echo ""
