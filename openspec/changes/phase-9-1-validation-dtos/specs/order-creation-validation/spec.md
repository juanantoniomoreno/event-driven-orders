# Order Creation Validation Specification

## Purpose

Validates incoming order creation payloads at the HTTP boundary and returns structured field-level error responses, preventing invalid data from reaching the domain layer.

## Validation Rules

| Field | Constraint | Error Message |
|-------|-----------|---------------|
| `customerEmail` | Not blank | "This value should not be blank." |
| `customerEmail` | Valid email format | "This value is not a valid email address." |
| `items` | Not null | "This value should not be null." |
| `items` | At least 1 element | "This collection should contain at least 1 element." |
| `total` | Not null | "This value should not be null." |
| `total` | Greater than 0 | "This value should be greater than 0." |

## Requirements

### Requirement: Valid Order Creation Payload

The system MUST accept well-formed order creation requests and return 201 Created with the order data. A valid payload contains a non-blank email, at least one item, and a positive total.

#### Scenario: Valid payload creates order successfully

- GIVEN a JSON payload with `customerEmail: "test@example.com"`, `items: ["widget"]`, `total: 9.99`
- WHEN the client sends POST `/api/orders`
- THEN the response status MUST be 201
- AND the response body MUST contain the created order with all fields

### Requirement: Email Validation

The system MUST reject payloads where `customerEmail` is blank or not a valid email address.

#### Scenario: Empty email returns 400

- GIVEN a JSON payload with `customerEmail: ""`
- WHEN the client sends POST `/api/orders`
- THEN the response status MUST be 400
- AND the response body MUST contain `{"errors": {"customerEmail": [...]}}`

#### Scenario: Invalid email format returns 400

- GIVEN a JSON payload with `customerEmail: "not-an-email"`
- WHEN the client sends POST `/api/orders`
- THEN the response status MUST be 400
- AND the response body MUST contain a `customerEmail` error entry

### Requirement: Items Validation

The system MUST reject payloads where `items` is missing, null, or an empty array.

#### Scenario: Empty items array returns 400

- GIVEN a JSON payload with `items: []`
- WHEN the client sends POST `/api/orders`
- THEN the response status MUST be 400
- AND the response body MUST contain `{"errors": {"items": [...]}}`

#### Scenario: Missing items field returns 400

- GIVEN a JSON payload without the `items` field
- WHEN the client sends POST `/api/orders`
- THEN the response status MUST be 400
- AND the response body MUST contain an `items` error entry

### Requirement: Total Validation

The system MUST reject payloads where `total` is zero, negative, or missing.

#### Scenario: Zero total returns 400

- GIVEN a JSON payload with `total: 0`
- WHEN the client sends POST `/api/orders`
- THEN the response status MUST be 400
- AND the response body MUST contain `{"errors": {"total": [...]}}`

#### Scenario: Negative total returns 400

- GIVEN a JSON payload with `total: -5.00`
- WHEN the client sends POST `/api/orders`
- THEN the response status MUST be 400
- AND the response body MUST contain a `total` error entry

### Requirement: Multiple Validation Errors

The system MUST return all field-level errors in a single response when multiple fields are invalid.

#### Scenario: Multiple invalid fields returns all errors

- GIVEN a JSON payload with `customerEmail: ""`, `items: []`, and `total: 0`
- WHEN the client sends POST `/api/orders`
- THEN the response status MUST be 400
- AND the `errors` object MUST contain entries for `customerEmail`, `items`, and `total`

### Requirement: Structured Error Response Format

The system MUST return validation errors as `{"errors": {"<field>": ["<message>", ...]}}`.

#### Scenario: Error response shape

- GIVEN any invalid payload
- WHEN the client sends POST `/api/orders`
- THEN the response Content-Type MUST be `application/json`
- AND the body MUST have a top-level `errors` key
- AND each key under `errors` MUST map to a non-empty array of strings

### Requirement: Malformed JSON Handling

The system MUST return 400 when the request body is not valid JSON.

#### Scenario: Invalid JSON body returns 400

- GIVEN a request body containing `not json`
- WHEN the client sends POST `/api/orders`
- THEN the response status MUST be 400
- AND the response body MUST contain an error message

### Requirement: No Regression on Read Endpoints

The validation MUST NOT affect `GET /api/orders` or `GET /api/orders/{id}`.

#### Scenario: List and get endpoints unchanged

- GIVEN existing orders in the database
- WHEN the client sends GET `/api/orders` or GET `/api/orders/{id}`
- THEN the responses MUST be identical to pre-validation behavior

## Test Scenarios

| # | Scenario | Test Method | Status |
|---|----------|-------------|--------|
| 1 | Valid payload → 201 | `testCreateOrderReturns201` | Existing |
| 2 | Empty email → 400 | `testCreateOrderWithEmptyEmailReturns400` | New |
| 3 | Invalid email → 400 | `testCreateOrderWithInvalidEmailReturns400` | New |
| 4 | Empty items → 400 | `testCreateOrderWithEmptyItemsReturns400` | New |
| 5 | Missing items → 400 | `testCreateOrderWithMissingItemsReturns400` | New |
| 6 | Zero total → 400 | `testCreateOrderWithZeroTotalReturns400` | New |
| 7 | Negative total → 400 | `testCreateOrderWithNegativeTotalReturns400` | New |
| 8 | Multiple errors → 400 | `testCreateOrderWithMultipleValidationErrors` | New |
| 9 | Malformed JSON → 400 | `testCreateOrderWithMalformedJsonReturns400` | New |
| 10 | Empty body → 400 | `testCreateOrderWithEmptyBodyReturns400` | Modified (was: asserts 201) |
