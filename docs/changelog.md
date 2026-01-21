# 📝 JGGL CRM — Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [Unreleased]

### Added
- Repository cleanup and optimization
- `.editorconfig` for consistent coding style
- `pint.json` configuration for PHP linter
- `docs/runbook.md` — operational commands
- `docs/architecture.md` — system architecture
- `CLEANUP_REPORT.md` — cleanup documentation

### Changed
- Moved `install-prod.sh` to `docs/legacy/`
- Updated `.gitignore` with comprehensive rules
- Removed hardcoded passwords from docker-compose files

### Removed
- `docker-compose.local.yml` (duplicate functionality)

### Security
- Replaced hardcoded `CrmSecurePass2024` with environment variables
- Added password generation instructions in `docker/env.example`

---

## [1.0.0] - 2026-01-21

### Added
- Full-text search with PostgreSQL FTS + GIN index
- Regression test suite (18 tests)
- Search test suite (11 tests)
- Meta Business Suite URL generation
- Conversation labels support
- Message limit guarantee (20 messages max)
- Gemini AI auto-activation
- Telegram auto-activation with webhook/polling modes
- CI/CD with GitHub Actions
- Docker image publishing to GHCR
- One-command installation script
- `jggl:doctor` diagnostic command
- Health check endpoint `/api/health`
- Encrypted settings storage
- Audit logging for settings changes
- SLA tracking and reporting
- Export to XLSX/CSV

### Changed
- Unified Filament admin theme
- Improved mobile responsiveness

### Security
- Webhook signature verification (Meta & Telegram)
- Idempotency protection (Redis + PostgreSQL)
- Rate limiting (300/min webhooks, 60/min API)
