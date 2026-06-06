# AGENTS.md — Project Root

## Project Overview

**Event-Driven Orders** — learning project focused on event-driven programming, Docker, and CI/CD.

An order management API where creating an order dispatches async events consumed by specialized workers (notifications, inventory, analytics), with real-time status updates via Mercure.

## Tech Stack

| Layer       | Technology                    |
| ----------- | ----------------------------- |
| Backend     | Symfony 7 + Messenger (AMQP)  |
| Frontend    | React 18 + Vite               |
| Queue       | RabbitMQ (AMQP)               |
| Realtime    | Mercure (SSE)                 |
| Database    | PostgreSQL 16 + Doctrine ORM  |
| Containers  | Docker + Docker Compose       |
| CI/CD       | GitHub Actions                |
| Language    | PHP 8.2 / JavaScript (JSX)    |

## Architecture

```
Client → Nginx (8080) → PHP-FPM → Symfony Controller
                                      ↓
                                 CreateOrderService
                                      ↓
                              OrderRepository (Doctrine/PostgreSQL)
                                      ↓
                          MessageBus → RabbitMQ (AMQP)
                                          ↓
                    ┌─────────────────────┼─────────────────────┐
                    ↓                     ↓                     ↓
          worker_notifications   worker_inventory    worker_analytics
                    ↓                     ↓                     ↓
                    └─────────────────────┼─────────────────────┘
                                          ↓
                                    Mercure Hub (3001)
                                          ↓
                                    Frontend (3000, SSE)
```

## Directory Map

| Path                  | Scope                        |
| --------------------- | ---------------------------- |
| `backend/`            | Symfony API + workers        |
| `frontend/`           | React SPA (minimal)          |
| `.github/workflows/`  | CI/CD pipelines              |
| `docker-compose.yml`  | Full infrastructure (8 services) |

## Docker Services

| Service              | Port | Purpose                        |
| -------------------- | ---- | ------------------------------ |
| `nginx`              | 8080 | Reverse proxy to PHP-FPM      |
| `php`                | —    | Symfony application           |
| `postgres`           | 5433 | Persistent storage            |
| `rabbitmq`           | 5673 / 15673 | Message broker (AMQP) + management UI |
| `mercure`            | 3001 | Real-time SSE hub             |
| `frontend`           | 3000 | React SPA via Nginx           |
| `worker_notifications` | —  | Consumes `async_notifications` |
| `worker_inventory`   | —    | Consumes `async_inventory`    |
| `worker_analytics`   | —    | Consumes `async_analytics`    |

## How to Run

```bash
cd backend && composer install && cd ..
cd frontend && npm install && cd ..
docker compose up -d --build
```

## Known Technical Debt

- **Handler code duplication**: The 3 handlers share ~80% identical structure (load order, sleep, markProcessedBy, publish to Mercure). Refactor candidate for Phase 8.2 — **must be done after 8.1** so the idempotency check can be factored into the shared abstraction

## Resolved

- ~~No tests~~ → Phase 6 complete (unit, integration, functional)
- ~~Mercure JWT hardcoded~~ → Phase 7.1 (centralized in root `.env`, referenced via `${MERCURE_JWT_SECRET}`)
- ~~No HTTPS~~ → Phase 7.4 (self-signed cert in `certs/`, nginx on :8443)
- ~~CD workflow outdated~~ → Phase 7.6 (rewritten for 3 workers + RabbitMQ, with `.env` check, `--remove-orphans`, and worker log verification)
- ~~Race condition / no idempotency~~ → Phase 8.1 (Order.`processedBy` JSON column, each handler checks `isProcessedBy()` before doing work, retries are silently skipped)
