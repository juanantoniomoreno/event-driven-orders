# AGENTS.md — Project Root

## Project Overview

**Event-Driven Orders** — learning project focused on event-driven programming, Docker, and CI/CD.

An order management API where creating an order dispatches async events consumed by specialized workers (notifications, inventory, analytics), with real-time status updates via Mercure.

## Tech Stack

| Layer       | Technology                    |
| ----------- | ----------------------------- |
| Backend     | Symfony 7 + Messenger (Redis) |
| Frontend    | React 18 + Vite               |
| Queue       | Redis Streams                 |
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
                          MessageBus → Redis Streams
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
| `redis`              | 6380 | Message queue (Redis Streams) |
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

- **CD workflow outdated**: `cd.yml` references a single `worker` container and `async` transport — should be updated to the 3-worker architecture
- **No tests**: Phase 6 (PHPUnit, integration, E2E) not started
- **Mercure JWT hardcoded**: `development_secret_key_change_in_production` in docker-compose.yml
- **No HTTPS**: Phase 7 not started
- **Race condition**: All 3 handlers call `markAsProcessed()` independently — no idempotency
- **Code duplication**: Handlers share ~80% identical code
