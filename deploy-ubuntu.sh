#!/bin/sh

# CRM Pro - Ubuntu 24.04 Cloud-Init Script
# Хост: crmgojggl

set -e

echo "=== CRM Pro - Установка ==="

# Обновление системы
apt update && apt upgrade -y

# Установка зависимостей
apt install -y ca-certificates curl gnupg lsb-release git

# Установка Docker
install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg
chmod a+r /etc/apt/keyrings/docker.gpg

echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable" | tee /etc/apt/sources.list.d/docker.list > /dev/null

apt update
apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

# Установка docker-compose (standalone)
curl -SL https://github.com/docker/compose/releases/download/v2.24.0/docker-compose-linux-x86_64 -o /usr/local/bin/docker-compose
chmod +x /usr/local/bin/docker-compose

systemctl enable docker
systemctl start docker

# Клонирование репозитория
cd /opt
git clone https://github.com/djbananol44-tech/crm-pro.git
cd crm-pro

# Создание .env
cat > .env << 'EOF'
APP_NAME="CRM Pro"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_TIMEZONE=Europe/Moscow
APP_URL=http://crmgojggl:8000
APP_LOCALE=ru

DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=crm_db
DB_USERNAME=crm_user
DB_PASSWORD=CrmSecurePass2024

CACHE_STORE=database
SESSION_DRIVER=database
SESSION_LIFETIME=120
QUEUE_CONNECTION=sync

REDIS_HOST=redis
REDIS_PORT=6379
EOF

# Запуск контейнеров
/usr/local/bin/docker-compose up -d --build

sleep 50

# Инициализация
/usr/local/bin/docker-compose exec -T app php artisan key:generate --force
/usr/local/bin/docker-compose exec -T app php artisan migrate:fresh --force --seed
/usr/local/bin/docker-compose exec -T app php artisan config:cache
/usr/local/bin/docker-compose exec -T app php artisan view:cache

# Файрвол
ufw allow 8000/tcp

echo "=== Готово! ==="
echo "URL: http://crmgojggl:8000"
echo "Admin: admin@crm.test / admin123"
