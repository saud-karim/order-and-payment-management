# Extendable Order and Payment Management API

Technical assessment submission for **Tocaan** — a Laravel 12 REST API for JWT-authenticated order management and extensible simulated payment processing.

Original brief: [`docs/Extendable_Order_Payment_API_Task.pdf`](docs/Extendable_Order_Payment_API_Task.pdf)  
Requirements traceability: [`docs/REQUIREMENTS_COVERAGE.md`](docs/REQUIREMENTS_COVERAGE.md)

---

## 1. Project overview

The API lets authenticated users create and manage orders (with server-calculated totals), transition orders through a documented status workflow, and process **simulated** credit card and PayPal payments. Payment gateways are registered via configuration and resolved through a strategy-style factory. Payments support idempotency keys, persistence of all attempts (`pending`, `successful`, `failed`), and guards against duplicate successful charges.

This is an assessment codebase — not production-hardened for real card processing or live PSP integration.

---

## 2. Implemented features

- JWT register, login, logout, and profile
- Order CRUD with status filtering and pagination
- Server-side BCMath monetary calculations
- Order status transition rules and delete protection when payments exist
- Simulated `credit_card` and `paypal` gateways (strategy pattern)
- Payment processing on **confirmed** orders only
- Idempotency-Key header support with fingerprint validation
- Payment listing (global and per-order) with pagination
- Consistent JSON success/error envelopes
- Automated unit and feature tests (71 tests at last Phase 5 run)

---

## 3. Tech stack and requirements

| Component | Version / note |
|-----------|----------------|
| PHP | **8.3+** |
| Laravel | **12.x** |
| Auth | [`php-open-source-saver/jwt-auth`](https://github.com/PHP-Open-Source-Saver/jwt-auth) ^2.9 |
| Database (default) | SQLite (`database/database.sqlite`) |
| Code style | Laravel Pint (PSR-12) |

**Required PHP extensions:** `openssl`, `mbstring`, `tokenizer`, `xml`, `ctype`, `json`, **`bcmath`**, **`pdo_sqlite`**, **`sodium`** (JWT signing), `fileinfo`

---

## 4. Installation

```bash
git clone <repository-url> order-and-payment-management
cd order-and-payment-management
composer install
```

---

## 5. Environment setup

```bash
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
```

Configure optional variables in `.env` (see `.env.example`):

- `ORDER_DEFAULT_CURRENCY` (default `USD`)
- `PAYMENT_GATEWAY_*_ENABLED` toggles
- `PAYMENT_SIM_*_SUFFIX` for **automated tests / internal simulator** (not public API fields)

Never commit `.env` or real secrets.

---

## 6. Database setup and migrations

**SQLite (default):**

```bash
# Windows PowerShell
New-Item -ItemType File -Path database\database.sqlite -Force

php artisan migrate
```

**MySQL / PostgreSQL:** set `DB_*` in `.env`, create an empty database, then `php artisan migrate`.

### One successful payment per order (DB constraint)

Migration `2026_10_21_000004_add_payment_idempotency_fields.php` adds:

- `operation_reference` (unique, server-issued)
- `request_fingerprint` (SHA-256 idempotency fingerprint)
- Partial / functional **unique index** on successful payments per order:
  - **SQLite:** `WHERE status = 'successful'`
  - **PostgreSQL:** same partial index syntax
  - **MySQL 8.0.13+:** functional unique index on `IF(status = 'successful', order_id, NULL)`

**MySQL migrations were not executed in the submission environment** (no MySQL test database configured). Verify on MySQL 8.0.13+ before production use.

Multiple **failed** attempts per order are allowed.

---

## 7. Running the application

```bash
php artisan serve
```

Base URL: `http://127.0.0.1:8000`  
API prefix: `/api`

Health check: `GET /up`

---

## 8. Authentication and JWT usage

| Method | Endpoint |
|--------|----------|
| POST | `/api/auth/register` |
| POST | `/api/auth/login` |
| POST | `/api/auth/logout` |
| GET | `/api/auth/me` |

Send `Authorization: Bearer {token}` on protected routes.

**Response envelope:**

```json
{ "success": true, "message": "...", "data": { } }
{ "success": false, "message": "...", "errors": { } }
```

---

## 9. API endpoint reference

### Orders (JWT required)

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/orders` | List own orders (`?status=`, `?page=`, `?per_page=` max 100) |
| POST | `/api/orders` | Create order (`customer_name`, `customer_email`, `items[]`) |
| GET | `/api/orders/{id}` | Show order |
| PUT/PATCH | `/api/orders/{id}` | Update (rules apply by status) |
| DELETE | `/api/orders/{id}` | Delete if no payment attempts |

### Payments (JWT required)

| Method | Path | Description |
|--------|------|-------------|
| POST | `/api/orders/{id}/payments` | Process payment — header **`Idempotency-Key`** required |
| GET | `/api/payments` | List own payments (`?order_id=`, pagination) |
| GET | `/api/orders/{id}/payments` | List payments for owned order |

**Process payment body (example):**

```json
{ "payment_method": "credit_card" }
```

Allowed methods: `credit_card`, `paypal` (must be enabled in config). Amount and currency are taken from the order — never from the client.

### HTTP semantics (process payment)

| Code | Meaning |
|------|---------|
| **201** | New attempt finalized as **successful** |
| **200** | Decline finalized as **failed**, or idempotent replay of completed attempt |
| **202** | Attempt remains **pending** (e.g. gateway timeout — retry with same key) |
| **409** | Unconfirmed order, duplicate success, unresolved pending, idempotency conflict |
| **422** | Validation / unsupported or disabled gateway |
| **503** | Unexpected gateway error (payment stays **pending**) |

---

## 10. Order status business rules

| Current | Allowed transitions |
|---------|----------------------|
| `pending` | `confirmed`, `cancelled` |
| `confirmed` | `cancelled` (only if no payment records) |
| `cancelled` | *(terminal)* |

- Customer details and line items editable **only** while `pending`.
- `confirmed` cannot revert to `pending`.
- Orders with any payment attempt cannot be deleted (**409**).

---

## 11. Payment state machine

| State | Meaning |
|-------|---------|
| `pending` | Attempt reserved; gateway outcome uncertain or not yet finalized |
| `successful` | Gateway reported success; at most one per order (app + DB index) |
| `failed` | Gateway decline (expected business outcome) |

**Gateway mapping:**

- Success → `successful` + `external_payment_id`
- Decline → `failed` + safe `failure_reason`
- Timeout / unavailable → remain `pending` (**202**)
- Unexpected infrastructure error → remain `pending` (**503**)

Invalid transitions on finalized rows are prevented by service logic.

---

## 12. Payment gateway architecture

```
PaymentController → PaymentService
                      ├─ reserve (DB tx + lockForUpdate)
                      ├─ PaymentGatewayFactory → PaymentGatewayInterface
                      └─ finalize (DB tx + lockForUpdate)
```

- **DTOs:** `GatewayPaymentRequest`, `PaymentResult`
- **Config registry:** `config/payment.php`
- **Implementations:** `CreditCardGateway`, `PayPalGateway`
- **Simulator:** `PaymentGatewaySimulator` (suffix rules on server `operation_reference`)

Raw PAN/CVV are never accepted, stored, or returned.

---

## 13. How to add a new gateway

1. Create `app/Payments/Gateways/YourGateway.php` implementing `PaymentGatewayInterface` (or extend `AbstractSimulatedGateway`).
2. Register in `config/payment.php` under `gateways.{key}` with `driver` and `enabled`.
3. Add env toggle in `.env.example`.
4. Add `PaymentMethod` enum case when the API should accept the method.
5. Add unit tests mirroring existing gateway tests.

No changes to `PaymentGatewayFactory` branching logic are required.

---

## 14. Idempotency

1. Client sends **`Idempotency-Key`** on `POST .../payments`.
2. Server stores unique key + **fingerprint** (`user|order|method|amount|currency`).
3. Same key + same fingerprint → replay stored result (**200** / **202**), **no second gateway call**.
4. Same key + different fingerprint → **409**.
5. New key blocked while another **pending** or **successful** payment exists on the order.
6. After **failed**, a new key may retry.

---

## 15. Database transactions and concurrency

**Reserve** and **finalize** steps use short transactions with `lockForUpdate()` on order/payment rows. The gateway call runs **outside** any transaction so locks are not held during I/O.

**Verified in CI:** sequential PHPUnit on SQLite in-memory.  
**Not verified:** parallel connections on MySQL/PostgreSQL — SQLite locking semantics differ. Re-test concurrency on production-like MySQL 8.0.13+ before go-live.

---

## 16. Running automated tests

```bash
php artisan test
vendor/bin/pint --test
```

Tests use SQLite `:memory:` (see `phpunit.xml`).

---

## 17. Postman

Import:

- `postman/Tocaan_Order_Payment_API.postman_collection.json`
- `postman/Tocaan_Local.postman_environment.json`

1. Select **Tocaan Local** environment (`base_url` = `http://127.0.0.1:8000/api`).
2. Run **Register** or **Login** (scripts store `jwt_token`).
3. **Create Order** → stores `order_id`.
4. **Confirm Order** before payment.
5. **Process Credit Card Payment** → sets `idempotency_key` and `payment_id`.
6. **Replay Same Idempotency Key** to verify idempotent behavior.

Declined/timeout examples in the **Error Scenarios** folder are **saved examples** from automated tests (simulation suffixes are server-controlled, not public API inputs).

---

## 18. Security considerations

- JWT on all order/payment routes
- User scoping → cross-user resource access returns **404**
- Mass assignment limited; no client-controlled totals or payment status
- Gateway secrets only in environment/config
- API resources omit `gateway_response`, PAN/CVV, and stack traces
- Logout invalidates JWT when blacklist is enabled (requires cache store)

---

## 19. Assumptions and trade-offs

- Single currency per order (`ORDER_DEFAULT_CURRENCY` / order row).
- One successful payment per order (business + partial unique index).
- Simulated PSP — no webhooks or settlement reconciliation in scope.
- `PaymentMethod` enum aligns with DB/API validation; config registry drives gateway wiring.
- Assessment prioritizes clarity and testability over multi-region deployment patterns.

---

## 20. Known limitations and production improvements

| Limitation | Mitigation in production |
|------------|---------------------------|
| Pending payments after timeout/error | PSP webhooks + reconciliation workers; admin retry tools |
| SQLite dev/test only for locking proof | MySQL/PostgreSQL + integration concurrency tests |
| MySQL migration not verified here | Run migrations on MySQL 8.0.13+ staging |
| No rate limiting / fraud scoring | API gateway, velocity checks, 3DS for real cards |
| JWT in localStorage pattern | HTTPS-only, short TTL, refresh strategy, secure client storage |

---

## Quick reference commands

```bash
php artisan --version
php artisan migrate
php artisan route:list --path=api
php artisan test
vendor/bin/pint --test
```
