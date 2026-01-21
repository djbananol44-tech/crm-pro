#!/bin/bash

# ═══════════════════════════════════════════════════════════════
# 🚀 CRM Pro — Установка на Ubuntu Server
# Использование: curl -fsSL https://raw.githubusercontent.com/djbananol44-tech/crm-pro/main/install.sh | bash
# ═══════════════════════════════════════════════════════════════

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}"
echo "╔═══════════════════════════════════════════════════════════╗"
echo "║           🚀 CRM Pro — Автоустановка                      ║"
echo "║              Ubuntu 22.04 / 24.04                         ║"
echo "╚═══════════════════════════════════════════════════════════╝"
echo -e "${NC}"

# Проверка root
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}❌ Запустите скрипт от root: sudo bash install.sh${NC}"
    exit 1
fi

# ─────────────────────────────────────────────────────────────
# 1. Обновление системы
# ─────────────────────────────────────────────────────────────
echo -e "\n${YELLOW}📦 Шаг 1/7: Обновление системы...${NC}"
apt update -qq
apt upgrade -y -qq
echo -e "${GREEN}✅ Система обновлена${NC}"

# ─────────────────────────────────────────────────────────────
# 2. Установка Docker
# ─────────────────────────────────────────────────────────────
echo -e "\n${YELLOW}🐳 Шаг 2/7: Установка Docker...${NC}"
if command -v docker &> /dev/null; then
    echo -e "${GREEN}✅ Docker уже установлен${NC}"
else
    curl -fsSL https://get.docker.com | sh -s -- --quiet
    systemctl enable docker
    systemctl start docker
    echo -e "${GREEN}✅ Docker установлен${NC}"
fi

# ─────────────────────────────────────────────────────────────
# 3. Установка зависимостей
# ─────────────────────────────────────────────────────────────
echo -e "\n${YELLOW}📚 Шаг 3/7: Установка зависимостей...${NC}"
apt install -y -qq git curl openssl
echo -e "${GREEN}✅ Зависимости установлены${NC}"

# ─────────────────────────────────────────────────────────────
# 4. Клонирование проекта
# ─────────────────────────────────────────────────────────────
echo -e "\n${YELLOW}📥 Шаг 4/7: Клонирование проекта...${NC}"
INSTALL_DIR="/opt/crm"

if [ -d "$INSTALL_DIR" ]; then
    echo -e "${YELLOW}⚠️  Директория существует, обновляем...${NC}"
    cd "$INSTALL_DIR"
    git pull --quiet
else
    git clone --quiet https://github.com/djbananol44-tech/crm-pro.git "$INSTALL_DIR"
    cd "$INSTALL_DIR"
fi
echo -e "${GREEN}✅ Проект загружен в $INSTALL_DIR${NC}"

# ─────────────────────────────────────────────────────────────
# 5. Настройка окружения
# ─────────────────────────────────────────────────────────────
echo -e "\n${YELLOW}⚙️  Шаг 5/7: Настройка окружения...${NC}"

if [ ! -f .env ]; then
    cp docker/env.example .env
    
    # Генерация APP_KEY
    APP_KEY=$(openssl rand -base64 32)
    sed -i "s|APP_KEY=|APP_KEY=base64:${APP_KEY}|" .env
    
    # Определение IP сервера
    SERVER_IP=$(hostname -I | awk '{print $1}')
    sed -i "s|APP_URL=http://localhost:8000|APP_URL=http://${SERVER_IP}:8000|" .env
    
    echo -e "${GREEN}✅ Файл .env создан${NC}"
else
    echo -e "${GREEN}✅ Файл .env уже существует${NC}"
fi

# ─────────────────────────────────────────────────────────────
# 6. Запуск Docker Compose
# ─────────────────────────────────────────────────────────────
echo -e "\n${YELLOW}🐳 Шаг 6/7: Запуск контейнеров...${NC}"

# Остановка старых контейнеров
docker compose down --remove-orphans 2>/dev/null || true

# Запуск
docker compose up -d --build --quiet-pull

echo -e "${GREEN}✅ Контейнеры запущены${NC}"

# Ожидание готовности БД
echo -e "${YELLOW}⏳ Ожидание готовности базы данных...${NC}"
sleep 15

# ─────────────────────────────────────────────────────────────
# 7. Инициализация Laravel
# ─────────────────────────────────────────────────────────────
echo -e "\n${YELLOW}🔧 Шаг 7/7: Инициализация приложения...${NC}"

# Миграции
docker compose exec -T app php artisan migrate --force 2>/dev/null || {
    echo -e "${YELLOW}⏳ Повторная попытка миграций...${NC}"
    sleep 10
    docker compose exec -T app php artisan migrate --force
}

# Сиды
docker compose exec -T app php artisan db:seed --force 2>/dev/null || true

# Оптимизация
docker compose exec -T app php artisan optimize 2>/dev/null || true

echo -e "${GREEN}✅ Приложение инициализировано${NC}"

# ─────────────────────────────────────────────────────────────
# Готово!
# ─────────────────────────────────────────────────────────────
SERVER_IP=$(hostname -I | awk '{print $1}')

echo -e "\n${GREEN}"
echo "╔═══════════════════════════════════════════════════════════╗"
echo "║         🎉 CRM Pro успешно установлена!                   ║"
echo "╚═══════════════════════════════════════════════════════════╝"
echo -e "${NC}"
echo ""
echo -e "${BLUE}🌐 Веб-интерфейс:${NC}    http://${SERVER_IP}:8000"
echo -e "${BLUE}🔐 Админ-панель:${NC}    http://${SERVER_IP}:8000/admin"
echo ""
echo -e "${YELLOW}📧 Логин:${NC}           admin@crm.test"
echo -e "${YELLOW}🔑 Пароль:${NC}          admin123"
echo ""
echo -e "${GREEN}📁 Директория:${NC}      $INSTALL_DIR"
echo ""
echo -e "${YELLOW}💡 Следующие шаги:${NC}"
echo "   1. Войдите в админ-панель"
echo "   2. Настройте API ключи в разделе 'Настройки'"
echo "   3. Готово к работе!"
echo ""
