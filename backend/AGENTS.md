# AGENTS.md — Backend (Symfony 7)

## Scope

Symfony 7 API with event-driven architecture. PHP 8.2, strict types everywhere.

## Architecture

Layered architecture with domain separation:

```
src/
├── Controller/        # HTTP layer — thin, delegates to services
├── Domain/
│   ├── Entity/        # Doctrine entities (Order)
│   └── Service/       # Business logic + repository interface/implementations
├── Messaging/
│   ├── Message/       # Immutable message DTOs for Messenger
│   └── Handler/       # Async processors (one per transport)
├── DataFixtures/      # Test data loading
└── Kernel.php
```

## Conventions

- **Strict types**: Every PHP file starts with `declare(strict_types=1);`
- **Constructor injection**: All dependencies via constructor with PHP 8 promoted properties
- **Namespace**: `App\` maps to `src/`
- **Routing**: YAML-based (`config/routes.yaml`), not annotations/attributes
- **DI config**: `config/services.yaml` — autowire + autoconfigure enabled
- **Repository binding**: `OrderRepositoryInterface` → `DoctrineOrderRepository` (configured in services.yaml)

## Key Patterns

- **Service layer**: Controllers never touch repositories directly; they call service classes (e.g., `CreateOrderService`)
- **Repository interface**: Domain defines the interface, infrastructure provides the implementation
- **Fan-out messaging**: One `OrderCreatedMessage` dispatches to 3 transports simultaneously
- **Mercure integration**: Handlers publish real-time updates after processing

## Database

PostgreSQL 16 with Doctrine ORM. Entity mapping via PHP 8 attributes in `Order.php`.

Migration file: `backend/migrations/Version20250530000000.php`

## Commands

```bash
# Run locally (without Docker)
php -S localhost:8000 -t public

# Doctrine migrations
php bin/console doctrine:migrations:migrate

# Load fixtures
php bin/console doctrine:fixtures:load

# Consume messages (one per terminal)
php bin/console messenger:consume async_notifications -vv
php bin/console messenger:consume async_inventory -vv
php bin/console messenger:consume async_analytics -vv

# Clear cache
php bin/console cache:clear
```

## Known Technical Debt

- **No validation**: No Symfony Validator usage — input validation is missing
- **No DTOs**: Controllers work with raw `json_decode` arrays
- **No error handling**: No custom exception handling or API error format
- **No tests**: No `tests/` directory exists
- **Float for money**: `float $total` causes precision issues
- **SQLite backup**: `OrderRepository` (SQLite) kept but unused — consider removing
