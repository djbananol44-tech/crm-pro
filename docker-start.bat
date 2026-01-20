@echo off
echo ========================================
echo   CRM System - Docker Setup
echo ========================================
echo.

REM Копируем .env если не существует
if not exist .env (
    echo [1/6] Создание .env файла...
    copy docker\env.example .env
) else (
    echo [1/6] .env файл уже существует
)

REM Запускаем контейнеры
echo [2/6] Запуск Docker контейнеров...
docker-compose up -d --build

REM Ждем готовности PostgreSQL
echo [3/6] Ожидание готовности PostgreSQL...
timeout /t 10 /nobreak > nul

REM Генерируем ключ приложения
echo [4/6] Генерация ключа приложения...
docker-compose exec app php artisan key:generate --force

REM Запускаем миграции и сидеры
echo [5/6] Настройка базы данных...
docker-compose exec app php artisan crm:setup --fresh

REM Создаем символическую ссылку для storage
echo [6/6] Настройка storage...
docker-compose exec app php artisan storage:link

echo.
echo ========================================
echo   CRM System запущена!
echo ========================================
echo.
echo   URL:           http://localhost:8000
echo   Админ-панель:  http://localhost:8000/admin
echo   Vite Dev:      http://localhost:5173
echo.
echo   Данные для входа:
echo   - Админ:    admin@crm.test / admin123
echo   - Менеджер: manager@crm.test / manager123
echo.
echo   Команды:
echo   - docker-compose logs -f      (логи)
echo   - docker-compose down         (остановить)
echo   - docker-compose exec app sh  (консоль)
echo ========================================
pause
