# Design: Add Input Validation and DTOs to Order Creation

## Technical Approach

Replace the raw-array intake in `OrderController::create()` with a typed `CreateOrderRequest` DTO validated by Symfony's `symfony/validator`. The controller becomes a thin shell that decodes JSON, hydrates the DTO, runs `ValidatorInterface::validate()`, and either dispatches the DTO to `CreateOrderService` (returns 201) or returns a structured 400 with field-level errors. The DTO uses PHP 8 `#[Assert\*]` attributes; no YAML/XML mapping. `CreateOrderService::execute()` signature changes to accept the DTO.

This is the smallest change that satisfies all 9 spec requirements and the proposal. Existing read endpoints (`list`, `get`) are untouched.

## Architecture Decisions

### Decision 1: DTO location — `App\Dto\CreateOrderRequest`

**Choice**: New top-level `src/Dto/` namespace, class `App\Dto\CreateOrderRequest`.
**Alternatives**: (a) `App\Controller\Dto\CreateOrderRequest` (transport-coupled), (b) `App\Http\CreateOrderRequest` (Symfony 7 idiom but adds a new namespace for one class).
**Rationale**: Per proposal. `src/Dto/` is a common PHP convention for transport-layer value objects and is reachable by the existing `App\:` autowire glob in `services.yaml`. It is NOT in `src/Domain/` because input shape is an HTTP concern, not a domain invariant. The DTO has no behavior beyond typed accessors — domain validation (when needed later) lives on `Order`.

### Decision 2: Validation via PHP 8 attributes, not YAML/XML

**Choice**: `#[Assert\NotBlank]`, `#[Assert\NotNull]`, `#[Assert\Email]`, `#[Assert\Count(min: 1)]`, `#[Assert\GreaterThan(0)]` on DTO properties.
**Alternatives**: YAML mapping (`config/validator/validation.yaml`), XML, or property callbacks.
**Rationale**: Attributes are co-located with the data they constrain, give full IDE autocompletion, and match the existing `#[ORM\*]` mapping style on `Order`. No new config file.

### Decision 3: Manual JSON → DTO hydration, not Serializer

**Choice**: Decode JSON to associative array with `json_decode($request->getContent(), true)`, then assign each DTO property in the controller.
**Alternatives**: `SerializerInterface::deserialize()` (already installed via `symfony/serializer`).
**Rationale**: DTO is 3 fields. The Serializer adds a `Serializer` + `Normalizer` config layer and error mapping for unknown properties. Manual hydration is ~6 lines, fully explicit, and matches the test scenarios in the spec (e.g. "missing `items` field" must produce a violation — easier to control with explicit `?? null`).

### Decision 4: `CreateOrderService::execute(CreateOrderRequest $request)`

**Choice**: Service signature changes from `(string $email, array $items, float $total)` to `(CreateOrderRequest $request)`.
**Alternatives**: Keep raw args, add an overloaded `executeFromRequest()`.
**Rationale**: One service method, one entry point. The DTO is already validated by the time it reaches the service, so the service can trust its contents and pull values via accessors. This is a controlled, mechanical refactor: `CreateOrderServiceTest` and the controller are the only call sites.

### Decision 5: `ValidatorInterface` injected into the controller

**Choice**: Constructor inject `Symfony\Component\Validator\Validator\ValidatorInterface`.
**Alternatives**: Use `MapRequestPayload` attribute (Symfony 7 idiom, body-validates automatically).
**Rationale**: `MapRequestPayload` would couple DTO shape to Symfony's auto-deserialization, which we explicitly rejected in Decision 3. Manual `validate()` keeps the hydration error path (`json_decode` failure) separate from the validation error path. `ValidatorInterface` is auto-wired by the framework bundle when `symfony/validator` is installed — no `services.yaml` change needed.

## Data Flow

```
HTTP POST /api/orders
       │
       ▼
┌──────────────────────────────────────────────┐
│ OrderController::create(Request $request)   │
│                                              │
│  1. $raw = $request->getContent()            │
│  2. $data = json_decode($raw, true)          │
│  3. if json_decode failed  ──→ 400 {_body}   │
│  4. $dto = new CreateOrderRequest(           │
│       $data['customerEmail'] ?? null,        │
│       $data['items']          ?? null,        │
│       $data['total']          ?? null         │
│     )                                        │
│  5. $violations = $validator->validate($dto) │
│  6. if count($violations) > 0 ──→ 400 errors │
│  7. $order = $createService->execute($dto)   │
│  8. return 201 $order->toArray()             │
└──────────────────────────────────────────────┘
       │                  │              │
       ▼                  ▼              ▼
   json_decode    ValidatorInterface   CreateOrderService
   (fails → 400)  (violations → 400)        │
                                              ▼
                                        OrderRepository::save
                                              │
                                              ▼
                                        MessageBus::dispatch
                                              (OrderCreatedMessage)
```

`framework.handle_all_throwables: true` remains in place as a backstop but is no longer the primary error path for this endpoint.

## Error Handling Strategy

`buildErrorResponse(ConstraintViolationList $violations): array` is a private controller helper that:

1. Iterates `$violations`.
2. For each violation, reads `$violation->getPropertyPath()` (e.g. `customerEmail`, `items`) and `$violation->getMessage()`.
3. Groups by property path: `$errors[$path][] = $message`.
4. Returns `['errors' => $errors]`.

The controller wraps this in `new JsonResponse(..., Response::HTTP_BAD_REQUEST)`. Malformed JSON is its own branch returning `['errors' => ['_body' => ['Invalid JSON body']]]` with the same 400 status. Symfony's default `ConstraintViolation` property path for top-level DTO properties is the property name itself — no manual path manipulation needed for the spec's fields.

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `backend/composer.json` | Modify | Add `"symfony/validator": "^7.0"` to `require`. `composer require` then `composer install` regenerates the lock. |
| `backend/src/Dto/CreateOrderRequest.php` | Create | Plain DTO with 3 typed properties and `#[Assert\*]` attributes. |
| `backend/src/Controller/OrderController.php` | Modify | Inject `ValidatorInterface`. Replace `json_decode` + null-coalesce block with DTO hydration + validate + branch. Keep `list()` and `get()` unchanged. |
| `backend/src/Domain/Service/CreateOrderService.php` | Modify | `execute(CreateOrderRequest $request): Order` — pull values from DTO accessors. |
| `backend/tests/Functional/Controller/OrderControllerTest.php` | Modify | Add 8 new test methods (spec rows 2–9). Rewrite existing `testCreateOrderWithEmptyBodyDoesNotCrash` to assert 400 (spec row 10). |
| `backend/tests/Unit/Domain/Service/CreateOrderServiceTest.php` | Modify | Construct `CreateOrderRequest` fixtures; assert service uses DTO values. Add a unit test for `CreateOrderRequest` validation (new file, see below). |
| `backend/tests/Unit/Dto/CreateOrderRequestTest.php` | Create | Pure Validator-on-DTO tests, no kernel boot. Fast feedback for constraint tuning. |
| `backend/config/services.yaml` | No change | `App\:` autowire glob picks up the new DTO; `ValidatorInterface` is auto-wired by the framework bundle. |
| `backend/src/Domain/Entity/Order.php` | No change | Domain entity untouched; constructor signature preserved. |

## Testing Strategy (Strict TDD)

Test runner: `php bin/phpunit`. Test environment: `APP_ENV=test`, `DATABASE_URL=sqlite:///:memory:`, DAMA bundle wraps each test in a rolled-back transaction. One cycle = red → green → refactor.

| Step | Action | Expected red signal | Green signal |
|------|--------|---------------------|--------------|
| 1 | Add `testCreateOrderWithEmptyEmailReturns400` to functional test | 201 returned, 400 expected | After step 3 |
| 2 | Create `CreateOrderRequest` with `#[Assert\NotBlank]` on `customerEmail` | DTO doesn't exist, autoload fails | Autoload OK, controller still returns 201 |
| 3 | Modify `OrderController::create()` to hydrate DTO + validate + 400 branch | Same as step 1 | 400 with `errors.customerEmail` array |
| 4 | Add 7 more functional tests (invalid email, empty items, missing items, zero/negative total, multiple errors, malformed JSON, empty body) | Each one fails in turn | Each passes as constraints are added |
| 5 | Add `#[Assert\Email]`, `#[Assert\NotNull]`, `#[Assert\Count(min: 1)]`, `#[Assert\GreaterThan(0)]` to DTO | Per-test failures | All 9 functional tests pass |
| 6 | Create `CreateOrderRequestTest` with 6 unit tests (one per constraint) | All fail | All pass — proves the DTO is the source of truth |
| 7 | Modify `CreateOrderService::execute(CreateOrderRequest $request)` and update the 3 unit tests in `CreateOrderServiceTest` | Service test fails (wrong signature) | Tests pass, service still works |
| 8 | Run full suite: `php bin/phpunit` | — | All 3 test suites green; no deprecations/notices/warnings (`failOnDeprecation/Notice/Warning="true"`) |
| 9 | Refactor: extract `buildErrorResponse()` helper if the controller is busy; consider `final` on the DTO | — | Tests still green |

The `testCreateOrderWithEmptyBodyDoesNotCrash` test is intentionally inverted: it currently asserts 201, the spec requires 400. Rewrite it BEFORE implementing the controller change so the new behavior is what drives the design.

## Edge Cases and Mitigations

| Case | Behavior | Mitigation |
|------|----------|------------|
| Empty body `""` | `json_decode` returns `null`, error code != `JSON_ERROR_NONE` | 400 with `errors._body: ["Invalid JSON body"]` |
| Body `null` | Same as empty body | Same branch |
| Body `{}` (no fields) | DTO hydrates with all 3 properties `null` | `NotNull` on `items` and `total`, `NotBlank` on `customerEmail` → 400 with 3 field errors |
| `items` is `[]` | DTO hydrates with `[]` | `Count(min: 1)` → 400 with `errors.items` |
| `items` is a string `"a"` | DTO hydrates with `["a"]` (cast to array by assignment) — `Count(min: 1)` passes, then `Order` accepts it | Spec only requires "at least 1 element"; type strictness is out of scope (no `Type("array")` constraint to avoid extra surface area) |
| `total` is `"9.99"` (string) | PHP coerces to `9.99` float on construction | `GreaterThan(0)` passes; matches prior behavior |
| `total` is `0.0` | `GreaterThan(0)` rejects | 400 with `errors.total` |
| Unknown fields (`foo: "bar"`) | Ignored — manual hydration never reads them | No change needed; matches current behavior |
| Validator not installed | `ValidatorInterface` cannot be autowired | Step 0 of TDD is `composer require symfony/validator:^7.0` and `composer install` |
| `failOnDeprecation/Notice/Warning="true"` triggers on validator 7.x deprecations | CI fails | Verify against PHP 8.2 + Symfony 7.x; pin to `^7.0` per proposal's mitigation |

## Migration / Rollout

No DB migration. No data backfill. The change is gated by `composer require` and a single PR. Rollback is `git revert` + `composer install` to restore the lock file. No feature flag needed — read endpoints are untouched and the only behavioral change is "previously-accepted bad payloads now return 400", which is a strict improvement.

## Open Questions

- [ ] None blocking. The serializer-vs-manual hydration choice (Decision 3) can be revisited if/when we add a second DTO with nested objects.
- [ ] The "items as string" case is acknowledged as a known gap. If the team wants type-strict items, add `#[Assert\Type('array')]` — defer to follow-up change.
