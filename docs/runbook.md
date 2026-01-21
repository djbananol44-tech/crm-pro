# 🔧 JGGL CRM — Runbook

Операционные команды для администраторов и DevOps.

---

## 🚀 Deployment

### Первая установка (Ubuntu 22.04/24.04)

```bash
curl -fsSL https://raw.githubusercontent.com/djbananol44-tech/crm-pro/main/install.sh | sudo bash
```

### Обновление production

```bash
cd /opt/jggl-crm
./deploy.sh                    # Latest
./deploy.sh --tag v1.2.3       # Specific version
```

---

## 🏥 Диагностика

### Полная проверка системы

```bash
docker compose -f docker-compose.prod.yml exec app php artisan jggl:doctor
```

### JSON формат (для мониторинга)

```bash
docker compose -f docker-compose.prod.yml exec app php artisan jggl:doctor --json
```

### HTTP Health Check

```bash
curl http://localhost:8080/api/health
```

---

## 📊 Мониторинг сервисов

### Статус контейнеров

```bash
docker compose -f docker-compose.prod.yml ps
```

### Логи (все сервисы)

```bash
docker compose -f docker-compose.prod.yml logs -f
```

### Логи конкретного сервиса

```bash
docker compose -f docker-compose.prod.yml logs -f app      # Laravel
docker compose -f docker-compose.prod.yml logs -f web      # Nginx
docker compose -f docker-compose.prod.yml logs -f queue    # Queue worker
docker compose -f docker-compose.prod.yml logs -f bot      # Telegram bot
docker compose -f docker-compose.prod.yml logs -f scheduler
```

---

## 🔄 Queue Management

### Статус очередей

```bash
docker compose -f docker-compose.prod.yml exec redis redis-cli LLEN queues:default
docker compose -f docker-compose.prod.yml exec redis redis-cli LLEN queues:meta
docker compose -f docker-compose.prod.yml exec redis redis-cli LLEN queues:ai
```

### Перезапуск worker

```bash
docker compose -f docker-compose.prod.yml restart queue
```

### Очистка failed jobs

```bash
docker compose -f docker-compose.prod.yml exec app php artisan queue:flush
```

---

## 🗄️ Database

### Миграции

```bash
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force
```

### Статус миграций

```bash
docker compose -f docker-compose.prod.yml exec app php artisan migrate:status
```

### Backup (через pg_dump)

```bash
docker compose -f docker-compose.prod.yml exec db pg_dump -U crm crm > backup_$(date +%Y%m%d).sql
```

### Restore

```bash
cat backup.sql | docker compose -f docker-compose.prod.yml exec -T db psql -U crm crm
```

---

## 🔍 Поиск и индексация

### Переиндексация лидов

```bash
docker compose -f docker-compose.prod.yml exec app php artisan crm:reindex-leads
```

### Dry run (без изменений)

```bash
docker compose -f docker-compose.prod.yml exec app php artisan crm:reindex-leads --dry-run
```

---

## 🧹 Maintenance

### Очистка кэшей

```bash
docker compose -f docker-compose.prod.yml exec app php artisan optimize:clear
```

### Пересборка кэшей (production)

```bash
docker compose -f docker-compose.prod.yml exec app php artisan config:cache
docker compose -f docker-compose.prod.yml exec app php artisan route:cache
docker compose -f docker-compose.prod.yml exec app php artisan view:cache
```

### Очистка старых Docker images

```bash
docker image prune -a --filter "until=168h"  # Старше 7 дней
```

---

## ⚠️ Troubleshooting

### Container не стартует

```bash
# Проверить логи
docker compose -f docker-compose.prod.yml logs app

# Проверить healthcheck
docker inspect crm_app --format='{{json .State.Health}}'
```

### Database connection refused

```bash
# Проверить что DB контейнер healthy
docker compose -f docker-compose.prod.yml exec db pg_isready

# Проверить .env
grep DB_ .env
```

### Redis connection refused

```bash
docker compose -f docker-compose.prod.yml exec redis redis-cli ping
```

### Permission denied

```bash
docker compose -f docker-compose.prod.yml exec app chown -R www-data:www-data storage bootstrap/cache
```

---

## 🔐 Security

### Шифрование секретов (при первой настройке)

```bash
docker compose -f docker-compose.prod.yml exec app php artisan settings:encrypt
```

### Rotation APP_KEY

⚠️ **ВНИМАНИЕ**: После смены APP_KEY все зашифрованные данные станут нечитаемыми!

```bash
# 1. Backup текущих настроек
docker compose -f docker-compose.prod.yml exec app php artisan tinker --execute="print_r(App\Models\Setting::all()->toArray());"

# 2. Сгенерировать новый ключ
php artisan key:generate --show

# 3. Обновить .env
# 4. Перенастроить все секреты через /admin/settings
```

---

## 🔄 Rollback

### Откат на предыдущую версию

```bash
./deploy.sh --tag <previous-version>
```

### Откат миграций (осторожно!)

```bash
docker compose -f docker-compose.prod.yml exec app php artisan migrate:rollback
```
