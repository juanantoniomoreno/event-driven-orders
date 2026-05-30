# AGENTS.md — Backend (Symfony 7)

## Scope

Symfony 7 API with event-driven architecture. PHP 8.2, strict types everywhere.

## Architecture

Layered architecture with domain separation:

```
src/
├── Controller/        # HTTP layer — thin, delegates to services
├── Domain/
│   ├── Entity/        # Order entity
│   └── Service/       # Business logic + repository
├── Messaging/
│   ├── Message/       # Immutable message DTOs for Messenger
│   └── Handler/       # Async processors
└── Kernel.php
```

## Conventions

- **Strict types**: Every PHP file starts with `declare(strict_types=1);`
- **Constructor injection**: All dependencies via constructor with PHP 8 promoted properties
- **Namespace**: `App\` maps to `src/`
- **Routing**: YAML-based (`config/routes.yaml`), not annotations/attributes
- **DI config**: `config/services.yaml` — autowire + autoconfigure enabled

## Key Patterns

- **Service layer**: Controllers never touch repositories directly; they call service classes (e.g., `CreateOrderService`)
- **Repository**: Concrete `OrderRepository` class with SQLite/PDO (no interface abstraction)
- **Async messaging**: Single `OrderCreatedMessage` dispatched to `async` transport
- **SQLite persistence**: Database file stored in `var/orders.db`, auto-created on first use

## Database

SQLite with PDO. Schema auto-created in `OrderRepository::init()`:

```sql
CREATE TABLE orders (
    id TEXT PRIMARY KEY,
    customer_email TEXT NOT NULL,
    items TEXT NOT NULL,        -- JSON array
    total REAL NOT NULL,
    status TEXT NOT NULL,
    created_at TEXT NOT NULL
);
```

## Commands

```bash
# Run locally (without Docker)
php -S localhost:8000 -t public

# Consume messages
php bin/console messenger:consume async -vv

# Clear cache
php bin/console cache:clear
```

## Known Technical Debt

- **No repository interface**: `OrderRepository` is concrete, no abstraction for testing
- **No validation**: No Symfony Validator usage — input validation is missing
- **No DTOs**: Controllers work with raw `json_decode` arrays
- **No error handling**: No custom exception handling or API error format
- **No tests**: No `tests/` directory exists
- **Float for money**: `float $total` causes precision issues
- **SQLite in production**: Not suitable for concurrent writes or high load
