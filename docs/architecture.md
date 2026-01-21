# 🏗️ JGGL CRM — Architecture

## Overview

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              INTERNET                                        │
└─────────────────────────────────────────────────────────────────────────────┘
                                    │
                    ┌───────────────┼───────────────┐
                    ▼               ▼               ▼
            ┌───────────┐   ┌───────────┐   ┌───────────┐
            │   Meta    │   │ Telegram  │   │  Gemini   │
            │ Webhooks  │   │   Bot     │   │    AI     │
            └─────┬─────┘   └─────┬─────┘   └─────┬─────┘
                  │               │               │
                  └───────────────┼───────────────┘
                                  ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                         NGINX (Port 8080)                                    │
│                    Static files + PHP-FPM proxy                              │
└─────────────────────────────────────────────────────────────────────────────┘
                                  │
                                  ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                         LARAVEL APP (PHP-FPM)                                │
│  ┌─────────────┐ ┌─────────────┐ ┌─────────────┐ ┌─────────────┐           │
│  │  Filament   │ │  Inertia    │ │   API       │ │  Webhooks   │           │
│  │   Admin     │ │   React     │ │  Routes     │ │  Handlers   │           │
│  └─────────────┘ └─────────────┘ └─────────────┘ └─────────────┘           │
│                                                                              │
│  ┌─────────────┐ ┌─────────────┐ ┌─────────────┐ ┌─────────────┐           │
│  │  Services   │ │    Jobs     │ │   Models    │ │  Commands   │           │
│  └─────────────┘ └─────────────┘ └─────────────┘ └─────────────┘           │
└─────────────────────────────────────────────────────────────────────────────┘
           │                │                │                │
           ▼                ▼                ▼                ▼
    ┌───────────┐    ┌───────────┐    ┌───────────┐    ┌───────────┐
    │ PostgreSQL│    │   Redis   │    │   Queue   │    │ Scheduler │
    │    DB     │    │   Cache   │    │  Workers  │    │           │
    └───────────┘    └───────────┘    └───────────┘    └───────────┘
```

---

## Services

### MetaApiService

Взаимодействие с Meta Graph API (Messenger, Instagram).

```php
app/Services/MetaApiService.php
```

Ответственность:
- Получение переписок и сообщений
- Отправка сообщений
- Обработка webhooks
- Rate limiting и retry logic

### TelegramService

Интеграция с Telegram Bot API.

```php
app/Services/TelegramService.php
```

Режимы работы:
- **Webhook** — Telegram push (требует HTTPS)
- **Polling** — Long polling (без HTTPS)

### AiAnalysisService

Интеграция с Gemini AI.

```php
app/Services/AiAnalysisService.php
```

Функции:
- Анализ переписок
- Определение интента
- Оценка менеджера
- Graceful degradation при ошибках

---

## Job Queues

| Queue | Purpose | Workers |
|-------|---------|---------|
| `default` | General tasks | queue container |
| `meta` | Meta API operations | queue container |
| `ai` | AI analysis (slow) | queue container |

---

## Data Flow

### Incoming Message (Meta)

```
1. Meta Webhook → POST /api/webhooks/meta
2. Signature verification (X-Hub-Signature-256)
3. Idempotency check (Redis + DB)
4. ProcessMetaMessage job dispatched
5. Contact/Conversation created/updated
6. Deal created/updated
7. GenerateAiAnalysis job dispatched (if enabled)
8. Telegram notification sent
```

### Outgoing Message

```
1. Admin/Manager composes message
2. MetaApiService::sendMessage()
3. Message stored in Deal history
4. Response tracking updated
```

---

## Security Layers

1. **Webhook Signature** — HMAC-SHA256 verification
2. **Idempotency** — Redis SETNX + DB unique index
3. **Rate Limiting** — 300/min webhooks, 60/min API
4. **Encryption** — Secrets encrypted with APP_KEY
5. **Audit Logging** — All settings changes logged

---

## Database Schema

### Core Tables

| Table | Description |
|-------|-------------|
| `users` | Admin & manager accounts |
| `contacts` | Customer contacts (PSID) |
| `conversations` | Meta conversations |
| `deals` | Sales pipeline |
| `settings` | System configuration |
| `webhook_logs` | Webhook idempotency |
| `system_logs` | Application events |

### Search (PostgreSQL FTS)

```sql
-- Full-text search vector on deals
deals.search_vector (tsvector)
  - contact_name (weight A)
  - ai_summary, ai_intent (weight B)
  - deal_comment, last_message_text (weight C)
  - status (weight D)
```

---

## Docker Services

| Service | Container | Image |
|---------|-----------|-------|
| `app` | jggl_app | ghcr.io/.../crm-pro |
| `web` | jggl_web | nginx:alpine |
| `db` | jggl_db | postgres:16-alpine |
| `redis` | jggl_redis | redis:7-alpine |
| `queue` | jggl_queue | ghcr.io/.../crm-pro |
| `bot` | jggl_bot | ghcr.io/.../crm-pro |
| `scheduler` | jggl_scheduler | ghcr.io/.../crm-pro |

---

## Environment Variables

See `docker/env.example` for full list.

### Required

| Variable | Description |
|----------|-------------|
| `APP_KEY` | Encryption key (base64) |
| `DB_PASSWORD` | PostgreSQL password |
| `APP_URL` | Public URL |

### Optional (configured via /admin/settings)

| Variable | Description |
|----------|-------------|
| `META_PAGE_ID` | Facebook Page ID |
| `META_ACCESS_TOKEN` | Graph API token |
| `TELEGRAM_BOT_TOKEN` | Bot token |
| `GEMINI_API_KEY` | AI API key |
