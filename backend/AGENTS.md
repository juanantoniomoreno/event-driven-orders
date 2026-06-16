# AGENTS.md — Backend (Symfony 7)

## Scope

Symfony 7 API with event-driven architecture. PHP 8.2, strict types everywhere.

## Architecture

Layered architecture with domain separation:

```
src/
├── Controller/        # HTTP layer — thin, delegates to services
│   ├── OrderController.php
│   └── MercureJwtController.php
├── Dto/               # Request DTOs with Symfony Validator constraints
│   ├── CreateOrderRequest.php
│   └── MoneyInput.php
├── Domain/
│   ├── Entity/        # Doctrine entities (Order)
│   ├── Service/       # Business logic + repository interface/implementations
│   │   ├── CreateOrderService.php
│   │   ├── OrderRepositoryInterface.php
│   │   └── DoctrineOrderRepository.php
│   └── ValueObject/   # Doctrine Embeddables
│       └── MoneyEmbeddable.php
├── Messaging/
│   ├── Message/       # Immutable message DTOs for Messenger
│   │   └── OrderCreatedMessage.php
│   └── Handler/       # Async processors (one per transport)
│       ├── AbstractOrderHandler.php   # Template Method pattern
│       ├── SendOrderConfirmationHandler.php
│       ├── UpdateInventoryHandler.php
│       └── TrackOrderAnalyticsHandler.php
└── Kernel.php
```

## Conventions

- **Strict Types**: Every PHP file starts with `declare(strict_types=1);`
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
- **Template Method (AbstractOrderHandler)**: Each handler extends `AbstractOrderHandler` and implements 4 hooks (`validateMessage`, `isAlreadyProcessed`, `doExecute`, `publishUpdate`). Result: ~30 lines per handler, zero duplication.

## Database

PostgreSQL 16 with Doctrine ORM. Entity mapping via PHP 8 attributes in `Order.php`.

Migration file: `backend/migrations/Version20250530000000.php`

## Testing

Test suite lives in `backend/tests/` with three layers:

| Suite | Location | Tests | Assertions |
| ----- | -------- | ----- | ---------- |
| Unit | `tests/Unit/Domain/` + `tests/Unit/Messaging/` | Order, OrderCreatedMessage, CreateOrderService | 14 tests / 58 assertions |
| Integration | `tests/Integration/` | DoctrineOrderRepository + handlers (SQLite in-memory) | 8 tests / 25 assertions |
| Functional | `tests/Functional/Controller/` | OrderController HTTP end-to-end | 6 tests |

Run with: `php bin/phpunit`

## Commands

```bash
# Run locally (without Docker)
php -S localhost:8000 -t public

# Doctrine migrations
php bin/console doctrine:migrations:migrate

# Consume messages (one per terminal)
php bin/console messenger:consume async_notifications -vv
php bin/console messenger:consume async_inventory -vv
php bin/console messenger:consume async_analytics -vv

# Run test suite
php bin/phpunit

# Clear cache
php bin/console cache:clear
```

## Known Technical Debt (known limitation, won't fix)

- **Race condition on `processedBy`**: JSON column can lose data under concurrent handler saves (last write wins). Individual idempotency works, but `processedBy` won't reliably reflect all handlers. **Low impact**: only affects logging of which handler processed the order, not functionality. Fix possible: optimistic locking or separate `order_handler_status` table, but complexity not justified for a learning project.

## Resolved

- ~~Float for money~~ → Phase 9.2 (`moneyphp/money` integer cents + ISO 4217 currency via `MoneyEmbeddable` Doctrine Embeddable; BIGINT DB column; nested `MoneyInput` DTO; migration from DOUBLE PRECISION)
- ~~No validation~~ → Phase 9.1 (Symfony Validator via `CreateOrderRequest` DTO)
- ~~No DTOs~~ → Phase 9.1 (typed `CreateOrderRequest` with `NotBlank`, `Email`, `NotNull`, `Count`, `GreaterThan` constraints)
- ~~No error handling~~ → Phase 9.1 (structured 400 responses with field-level errors; invalid JSON returns `{errors: {_body: ["Invalid JSON body"]}}`)
