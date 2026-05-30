# AGENTS.md — Project Root

## Project Overview

**Event-Driven Orders** — learning project focused on event-driven programming, Docker, and CI/CD.

An order management API where creating an order dispatches async events consumed by a worker, with persistent storage in SQLite.

## Tech Stack

| Layer       | Technology              |
| ----------- | ----------------------- |
| Backend     | Symfony 7 + Messenger   |
| Frontend    | React 18 + Vite         |
| Queue       | Redis                   |
| Database    | SQLite (PDO)            |
| Containers  | Docker + Docker Compose |
| CI/CD       | GitHub Actions          |
| Language    | PHP 8.2 / JavaScript    |

## Architecture

```
Client → Nginx (8080) → PHP-FPM → Symfony Controller
                                      ↓
                                 CreateOrderService
                                      ↓
                              OrderRepository (SQLite)
                                      ↓
                          MessageBus → Redis
                                          ↓
                                    Worker (async)
                                          ↓
                              OrderCreatedHandler
```

## Directory Map

| Path                  | Scope                   |
| --------------------- | ----------------------- |
| `backend/`            | Symfony API + worker    |
| `frontend/`           | React SPA               |
| `.github/workflows/`  | CI/CD pipelines         |
| `docker-compose.yml`  | Infrastructure (5 services) |

## Docker Services

| Service    | Port | Purpose                  |
| ---------- | ---- | ------------------------ |
| `nginx`    | 8080 | Reverse proxy to PHP-FPM |
| `php`      | —    | Symfony application      |
| `redis`    | 6380 | Message queue            |
| `worker`   | —    | Consumes `async` transport |
| `frontend` | 3000 | React SPA via Nginx      |

## How to Run

```bash
cd backend && composer install && cd ..
cd frontend && npm install && cd ..
docker compose up -d --build
```

## Known Technical Debt

- **No tests**: PHPUnit, integration, and E2E tests not implemented
- **No HTTPS**: Production-ready TLS not configured
- **Single worker**: All messages processed by one worker (no specialization)
- **SQLite limitations**: Not suitable for production workloads
- **No validation**: Input validation not implemented
- **No error handling**: Custom exception handling missing
