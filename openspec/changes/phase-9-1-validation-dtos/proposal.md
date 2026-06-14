# Proposal: Add Input Validation and DTOs to Order Creation

## Intent

`OrderController::create()` currently accepts raw JSON arrays with no validation, passes them directly to `CreateOrderService`, and returns generic 500 errors on malformed input. This creates a poor API contract, leaks internal errors to clients, and allows invalid data (e.g., empty email, negative totals) to reach the domain layer. We need structured input validation, a typed DTO, and explicit 400 responses with field-level errors.

## Scope

### In Scope
- Install and configure `symfony/validator`
- Introduce `CreateOrderRequest` DTO with validation constraints (email format, items not empty, total > 0)
- Update `OrderController::create()` to map Request → DTO → validate → return structured 400 JSON on failure
- Add functional tests for validation success and failure paths

### Out of Scope
- DTOs for `list` or `get` endpoints (read-only, no input validation needed)
- General exception handling framework for all controllers
- Replacing `float` for money (existing debt)
- OpenAPI schema documentation

## Capabilities

### New Capabilities
- `order-creation-validation`: Validates incoming order creation payloads and returns structured field-level error responses.

### Modified Capabilities
- None

## Approach

Use Symfony's Validator component with PHP 8 attributes on the `CreateOrderRequest` DTO. The controller will:
1. Decode JSON into the DTO
2. Run `ValidatorInterface::validate()`
3. On violations, return 400 with `{"errors": {"field": ["message"]}}`
4. On success, pass the DTO to `CreateOrderService` (which will accept the DTO instead of raw parameters)

This keeps the controller thin, the validation declarative, and the error format predictable.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `backend/composer.json` | Modified | Add `symfony/validator` |
| `backend/src/Controller/OrderController.php` | Modified | Validate DTO, return 400 errors |
| `backend/src/Domain/Service/CreateOrderService.php` | Modified | Accept `CreateOrderRequest` instead of raw args |
| `backend/src/Dto/CreateOrderRequest.php` | New | Typed DTO with validation attributes |
| `backend/tests/Functional/OrderControllerTest.php` | Modified | Add validation test cases |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| `symfony/validator` conflicts with existing packages | Low | Pin to `^7.0` to match Symfony version |
| Validation breaks existing API consumers sending empty fields | Medium | Functional tests must assert exact 400 format; no schema changes |
| DTO changes require service layer updates | Low | `CreateOrderService` change is mechanical |

## Rollback Plan

Revert the commit. `composer.json` change requires `composer install` to restore lock file. No database migrations needed.

## Dependencies

- `symfony/validator` package (not currently installed)

## Success Criteria

- [ ] Invalid payloads (empty email, missing items, negative total) return 400 with structured field errors
- [ ] Valid payloads continue to create orders and return 201 as before
- [ ] Functional tests cover at least 3 validation failure cases
- [ ] No regression in existing `list` and `get` endpoints
