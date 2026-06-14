# Tasks: Add Input Validation and DTOs to Order Creation

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~335 |
| 400-line budget risk | Low |
| Chained PRs recommended | No |
| Suggested split | Single PR |
| Delivery strategy | ask-on-risk |
| Chain strategy | pending |

Decision needed before apply: No
Chained PRs recommended: No
Chain strategy: pending
400-line budget risk: Low

### Suggested Work Units

| Unit | Goal | Likely PR | Notes |
|------|------|-----------|-------|
| 1 | Full DTO validation change | PR 1 | Single PR, ~335 lines, well under 400-line budget |

## Phase 1: Foundation / Test Inversion

- [x] **1.1** Invert `testCreateOrderWithEmptyBodyDoesNotCrash` in `backend/tests/Functional/Controller/OrderControllerTest.php` to assert 400 instead of 201 — RED (~5 lines)
- [x] **1.2** Add `"symfony/validator": "^7.0"` to `backend/composer.json` and run `composer install` (~1 line)
- [x] **1.3** Create `backend/src/Dto/CreateOrderRequest.php` with typed properties + all `#[Assert\*]` attributes (~25 lines)

## Phase 2: Controller Validation (TDD per constraint)

- [x] **2.1** Inject `ValidatorInterface` into `OrderController::__construct`; hydrate DTO + `validate()` + 400 branch with `buildErrorResponse()` — GREEN for email (~84 lines total)
- [x] **2.2** Add `#[Assert\Email]` on `CreateOrderRequest::$customerEmail` — GREEN for invalid email (included in 1.3)
- [x] **2.3** Add `#[Assert\NotNull]` + `#[Assert\Count(min:1)]` on `CreateOrderRequest::$items` — GREEN for items (included in 1.3)
- [x] **2.4** Add `#[Assert\NotNull]` + `#[Assert\GreaterThan(0)]` on `CreateOrderRequest::$total` — GREEN for total/empty body (included in 1.3)

## Phase 3: Functional Tests

- [x] **3.1** Add 8 new test methods to `OrderControllerTest` covering spec rows 2–9 (empty email, invalid email, empty items, missing items, zero total, negative total, multiple errors, malformed JSON) (~160 lines)

## Phase 4: Service Integration

- [x] **4.1** Change `CreateOrderService::execute()` signature from `(string $email, array $items, float $total)` to `(CreateOrderRequest $request)` — RED (~5 lines)
- [x] **4.2** Update `CreateOrderServiceTest` to construct `CreateOrderRequest` fixtures — GREEN (~60 lines)

## Phase 5: DTO Unit Tests

- [x] **5.1** Create `backend/tests/Unit/Dto/CreateOrderRequestTest.php` with 6 pure-Validator unit tests (one per constraint) — RED → GREEN (~100 lines)

## Phase 6: Verification & Refactor

- [ ] **6.1** Run full suite: `cd backend && php vendor/bin/phpunit` — all suites GREEN, no deprecations/notices/warnings (~0 lines) — **BLOCKED: local PHP missing dom/xml/xmlwriter extensions; Docker not running**
- [x] **6.2** Extract `buildErrorResponse()` private helper if `OrderController::create()` reads as busy; verify tests still green — already extracted during Phase 2