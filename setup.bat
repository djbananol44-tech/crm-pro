@echo off
chcp 65001 >nul
setlocal enabledelayedexpansion

echo.
echo ╔═══════════════════════════════════════════╗
echo ║           CRM Pro - Установка             ║
echo ╚═══════════════════════════════════════════╝
echo.

:: Check Docker
where docker >nul 2>nul
if %ERRORLEVEL% neq 0 (
    echo ❌ Docker не найден. Установите Docker Desktop: https://docs.docker.com/desktop/windows/
    pause
    exit /b 1
)

:: Create .env if not exists
if not exist .env (
    echo 📝 Создание .env файла...
    if exist .env.example (
        copy .env.example .env >nul
    ) else (
        (
            echo APP_NAME="CRM Pro"
            echo APP_ENV=production
            echo APP_KEY=
            echo APP_DEBUG=false
            echo APP_URL=http://localhost:8000
            echo.
            echo LOG_CHANNEL=stack
            echo LOG_LEVEL=error
            echo.
            echo DB_CONNECTION=pgsql
            echo DB_HOST=db
            echo DB_PORT=5432
            echo DB_DATABASE=crm_db
            echo DB_USERNAME=crm_user
            echo DB_PASSWORD=crm_secret_password
            echo.
            echo CACHE_DRIVER=redis
            echo QUEUE_CONNECTION=redis
            echo SESSION_DRIVER=redis
            echo SESSION_LIFETIME=1440
            echo.
            echo REDIS_HOST=redis
            echo REDIS_PORT=6379
        ) > .env
    )
)

echo 🐳 Запуск Docker контейнеров...
docker-compose down --remove-orphans 2>nul
docker-compose up -d --build

echo ⏳ Ожидание запуска PostgreSQL (20 сек)...
timeout /t 20 /nobreak >nul

echo 📦 Установка зависимостей...
docker-compose exec -T app composer install --no-dev --optimize-autoloader

echo 🔑 Генерация APP_KEY...
docker-compose exec -T app php artisan key:generate --force

echo 🗄️ Миграция базы данных...
docker-compose exec -T app php artisan migrate --force

echo 🌱 Наполнение тестовыми данными...
docker-compose exec -T app php artisan db:seed --force

echo 🔧 Оптимизация...
docker-compose exec -T app php artisan optimize:clear
docker-compose exec -T app php artisan config:cache
docker-compose exec -T app php artisan route:cache
docker-compose exec -T app php artisan view:cache

echo.
echo ╔═══════════════════════════════════════════╗
echo ║       ✅ Установка завершена!             ║
echo ╚═══════════════════════════════════════════╝
echo.
echo 🌐 Приложение:     http://localhost:8000
echo 🔐 Админ-панель:   http://localhost:8000/admin
echo.
echo 📧 Тестовые аккаунты:
echo    Админ:    admin@crm.test / admin123
echo    Менеджер: manager@crm.test / manager123
echo.
echo 💡 После входа настройте API ключи в админке.
echo.
pause
