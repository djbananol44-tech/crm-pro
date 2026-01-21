# 🚀 JGGL CRM

<div align="center">

**AI-Powered CRM для интеграции с Meta Business Suite и Telegram**

![Laravel](https://img.shields.io/badge/Laravel-11-FF2D20?style=flat-square&logo=laravel)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-16-336791?style=flat-square&logo=postgresql)
![Docker](https://img.shields.io/badge/Docker-Ready-2496ED?style=flat-square&logo=docker)

</div>

---

## ⚡ Quick Start (Ubuntu 22.04 / 24.04)

```bash
# Production (pull готовых образов из GHCR — быстро!)
curl -fsSL https://raw.githubusercontent.com/.../install.sh | sudo bash

# Development (локальная сборка)
curl -fsSL https://raw.githubusercontent.com/.../install.sh | sudo bash -s -- --dev
```

**После установки:**
- 🌐 Интерфейс: `https://jgglgocrm.org` (или `http://IP:8080`)
- 🔐 Админка: `/admin` → `admin@crm.test` / `admin123`

**Обновление (10 секунд):**
```bash
cd /opt/jggl-crm && ./deploy.sh
```

---

## 🔑 Тестовые аккаунты

После установки автоматически создаются тестовые пользователи:

| Роль | Email | Пароль | Доступ |
|------|-------|--------|--------|
| 👑 **Admin** | `admin@crm.test` | `admin123` | `/admin` — полный доступ |
| 👤 **Manager** | `manager@crm.test` | `manager123` | `/deals` — только свои сделки |

### Восстановление тестовых аккаунтов

```bash
# Если аккаунты были удалены или пароли изменены:
docker compose exec app php artisan db:seed --class=UserSeeder

# Проверить наличие пользователей:
docker compose exec app php artisan tinker --execute="App\Models\User::pluck('email', 'role')"
```

> ⚠️ **Для production**: смените пароли тестовых аккаунтов или удалите их!

## 🌐 URL доступа

| Окружение | URL | Примечание |
|-----------|-----|------------|
| 🔒 Production | https://jgglgocrm.org | С SSL |
| 🧪 Development | http://localhost:8080 | Локально |
| 🔐 Админка | /admin | Filament Panel |

## 🔍 Поиск по лидам

Быстрый полнотекстовый поиск (Postgres FTS + GIN индекс):

```bash
# Переиндексация всех лидов
docker compose exec app php artisan crm:reindex-leads

# Только статистика
docker compose exec app php artisan crm:reindex-leads --dry-run
```

**Индексируемые поля:**
- Имя контакта (вес A — высший приоритет)
- AI summary, intent (вес B)
- Комментарий менеджера, последнее сообщение (вес C)
- PSID, статус (вес D)

**Особенности:**
- Debounce 350ms в UI
- Точный поиск по ID/PSID (распознаёт цифровые запросы)
- Ранжирование по релевантности (`ts_rank`)
- Работает на 10k+ сделок за < 50ms

📖 Подробнее: [docs/search.md](docs/search.md)

## 🏥 Диагностика

```bash
# Полная проверка системы
docker compose -f docker-compose.prod.yml exec app php artisan jggl:doctor

# JSON для мониторинга
docker compose -f docker-compose.prod.yml exec app php artisan jggl:doctor --json

# HTTP Health Check
curl http://localhost:8080/api/health
```

## 🧪 CI (Continuous Integration)

### GitHub Actions Pipeline

При каждом **push** и **pull_request** автоматически запускаются:

| Job | Описание | Время |
|-----|----------|-------|
| 🐘 PHP | Tests + Pint (code style) | ~2 мин |
| 🟨 JS | npm ci + build | ~1 мин |

**Статус:** ![CI](https://github.com/djbananol44-tech/crm-pro/actions/workflows/ci.yml/badge.svg)

### Regression Test Suite

Критические потоки P0/P1 (18 тестов, ~1 мин):

| Группа | Тесты | Описание |
|--------|-------|----------|
| **A) Meta Security** | 3 | Подпись webhook (valid/invalid/missing) |
| **B) Idempotency** | 1 | Дедупликация по message.mid |
| **C) Queue** | 2 | Redis dispatch, правильные очереди |
| **D) Telegram** | 3 | Дедуп update_id, secret_token, claim callback |
| **E) Gemini AI** | 3 | isAvailable, graceful error handling, retry |
| **F) Health** | 3 | DB/Redis/Queue status |

### Search Test Suite

Полнотекстовый поиск по лидам (11 тестов):

| Тест | Описание |
|------|----------|
| search_by_contact_name | Поиск по имени контакта |
| search_by_ai_summary | Поиск по AI анализу |
| search_by_comment | Поиск по комментарию менеджера |
| search_by_last_message_text | Поиск по последнему сообщению |
| exact_search_by_psid | Точный поиск по PSID |
| exact_search_by_id | Точный поиск по ID сделки |
| manager_sees_only_own_deals | Менеджер видит только свои |
| admin_sees_all_deals | Админ видит все |
| filter_unassigned | Фильтр "без менеджера" |
| pagination | Пагинация результатов |
| sort_by_ai_score | Сортировка по AI Score |

### Локальный запуск

```bash
# Все тесты
docker compose exec app ./vendor/bin/phpunit

# Только regression suite
docker compose exec app ./vendor/bin/phpunit --filter=RegressionTest

# Code style check
docker compose exec app ./vendor/bin/pint --test

# Code style fix
docker compose exec app ./vendor/bin/pint

# Frontend build
npm ci && npm run build
```

---

## 🐳 CI/CD & Docker Images

### Триггеры сборки

| Событие | Результат |
|---------|-----------|
| Push в `main` | `:latest` + `:sha-abc1234` |
| Tag `v*` (например `v1.2.3`) | `:v1.2.3` + `:1.2` + `:1` |
| Release | То же что tag |
| Manual dispatch | Кастомный тег |

### Автоматическая сборка (GitHub Actions)

При push в `main` или создании tag `v*`:
1. ✅ CI проходит (tests + Pint + build)
2. 🐳 Собирается Docker image (multi-stage, cached layers)
3. 📦 Устанавливаются Composer зависимости (--no-dev)
4. 🎨 Собирается frontend (Vite production build)
5. 🚀 Image пушится в GitHub Container Registry

```
ghcr.io/<owner>/<repo>:latest          # main branch
ghcr.io/<owner>/<repo>:v1.2.3          # tag/release
ghcr.io/<owner>/<repo>:1.2             # major.minor
ghcr.io/<owner>/<repo>:sha-abc1234     # commit SHA
```

### Быстрый деплой (10 сек)

```bash
# Обновление до последней версии
./deploy.sh

# Обновление до конкретной версии
./deploy.sh --tag v1.2.3

# Обновление до конкретного коммита
./deploy.sh --tag sha-abc1234
```

**Что происходит:**
1. `docker pull` — загрузка готового образа (~30 сек)
2. `docker compose up -d` — перезапуск (~5 сек)
3. Миграции + очистка кэша (~5 сек)

**НЕ выполняется на сервере:**
- ❌ `composer install` — уже в образе
- ❌ `npm install && npm run build` — уже в образе
- ❌ Сборка Docker image — готовый из GHCR

### GitHub Actions Workflows

| Workflow | Файл | Триггер |
|----------|------|---------|
| 🧪 CI | `ci.yml` | push, PR |
| 🐳 Build & Push | `build-push.yml` | main, v* tags |

### Файлы

| Файл | Назначение |
|------|------------|
| `docker-compose.yml` | Development (build locally) |
| `docker-compose.prod.yml` | Production (pull from GHCR) |
| `Dockerfile` | Multi-stage build |
| `deploy.sh` | Quick update script |
| `.github/workflows/ci.yml` | Tests + code style |
| `.github/workflows/build-push.yml` | Docker build + push |

---

## 🔒 SSL Setup (Production)

Для production с HTTPS рекомендуется использовать **reverse proxy** (Nginx, Traefik, Caddy) перед приложением.

### Архитектура

```
    Internet ──► Reverse Proxy (SSL termination) ──► CRM App (:8080)
                         │
                   Let's Encrypt
```

### Варианты настройки

| Вариант | Описание |
|---------|----------|
| **Nginx + Certbot** | Классический вариант |
| **Caddy** | Автоматический SSL |
| **Traefik** | Для Docker-окружений |
| **Cloudflare** | SSL + CDN |

### Проверка SSL

```bash
# Статус сертификата
curl -vI https://your-domain.com 2>&1 | grep -E "subject|expire|issuer"

# Проверка даты истечения
echo | openssl s_client -servername your-domain.com -connect your-domain.com:443 2>/dev/null | \
  openssl x509 -noout -dates
```

### Автозапуск после reboot

```bash
# Проверить статус
sudo systemctl status crm-pro

# Включить автозапуск (уже включён при установке)
sudo systemctl enable crm-pro

# Ручной перезапуск
sudo systemctl restart crm-pro
```

### Rollback (откат на HTTP)

```bash
# Остановить production
docker compose -f docker-compose.prod.yml down

# Запустить development версию
docker compose up -d
```

### Troubleshooting SSL

| Проблема | Решение |
|----------|---------|
| Сертификат не выпускается | Проверьте DNS: `dig +short your-domain.com` должен показать IP сервера |
| Rate limit Let's Encrypt | Подождите 1 час, используйте staging: добавьте `--certificatesresolvers.letsencrypt.acme.caserver=https://acme-staging-v02.api.letsencrypt.org/directory` |
| Mixed content в браузере | Проверьте `APP_URL=https://...` в `.env` |
| 502 Bad Gateway | Проверьте логи app: `docker compose -f docker-compose.prod.yml logs app` |

---

## 📁 Структура проекта

```
crm/
├── app/                    # Laravel приложение
│   ├── Console/Commands/   # Artisan команды
│   ├── Filament/           # Админ-панель
│   ├── Http/Controllers/   # Контроллеры
│   ├── Jobs/               # Очереди
│   ├── Models/             # Eloquent модели
│   └── Services/           # Бизнес-логика
├── docker/                 # Docker конфиги
├── resources/js/           # React компоненты
├── docker-compose.yml      # Production конфиг
├── deploy.sh               # Скрипт развертывания
└── install.sh              # Скрипт установки
```

---

## 🛠 Команды

### Production (HTTPS)

```bash
# Статус
docker compose -f docker-compose.prod.yml ps

# Логи (все сервисы)
docker compose -f docker-compose.prod.yml logs -f

# Логи конкретного сервиса
docker compose -f docker-compose.prod.yml logs -f app      # Laravel
docker compose -f docker-compose.prod.yml logs -f web      # Nginx

# Диагностика
docker compose -f docker-compose.prod.yml exec app php artisan crm:check

# Перезапуск через systemd
sudo systemctl restart crm-pro

# Обновление
cd /opt/crm && git pull
docker compose -f docker-compose.prod.yml up -d --build
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force
docker compose -f docker-compose.prod.yml exec app php artisan optimize:clear
```

### Development (HTTP)

```bash
# Статус
docker compose ps

# Логи
docker compose logs -f

# Диагностика
docker compose exec app php artisan crm:check

# Перезапуск
docker compose restart

# Обновление
git pull && docker compose up -d --build
```

---

## 📨 Очереди (Redis)

Проект использует Redis для асинхронной обработки задач (Meta webhooks, AI-анализ, Telegram уведомления).

### Проверка работы очередей

```bash
# 1. Убедитесь, что Redis работает
docker compose exec redis redis-cli ping
# Ожидаемый ответ: PONG

# 2. Проверьте конфигурацию Laravel
docker compose exec app php artisan tinker --execute="echo config('queue.default');"
# Ожидаемый ответ: redis

# 3. Проверьте, что queue_worker запущен
docker compose ps | grep queue
# Должен быть статус: Up

# 4. Просмотр логов воркера
docker compose logs -f queue_worker

# 5. Проверка очереди (количество jobs)
docker compose exec redis redis-cli LLEN queues:default
docker compose exec redis redis-cli LLEN queues:meta
docker compose exec redis redis-cli LLEN queues:ai
```

### Ручной запуск воркера (для отладки)

```bash
docker compose exec app php artisan queue:work redis --verbose
```

### Мониторинг очереди

```bash
# Просмотр всех ключей очередей
docker compose exec redis redis-cli KEYS "queues:*"

# Статус обработки
docker compose exec app php artisan queue:monitor redis:default,redis:meta,redis:ai
```

---

## 🔐 Безопасное хранение секретов

Все чувствительные данные (API ключи, токены) хранятся в зашифрованном виде.

### Шифрование

| Ключ | Шифруется | Алгоритм |
|------|-----------|----------|
| `meta_access_token` | ✅ | AES-256-CBC |
| `meta_app_secret` | ✅ | AES-256-CBC |
| `meta_webhook_verify_token` | ✅ | AES-256-CBC |
| `telegram_bot_token` | ✅ | AES-256-CBC |
| `gemini_api_key` | ✅ | AES-256-CBC |

Шифрование использует Laravel `Crypt` с `APP_KEY`.

### Masked отображение

В админ-панели секретные поля отображаются как `••••••••`. 
- Чтобы изменить — введите новое значение
- Чтобы сохранить текущее — оставьте поле пустым
- Чтобы удалить — очистите поле и сохраните

### Аудит изменений

Все изменения настроек логируются:
- Кто изменил (user_id)
- Когда (timestamp)
- Какой ключ (setting_key)
- Тип изменения (created/updated/deleted)

**Значения секретов НЕ логируются** — только факт изменения.

Просмотр журнала: `/admin/setting-audit-logs`

### Миграция существующих данных

```bash
# Зашифровать уже сохранённые секреты
docker compose exec app php artisan settings:encrypt

# Проверить без изменений
docker compose exec app php artisan settings:encrypt --dry-run
```

### ⚠️ Важно

1. **APP_KEY** — единственный ключ для расшифровки. Сохраните его в безопасном месте!
2. При потере APP_KEY все зашифрованные данные будут недоступны
3. При смене APP_KEY нужно перенастроить все секреты

---

## 🔐 Безопасность Webhook

### Rate Limiting

| Endpoint | Лимит | Назначение |
|----------|-------|------------|
| `/api/webhooks/*` | 300/min | Meta bursts (много событий сразу) |
| `/api/test/*` | 10/min | Защита тестовых endpoints |
| `/api/*` (остальные) | 60/min | Стандартный API |

При превышении лимита возвращается **HTTP 429** с `Retry-After` header.

### Idempotency (Защита от дублей)

Webhook обработка идемпотентна — повторные события игнорируются:

| Слой | Механизм | TTL |
|------|----------|-----|
| Redis | `SETNX` по event_key | 24 часа |
| PostgreSQL | `UNIQUE INDEX (source, event_key)` | навсегда |

**Event Key** формируется из:
- `message.mid` — уникальный Message ID от Meta (приоритет)
- `sha256(entry_id + sender_id + timestamp + message_hash)` — fallback

```bash
# Проверка: повторный запрос возвращает DUPLICATE_IGNORED
curl -X POST http://localhost:8000/api/webhooks/meta \
  -H "Content-Type: application/json" \
  -H "X-Hub-Signature-256: sha256=..." \
  -d '{"object":"page","entry":[...]}'
# Первый: EVENT_RECEIVED
# Повторный: DUPLICATE_IGNORED
```

### Signature Verification

Meta webhook защищён верификацией подписи `X-Hub-Signature-256`:

1. **Настройте App Secret** в `/admin` → Настройки → Meta Business Suite → App Secret
2. App Secret берётся из настроек приложения в [Meta Developers Console](https://developers.facebook.com/apps/)
3. Все запросы без валидной подписи отклоняются с HTTP 403

### Проверка работы

```bash
# Тест с невалидной подписью (должен вернуть 403)
curl -X POST http://localhost:8000/api/webhooks/meta \
  -H "Content-Type: application/json" \
  -H "X-Hub-Signature-256: sha256=invalid" \
  -d '{"object":"page","entry":[]}' \
  -w "\nHTTP Status: %{http_code}\n"

# Логи отклонённых запросов
docker compose exec app grep "signature" storage/logs/laravel.log
```

### Запуск тестов

```bash
docker compose exec app php artisan test --filter=MetaWebhookSignatureTest
```

---

## ⚙️ Настройка API

После установки войдите в `/admin` → **Настройки** и заполните:

- **Meta Page ID** — ID Facebook страницы
- **Meta Access Token** — токен с правами `pages_messaging`
- **Meta App Secret** — секрет приложения для верификации webhook ⚠️
- **Telegram Bot Token** — от @BotFather
- **Telegram Mode** — режим работы бота (webhook/polling)
- **Gemini API Key** — для AI анализа (опционально)

---

## 🤖 Telegram Bot

Бот поддерживает два режима работы:

| Режим | Описание | Требования |
|-------|----------|------------|
| **Webhook** | Telegram отправляет обновления на `/api/webhooks/telegram` | HTTPS обязателен |
| **Polling** | `bot_worker` опрашивает Telegram API | Работает без HTTPS |

### Настройка режима

```bash
# Проверить текущий статус
docker compose exec app php artisan telegram:setup --status

# Переключить на webhook (требует HTTPS)
docker compose exec app php artisan telegram:setup --mode=webhook

# Переключить на polling
docker compose exec app php artisan telegram:setup --mode=polling
```

### Webhook режим (рекомендуется для production)

```bash
# Установить webhook
docker compose exec app php artisan telegram:setup --mode=webhook

# bot_worker автоматически перейдёт в режим сна
# Проверить:
docker compose logs bot_worker
```

### Polling режим (для разработки без HTTPS)

```bash
# Настроить polling
docker compose exec app php artisan telegram:setup --mode=polling

# bot_worker автоматически запустит long polling
# Проверить:
docker compose logs -f bot_worker
```

### Устойчивость к restart

- **Offset хранится в Redis** — при restart worker продолжает с последнего обработанного update
- **Идемпотентность** — повторные update игнорируются (защита от дублей)
- **Graceful shutdown** — при SIGTERM offset сохраняется

```bash
# Проверить сохранённый offset
docker compose exec redis redis-cli GET telegram:polling:offset

# Сбросить offset (если нужно)
docker compose exec redis redis-cli DEL telegram:polling:offset
```

---

## 🛠️ Development

### Quick Start (3 команды)

```bash
# 1. Клонировать
git clone https://github.com/djbananol44-tech/crm-pro.git && cd crm-pro

# 2. Отредактировать .env (установить DB_PASSWORD!)
cp docker/env.example .env && nano .env

# 3. Установить всё
make install
```

**Готово!** → http://localhost:8080/admin (admin@crm.test / admin123)

### Developer Commands

Проект поддерживает **Makefile** (Linux/macOS) и **PowerShell** (Windows):

```bash
# Linux / macOS
make help           # Показать все команды
make up             # Поднять контейнеры
make test           # Запустить тесты
make lint           # Проверить code style
make reset          # Быстрый reset окружения

# Windows PowerShell
.\scripts\dev.ps1 help
.\scripts\dev.ps1 up
.\scripts\dev.ps1 test
```

### Основные команды

| Команда | Описание |
|---------|----------|
| `make up` | Поднять контейнеры |
| `make down` | Остановить контейнеры |
| `make test` | Запустить тесты |
| `make lint` | Проверить code style |
| `make lint-fix` | Исправить code style |
| `make build` | Собрать frontend |
| `make doctor` | Диагностика системы |
| `make reset` | Очистить кэши + миграции + сиды |
| `make fresh` | DROP ALL + миграции + сиды ⚠️ |
| `make shell` | Bash в контейнере |
| `make check` | lint + test (CI) |

### Code Style

Проект использует:
- **PHP**: [Laravel Pint](https://laravel.com/docs/pint) (PSR-12 + Laravel preset)
- **EditorConfig**: `.editorconfig` для единых настроек

```bash
# Проверить стиль
make lint

# Автоисправление
make lint-fix
```

---

## 📚 Документация

| Документ | Описание |
|----------|----------|
| [docs/architecture.md](docs/architecture.md) | Архитектура системы |
| [docs/runbook.md](docs/runbook.md) | Операционные команды |
| [docs/search.md](docs/search.md) | Полнотекстовый поиск |
| [docs/changelog.md](docs/changelog.md) | История изменений |

---

## 📄 Лицензия

MIT
