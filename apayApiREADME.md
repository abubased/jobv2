# apay — Multi-Step Transaction Engine

A reusable, frontend-agnostic REST API for driving a user through a multi-step
transaction. Any client can sit in front of it — Blade, Inertia, Livewire, Vue,
React, Next.js, a mobile app — because the backend returns **data only** and
never dictates presentation. No popups, no redirects, no countdowns: the
frontend owns all of that.

Laravel 12 · PHP 8.3 · MySQL 8.4 · JSON only.

---

## Getting started

```bash
composer install
cp .env.example .env && php artisan key:generate

# Create the schemas (the test suite needs apay_testing).
mysql -u root -e "CREATE DATABASE apay CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -e "CREATE DATABASE apay_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

php artisan migrate
php artisan db:seed            # issues a dev API key, generates 100 backup codes
php artisan test
```

> MySQL must run InnoDB. `config/database.php` forces it, because some
> installations (WAMP among them) still default to MyISAM, which silently
> discards foreign keys and supports neither transactions nor row locking — both
> of which this engine's correctness depends on.

Issue a key for a calling application:

```bash
php artisan api-client:create "Merchant Portal"   # prints the key once
php artisan backup-code:generate 500
```

---

## Authentication

Every request carries the key in a header:

```
X-API-KEY: apay_<prefix>_<secret>
```

Keys are stored as an HMAC-SHA256 keyed with `APP_KEY`; only the public prefix is
kept in clear. A malformed key, an unknown prefix, a wrong secret and a revoked
client all return the **same** 401 body, so the endpoint cannot be used to
enumerate valid clients.

The authenticated client is stamped onto every transaction it creates
(`api_client_id` plus an `api_client` name snapshot) and scopes every subsequent
read: another client's transaction returns **404**, never 403.

---

## Response envelope

Every response — success, business failure, validation error, 404, 405, 429,
500 — has exactly these five keys, in this order:

```json
{
  "success": true,
  "code": "STEP_1_COMPLETED",
  "message": "Transaction created.",
  "data": {},
  "errors": null
}
```

- `success` — whether the flow advanced. A cancellation and a failed
  verification are `false` while still being valid, expected outcomes.
- `code` — stable machine-readable outcome. **Branch on this, never on `message`.**
- `data` — always a JSON object, never `[]`.
- `errors` — `null` except on validation failures.

---

## Endpoints

All under `/api/v1`. `transaction_id` is the transaction UUID and travels in the
request body.

| Method | Path | Body |
|---|---|---|
| `GET` | `/health` | — (no key required) |
| `POST` | `/transactions/step-one` | `invoice_no`, `merchant_name`, `amount`, `customer_input`, `metadata?` |
| `POST` | `/transactions/step-two` | `transaction_id`, `second_input` (4–5 digits) |
| `POST` | `/transactions/step-three` | `transaction_id`, `backup_code` (6 digits) |
| `POST` | `/transactions/cancel` | `transaction_id` |
| `GET` | `/transactions/{uuid}` | — |

### STEP 1 — create

`amount`, `merchant_name` and `invoice_no` come from the calling application.
`customer_input` is whatever the user typed — a phone number, bill number,
account number, barcode, anything. The engine stores it verbatim and attaches no
meaning to it. The transaction is persisted immediately, so nothing is lost if
the user abandons the flow here.

```json
{ "success": true, "code": "STEP_1_COMPLETED", "message": "Transaction created.",
  "data": { "transaction_id": "019fb5e2-…", "status": "step1_completed",
            "current_step": 1, "next_step": 2, "invoice_no": "INV-2026-000123",
            "merchant_name": "Acme Retail", "amount": "1500.00" },
  "errors": null }
```

### STEP 3 — verification and retries

A failed verification returns HTTP **200** — a wrong code is a business outcome,
not a protocol error:

```json
{ "success": false, "code": "VERIFICATION_FAILED", "message": "Verification failed.",
  "data": { "transaction_id": "019fb5e2-…", "status": "failed", "current_step": 2,
            "next_step": 3, "verification": false,
            "retry": { "retry_allowed": true, "retry_after": 30, "attempts_used": 1,
                       "attempts_remaining": 2, "max_attempts": 3 } },
  "errors": null }
```

The backend reports retry **state** only. The frontend decides whether to show a
countdown, disable a button, or anything else.

Once attempts run out, `code` becomes `RETRY_LIMIT_EXCEEDED`, `retry_after` is
`null` (so no client starts a countdown leading nowhere), and further
submissions return **409**.

**Two guarantees hold on every path:** the submitted code and the verification
result are written to the transaction whether verification succeeds or fails —
user input is never discarded for being wrong — and a backup code is consumed at
most once, enforced by an atomic conditional update rather than a
read-then-write.

### Cancel

Available from any non-terminal state. `success` is `false` by contract: the
flow did not complete, even though the cancellation itself succeeded.

```json
{ "success": false, "code": "USER_CANCELLED", "message": "Transaction cancelled.",
  "data": { "transaction_id": "019fb5e2-…", "status": "cancelled", "current_step": 2 },
  "errors": null }
```

### Response codes

`STEP_1_COMPLETED` · `STEP_2_COMPLETED` · `TRANSACTION_VERIFIED` ·
`USER_CANCELLED` · `VERIFICATION_FAILED` · `RETRY_LIMIT_EXCEEDED` ·
`VALIDATION_FAILED` · `MISSING_API_KEY` · `INVALID_API_KEY` ·
`TRANSACTION_NOT_FOUND` · `INVALID_STEP` · `NOT_FOUND` · `METHOD_NOT_ALLOWED` ·
`TOO_MANY_REQUESTS` · `SERVER_ERROR`

---

## Architecture

```
Route → ForceJsonResponse → AuthenticateApiKey → FormRequest → Controller (thin)
      → TransactionService → StepHandler → Repository → Model
      → StepResult → ApiResponse
```

- **`app/Services/TransactionService.php`** — the orchestrator. Owns the database
  transaction, the row lock, the transition guard, persistence and logging, so a
  step handler contains only what is unique to its step.
- **`app/Services/Contracts/StepHandler.php`** — the extension point.
- **`config/transaction.php`** — the step registry and retry policy.
- **`app/Support/ApiResponse.php`** — the single writer of the envelope, shared by
  controllers, middleware and the exception handler.
- **`app/Services/RetryPolicy.php`** — the only class that knows the retry rules.

### Adding STEP 4

Nothing about steps 1–3 changes:

1. Add a handler implementing `StepHandler`.
2. Add a `FormRequest` and a DTO implementing `StepData`.
3. Register it in `config/transaction.php`:
   ```php
   4 => ['handler' => StepFourHandler::class,
         'allowed_from' => [TransactionStatus::Verified],
         'next_step' => null],
   ```
4. Add a controller method and a route.

The transaction lock, guard, envelope, logging and retry machinery are inherited.
`StepRegistryTest::a_new_step_becomes_available_purely_through_configuration`
holds this claim to account.

### Statuses

`pending` → `step1_completed` → `step2_completed` → `verified`

with `failed`, `cancelled` and `expired` as branches. `verified`, `cancelled` and
`expired` are terminal. **`failed` is deliberately not terminal** — it is a
retryable state, and STEP 3 re-enters from it with no special case. Exhaustion is
enforced by the retry policy, not by terminality.

### Concurrency

Every mutating step runs in `DB::transaction(…, attempts: 3)` with
`lockForUpdate()` on the transaction row, taken **before** the guard reads state.
Duplicate or concurrent submissions serialise: the first commits, the second
re-reads the committed row and is rejected with 409. Backup code consumption is a
single conditional `UPDATE` checked by affected-row count, so two concurrent
verifications can never both claim one code.

The test suite runs against **MySQL, not sqlite**, because sqlite treats
`lockForUpdate()` as a no-op and these guarantees would go untested.

---

## Security notes

Deliberate trade-offs, made to build to the specified schema:

- **Secrets are stored in clear.** `backup_codes.code`, `transactions.backup_code`,
  `customer_input` and `second_input` are all readable in the database. Every one
  of them is masked in API responses and in the log channel, so the database is
  the only exposure surface.
- **The backup code pool is global** — not scoped to a client or transaction, so
  any active code verifies any transaction. Scoping it later is one migration
  plus one `where` clause in `BackupCodeRepository`.
- **Retry history lives in logs, not the database.** With a single `backup_code`
  column, only the latest attempt survives on the row;
  `storage/logs/transactions-*.log` is the durable per-attempt record. Since
  `RetryPolicy` is the only class that reads attempt counts, introducing a
  `transaction_attempts` table later touches one class.
- A 6-digit code is only a 10⁶ space, and a per-transaction cap does not stop an
  attacker who creates fresh transactions — hence the dedicated
  `transaction-verify` rate limiter on STEP 3, keyed on the authenticated client.

## Configuration

| Variable | Default | Purpose |
|---|---|---|
| `API_KEY_HEADER` | `X-API-KEY` | Header carrying the key |
| `TRANSACTION_MAX_ATTEMPTS` | `3` | STEP 3 attempt ceiling |
| `TRANSACTION_RETRY_AFTER` | `30` | Advisory retry delay, seconds |
| `TRANSACTION_VERIFY_RATE_LIMIT` | `10` | STEP 3 requests per minute |
| `API_RATE_LIMIT` | `120` | Baseline requests per minute |
| `TRANSACTION_LOG_DAYS` | `90` | Audit log retention |
| `DB_ENGINE` | `InnoDB` | Storage engine — do not change |
