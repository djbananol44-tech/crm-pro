# 🚀 CRM Pro

<div align="center">

**AI-Powered CRM для интеграции с Meta Business Suite и Telegram**

![Laravel](https://img.shields.io/badge/Laravel-11-FF2D20?style=flat-square&logo=laravel)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-16-336791?style=flat-square&logo=postgresql)
![Docker](https://img.shields.io/badge/Docker-Ready-2496ED?style=flat-square&logo=docker)

</div>

---

## ⚡ Быстрая установка (Ubuntu)

```bash
# Одна команда для установки
curl -fsSL https://raw.githubusercontent.com/djbananol44-tech/crm-pro/main/install.sh | sudo bash
```

Или вручную:

```bash
# 1. Клонировать
git clone https://github.com/djbananol44-tech/crm-pro.git /opt/crm
cd /opt/crm

# 2. Запустить
chmod +x install.sh
sudo ./install.sh
```

---

## 🔑 Доступ после установки

| Сервис | URL | Логин |
|--------|-----|-------|
| 🌐 CRM | http://IP:8000 | — |
| 🔐 Админка | http://IP:8000/admin | `admin@crm.test` / `admin123` |

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

## ⚙️ Настройка API

После установки войдите в `/admin` → **Настройки** и заполните:

- **Meta Page ID** — ID Facebook страницы
- **Meta Access Token** — токен с правами `pages_messaging`
- **Telegram Bot Token** — от @BotFather
- **Gemini API Key** — для AI анализа (опционально)

---

## 📄 Лицензия

MIT
