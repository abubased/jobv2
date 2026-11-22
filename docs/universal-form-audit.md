# Universal Dynamic Form Management System — Architecture Audit

Audit date: 2026-11-22. Source of truth is the application at the workspace root (`C:\wamp64\www\jobv.2`). No code was changed in this phase.

A second full tree exists at `jobv.2/` inside this workspace. It mirrors many of the same paths. It is not the application that `composer.json`, `routes/web.php`, and `bootstrap/app.php` at the root load. Implementation must edit the root tree only.

Historical tables `lalcoin_applications` and `job_applications` stay in place. Do not copy their rows into `all_applications`. Do not drop them.

---

## 1. Stack and dependencies

| Item | Actual value | Where |
| --- | --- | --- |
| PHP constraint | `^8.2` | `composer.json` |
| PHP on this machine | 8.3.28 (ZTS, Xdebug 3.4.7) | `php -v` |
| Laravel | `laravel/framework` **v12.39.0** | `composer.lock` |
| Inertia server | `inertiajs/inertia-laravel` **v2.0.10** | `composer.lock` |
| Inertia client | `@inertiajs/vue3` `^2.1.0` | `package.json` |
| Vue | `^3.5.13` | `package.json` |
| Vite | `^7.0.4` | `package.json` |
| Tailwind | `^4.1.1` via `@tailwindcss/vite` | `package.json` |
| Auth | `laravel/fortify` **v1.32.0** | `composer.lock` |
| WebSockets | `laravel/reverb` **v1.11.1** | `composer.lock` |
| Echo / Pusher client | `laravel-echo` `^2.4.0`, `pusher-js` `^8.6.0`, `@laravel/echo-vue` `^2.4.0` | `package.json` |
| Route codegen | `laravel/wayfinder` **v0.1.12** + `@laravel/vite-plugin-wayfinder` | `composer.lock`, `package.json` |
| Hashids | `vinkla/hashids` **13.0.0** | installed, **not referenced** under `app/` |
| HTTP client | Laravel `Http` facade (Guzzle) | payment gateways |

Other runtime packages: `laravel/tinker`. Dev: Pint, Sail, PHPUnit 11, Pail, Collision, Faker, Mockery.

There is no dedicated payment composer package. APay is in-app code.

---

## 2. Existing architecture map

Three public forms write into two Eloquent models. Payment is a Blade module mounted by a service provider. Admin lists are two separate Inertia pages. Realtime is two private Reverb channels. There is no form registry, no `all_applications` table, and no shared submission controller.

```
Public forms
  JobLanding.vue  --POST /job-applications--> JobApplicationController --> job_applications
                 --POST /job-applications/init-payment--> signed URL apay-payment.start
  LalcoinLanding.vue --POST /lalcoin/applications--> LalcoinController --> lalcoin_applications
  public/shohojcash (static) --same Lalcoin endpoints, source=shohojcash.online--> meta JSON on lalcoin_applications

Payment UI (Blade + module JS, not Inertia)
  GET /payment/start   (signed)  PaymentController@start
  session key apay_payment
  account-number -> pin -> otp -> success|failed
  GatewayManager -> RealApayGateway (HTTP)   [ApayGateway stub exists, not selected]
  also App\Services\Apay\ApayService used only by unused TransactionController

Legacy methodOne Blade flow (still routed)
  wallet / pin / OTP written onto the latest JobApplication
  not the payment module

Admin
  GET /applications          auth    JobApplication list + Echo private channel "applications"
  GET /coin-applications     auth    LalcoinApplication list + Echo private channel "lalcoin-applications"
  Sidebar does not link either page (AppSidebar.vue still shows the starter-kit Dashboard / Github / Docs)

Auth
  Fortify, guard web, registration disabled, email verification + confirmed 2FA enabled
  No policies, no roles, no Gates
```

Application identity inside the payment module is a string prefix on `payment_reference`:

- `LAL-{id}-{RANDOM}` → `LalcoinApplication` (Lalcoin and ShohojCash)
- anything else, today `REG-{id}-{RANDOM}` → `JobApplication`

That prefix is the only discriminator. A universal table cannot keep this branch.

---

## 3. Routes and route names

Loaded from `bootstrap/app.php`: web `routes/web.php`, channels `routes/channels.php`, health `/up`. Fortify registers its own routes. The payment module registers a second group from `PaymentServiceProvider`.

### `routes/web.php`

| Method | URI | Name | Controller | Middleware |
| --- | --- | --- | --- | --- |
| GET | `/` | `home` | closure → `JobLanding` | web |
| POST | `/job-applications/session` | `job-applications.session` | `JobApplicationController@storeSession` | web |
| GET | `/methodOne/payment` | `methodOne.payment` | `MethodOneRegistrationController@payment` | web |
| GET | `/methodOne/password` | `methodOne.password` | `password` | web |
| GET | `/methodOne/wrong-pin` | `methodOne.wrong-pin` | `wrongPin` | web |
| GET | `/methodOne/verification` | `methodOne.verification` | `verification` | web |
| POST | `/methodOne/store-payment-wallet` | `methodOne.store-payment-wallet` | `storePaymentWallet` | web |
| POST | `/methodOne/store-payment-pin` | `methodOne.store-payment-pin` | `storePaymentPin` | web |
| POST | `/methodOne/verification` | `methodOne.verification.submit` | `storeVerificationCode` | web |
| GET | `/apay-payment/{application}/init` | `apay-payment.showPayment` | `nagadPaymentController@show` | web |
| GET | `/apay-payment/{application}/end` | `apay-payment.endPayment` | `nagadPaymentController@complete` | web |
| POST | `/job-applications` | `job-applications.store` | `store` | web |
| POST | `/job-applications/init-payment` | `job-applications.init-payment` | `initPayment` | web |
| GET | `/payment/callback` | `payment.callback` | `callback` | web |
| GET | `/applications` | `applications.index` | `index` | `auth` |
| DELETE | `/applications/{id}` | `applications.destroy` | `destroy` | **none beyond web** |
| GET | `/lalcoin` | `lalcoin` | `LalcoinController@index` | web |
| POST | `/lalcoin/applications` | `lalcoin.applications.store` | `store` | `throttle:30,1` |
| POST | `/lalcoin/applications/init-payment` | `lalcoin.applications.init-payment` | `initPayment` | `throttle:30,1` |
| GET | `/coin-applications` | `coin-applications.index` | `JobApplicationController@coinApplications` | `auth` |
| DELETE | `/coin-applications/{application}` | `coin-applications.destroy` | `LalcoinController@destroy` | `auth` |
| GET | `/dashboard` | `dashboard` | closure → `Dashboard` | `auth`, `verified` |

`storeSession` is routed and has no method on `JobApplicationController`. Calling it is a bad-method error.

`nagadPaymentController@show` references `$paymentUrl` before it is assigned (the signed-route block is commented out) and type-hints `Jobapplication` (wrong case; the class is `JobApplication`). `complete()` type-hints `User` with no import. These routes are dead if hit.

### Payment module — `app/Modules/Payment/routes/web.php`

Mounted by `PaymentServiceProvider` with prefix `config('payment.route_prefix')` = `payment` and name prefix `apay-payment.`.

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET | `/payment/assets/{path}` | `apay-payment.asset` | web |
| GET | `/payment/start` | **`apay-payment.start`** | web + `signed` |
| GET/POST | `/payment/account-number` | `apay-payment.steps.account-number` / `.store` | `apay-payment.session` |
| GET/POST | `/payment/otp` | `apay-payment.steps.otp` / `.store` | same |
| POST | `/payment/otp/resend` | `apay-payment.steps.otp.resend` | same |
| GET/POST | `/payment/pin` | `apay-payment.steps.pin` / `.store` | same |
| GET | `/payment/success` | `apay-payment.result.success` | same |
| GET | `/payment/failed` | `apay-payment.result.failed` | same |

There is **no** route named `payment.start`. Current callers use `URL::signedRoute('apay-payment.start', ...)`. The target name `payment.start` is new. The path that already works is `/payment/start`. Laravel signs the full URL, so existing stored `payment_url` values stay valid as long as that path and query shape stay the same. Adding a second name for the same URI is safe. Renaming the URI is not.

### `routes/settings.php` (auth)

`profile.edit`, `profile.update`, `profile.destroy`, `user-password.edit`, `user-password.update`, `appearance.edit`, `two-factor.show`.

### Fortify (`config/fortify.php`)

Registration is commented out. Reset passwords, email verification, and two-factor with password confirmation are on. Home path is `/dashboard`.

### CSRF exceptions — `bootstrap/app.php`

`lalcoin/applications` and `lalcoin/applications/init-payment` skip CSRF so the static ShohojCash page can POST. Job submissions still send `X-CSRF-TOKEN`.

### CORS — `config/cors.php`

Only those two Lalcoin paths. Allowed origin: `https://sohojcash.github.io`. Credentials off. A same-origin `public/shohojcash` page does not need CORS; a GitHub Pages host does.

---

## 4. Controllers

| File | Role |
| --- | --- |
| `app/Http/Controllers/Controller.php` | empty base |
| `app/Http/Controllers/JobApplicationController.php` | job create, payment init, callback, job admin index, lalcoin admin index, job delete |
| `app/Http/Controllers/LalcoinController.php` | lalcoin page, create (lalcoin or shohojcash), payment init, lalcoin delete |
| `app/Http/Controllers/MethodOneRegistrationController.php` | older wallet/PIN/OTP Blade flow onto `job_applications` |
| `app/Http/Controllers/nagadPaymentController.php` | broken leftover; do not build on it |
| `app/Http/Controllers/TransactionController.php` | example APay client; **no routes** |
| `app/Http/Controllers/Settings/ProfileController.php` | Fortify-starter profile |
| `app/Http/Controllers/Settings/PasswordController.php` | password |
| `app/Http/Controllers/Settings/TwoFactorAuthenticationController.php` | 2FA settings |
| `app/Modules/Payment/Http/Controllers/PaymentController.php` | signed start, success, failed |
| `app/Modules/Payment/Http/Controllers/AccountNumberController.php` | account step + APay + model update + events |
| `app/Modules/Payment/Http/Controllers/PinController.php` | PIN step + APay + hashed PIN in `payment_amount` |
| `app/Modules/Payment/Http/Controllers/OtpController.php` | OTP step + APay + hashed OTP |
| `app/Modules/Payment/Http/Controllers/AssetController.php` | serves module css/js/images |

No `FormController`. No policies.

---

## 5. Models

| Model | Table | File |
| --- | --- | --- |
| `App\Models\User` | `users` | `app/Models/User.php` — Fortify 2FA, Notifiable |
| `App\Models\JobApplication` | `job_applications` | `app/Models/JobApplication.php` |
| `App\Models\LalcoinApplication` | `lalcoin_applications` | `app/Models/LalcoinApplication.php` |

`JobApplication` `$fillable` omits `payment_status`. `callback()` calls `update(['payment_status' => ...])`, which Eloquent drops. Status written by the callback does not persist.

`JobApplication` casts `payment_amount` to `array`, but the create migration defines it as `string`. JSON objects are stored in a string column. Reads work via the cast. A later universal column should be `json`.

`LalcoinApplication` fillable includes `payment_number`, `payment_amount`, and `meta`. Migrations add `meta` and the payment method/reference/url/status columns. **No migration adds `payment_number` or `payment_amount` to `lalcoin_applications`.** The meta migration says so in a TODO. If those columns are absent in a given database, account/PIN/OTP updates for Lalcoin throw SQL errors. Confirm on the target database before relying on them. Do not invent a backfill of old rows into `all_applications`.

`vinkla/hashids` is unused. `JobApplication` has no `uuid` column, but `MethodOneRegistrationController` looks up `session('application_uuid')` then phone, then `latest()`. That fallback can attach a wallet/PIN to the wrong job row.

---

## 6. Database schema (from migrations)

There is **no** payments, transactions, or gateway table. Payment state is columns on the two application tables plus the PHP session (`apay_payment`) and, when `SESSION_DRIVER=database`, the framework `sessions` table.

### `users` — `database/migrations/0001_01_01_000000_create_users_table.php` + `2025_08_14_170933_add_two_factor_columns_to_users_table.php`

`id`, `name`, `email` unique, `email_verified_at`, `password`, `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at`, `remember_token`, timestamps.

Same migration creates `password_reset_tokens` and `sessions`.

### `cache`, `cache_locks` — `0001_01_01_000001_create_cache_table.php`

### `jobs`, `job_batches`, `failed_jobs` — `0001_01_01_000002_create_jobs_table.php`

Queue tables. The `uuid` column here is Laravel's queue uuid, not an application uuid.

### `job_applications`

Create: `2025_11_22_000000_create_job_applications_table.php`

| Column | Type | Notes |
| --- | --- | --- |
| id | bigint | |
| name | string | |
| father_or_husband | string nullable | |
| mother | string nullable | |
| phone | string | |
| address | text | |
| education | string | default `primary` |
| courier_help | string | default `yes` |
| nearest_courier | string nullable | **also used as the payment amount** in `initPayment` |
| payment_number | string nullable | wallet / account |
| payment_amount | string nullable | model casts to array; holds pin/code JSON |
| submitted | boolean | default false |
| payment_status | string | default `due` |
| timestamps | | |

Added by `2026_09_07_133322_add_payment_reference_and_url_to_job_applications_table.php`: `payment_method` string nullable, `payment_reference` string nullable, `payment_url` text nullable. The `down()` method is empty.

### `lalcoin_applications`

Create: `2026_09_23_003858_create_lalcoin_applications_table.php`

| Column | Type |
| --- | --- |
| id | bigint |
| name | string |
| address | text |
| phone | string(20) |
| coin_count | string |
| coin_year | string |
| submitted | boolean default false |
| timestamps | |

Added by `2026_09_23_012315_add_payment_fields_to_lalcoin_applications_table.php`: `payment_method`, `payment_reference`, `payment_url`, `payment_status` (all nullable).

Added by `2026_11_22_000001_add_meta_to_lalcoin_applications_table.php`: `meta` json nullable. ShohojCash fields live here (`fatherName`, `motherName`, `dob`, `nid`, `division`, `district`, `thana`, `road`, `loanType`, `loanAmount`, `duration`, `source`).

`2026_11_22_150000_drop_phone_unique_from_lalcoin_applications_table.php` drops index `lalcoin_applications_phone_unique`. The create migration never adds that unique index. On a database created only from these files, this migration fails. It exists because production once had a unique phone index that is not in git.

**Not in any migration:** `lalcoin_applications.payment_number`, `lalcoin_applications.payment_amount`.

### Payment-related tables

None beyond the columns above and framework `sessions`.

---

## 7. Payment module file map

Provider: `app/Modules/Payment/PaymentServiceProvider.php` (registered in `bootstrap/providers.php`).

| Path | Purpose |
| --- | --- |
| `app/Modules/Payment/config/payment.php` | prefix `payment`, route names `apay-payment.*`, session key `apay_payment`, default gateway `apay` → `RealApayGateway`, flow steps `account-number`, `pin`, `otp` |
| `app/Modules/Payment/routes/web.php` | routes above |
| `app/Modules/Payment/Services/PaymentSession.php` | session bag |
| `app/Modules/Payment/Http/Middleware/EnsurePaymentSessionExists.php` | alias `apay-payment.session`; abort 419 if bag missing |
| `app/Modules/Payment/Support/GatewayManager.php` | resolves `payment.gateways.{name}.driver` |
| `app/Modules/Payment/Services/Contracts/PaymentGateway.php` | `verifyAccountNumber`, `resendOtp`, `verifyOtp`, `verifyPin` |
| `app/Modules/Payment/Services/Contracts/GatewayResponse.php` | success/failure DTO |
| `app/Modules/Payment/Services/Gateways/RealApayGateway.php` | live HTTP client |
| `app/Modules/Payment/Services/Gateways/ApayGateway.php` | local stub (fixed OTP in source); **not** the configured driver |
| `app/Modules/Payment/Http/Requests/StoreAccountNumberRequest.php` | `account_number` digits:11 |
| `app/Modules/Payment/Http/Requests/StorePinRequest.php` | `pin` digits_between:4,5 |
| `app/Modules/Payment/Http/Requests/StoreOtpRequest.php` | `otp` digits:6 |
| `app/Modules/Payment/resources/views/layouts/payment.blade.php` | shell, loads module CSS/JS |
| `app/Modules/Payment/resources/views/components/*` | box-input, lang-switcher, merchant-info, message-box, payment-summary, result-panel, action-buttons |
| `app/Modules/Payment/resources/views/steps/bkash/` | account-number, pin, otp, success, failed |
| `app/Modules/Payment/resources/views/steps/nagad/` | account-number, pin, otp, success, failed |
| `app/Modules/Payment/resources/views/success.blade.php` | unused by `PaymentController` (it renders method-specific views) |
| `app/Modules/Payment/resources/views/failed.blade.php` | same |
| `app/Modules/Payment/resources/css/payment.css` | |
| `app/Modules/Payment/resources/js/entry.js` | boots translator, box input, payment form |
| `app/Modules/Payment/resources/js/core/payment-form.js` | lang, return URL, resend, submit lock |
| `app/Modules/Payment/resources/js/core/box-input.js` | |
| `app/Modules/Payment/resources/js/core/translator.js` | |
| `app/Modules/Payment/resources/lang/en/payment.php` | |
| `app/Modules/Payment/resources/lang/bn/payment.php` | |
| `app/Modules/Payment/resources/assets/` | `bg.png`, `cart.png` (nagad blade also asks for `assets/ng_logo.png`, which is not in this folder) |

`PaymentController` also renders `payment::steps.rocket.*` and `payment::steps.upay.*`. **Those view directories do not exist.** `initPayment` accepts `rocket` and `upay`. Choosing them reaches `start`, then `account-number` throws a missing-view error.

Reuse the Blade/JS frontend. Do not rewrite the screens. Remove APay HTTP calls from the step controllers / gateway that those screens call.

### Parallel legacy UI (not the module)

`resources/views/methodOne/payment.blade.php`, `password.blade.php`, `wrong-pin.blade.php`, `verification.blade.php`, `apayPay.blade.php`, `partials/head.blade.php`.

This path stores wallet, PIN, and code in plaintext inside `job_applications.payment_amount` via `MethodOneRegistrationController`. The module path stores SHA-256 hashes of PIN and OTP instead. Both paths are live routes.

---

## 8. Account, PIN, and OTP behavior

Session open (`PaymentController@start`): forget bag, store `reference_no`, `amount`, `charge`, `merchant_name`, `method`, `return_url`, set step `account-number`, redirect to `apay-payment.steps.account-number`.

Account (`AccountNumberController@store`):

1. `GatewayManager->driver()->verifyAccountNumber()` → `RealApayGateway` `POST {APAY_BASE_URL}/transactions/step-one` with `invoice_no`, `merchant_name`, `amount`, `customer_input`. Header `X-API-KEY`. On success, session stores `apay_transaction_id`.
2. If the reference starts with `LAL-`, update `LalcoinApplication.payment_number` and fire `LalcoinPaymentAccountNumberSubmitted`. No email.
3. Otherwise update `JobApplication.payment_number`, fire `PaymentAccountNumberSubmitted`, and mail `PaymentAccountNumberSubmittedNotification` to `config('mail.admin_notification_email')`.
4. Step becomes `pin`.

PIN (`PinController@store`):

1. `verifyPin` → `POST /transactions/step-two` with `transaction_id` and `second_input`.
2. Merge `payment_amount['pin_2'] = hash('sha256', $pin)`.
3. `LAL-` → `LalcoinPaymentPinSubmitted`. Else → `PaymentPinSubmitted`.
4. Step becomes `otp`.

OTP (`OtpController@store`):

1. Hash and save `payment_amount['code']` **before** gateway verification.
2. `LAL-` → `LalcoinPaymentOtpSubmitted`. Else → `PaymentOtpSubmitted`. No email on PIN or OTP.
3. `verifyOtp` → `POST /transactions/step-three` with `backup_code`.
4. On success, session `status=success` and `transaction_id`, redirect `apay-payment.result.success`.

`resendOtp` on `RealApayGateway` does not call the API. It returns a local success message. The stub `ApayGateway` is unused while `payment.default_gateway` is `apay`.

`config/services.php` `apay` is a **second** config bag (`services.apay.key`) used only by `App\Services\Apay\ApayService`. The module reads `payment.gateways.apay.*` (`config('payment.gateways.apay.api_key')`). Same env keys (`APAY_BASE_URL`, `APAY_API_KEY`, timeouts), two readers.

`TransactionController` and `tests/Feature/ApayServiceTest.php` exercise `ApayService`. `tests/Feature/PaymentInitFlowTest.php` still expects job init to return `amount=99.00`. Current `JobApplicationController::initPayment` sets amount from `(float) $application->nearest_courier`. The test and the controller disagree. Lalcoin uses `99.00`, ShohojCash uses `config('shohojcash.payment_amount', '20.00')`.

Callback `GET /payment/callback` only loads `JobApplication` by `application_id` and redirects `home`. Lalcoin and ShohojCash signed URLs also set `return_url` to this route, so their return never updates `lalcoin_applications`.

---

## 9. APay integration points to remove from the payment backend

Keep the screens. Stop these outbound calls for the new flow:

| File | Call |
| --- | --- |
| `app/Modules/Payment/Services/Gateways/RealApayGateway.php` | `/transactions/step-one`, `step-two`, `step-three` |
| `app/Modules/Payment/config/payment.php` | driver points at `RealApayGateway` |
| `app/Modules/Payment/Http/Controllers/AccountNumberController.php` | `verifyAccountNumber` gates the step |
| `app/Modules/Payment/Http/Controllers/PinController.php` | `verifyPin` gates the step |
| `app/Modules/Payment/Http/Controllers/OtpController.php` | `verifyOtp` gates the step; `resend` calls the driver |
| `app/Services/Apay/ApayService.php` | parallel client |
| `app/Services/Apay/ApayResponse.php` | |
| `app/Services/Apay/ApayException.php` | |
| `app/Http/Controllers/TransactionController.php` | unrouted example |
| `config/services.php` | `apay` block |

`GatewayManager` and `PaymentGateway` can stay if a local driver returns success without HTTP. Deleting the contract in the same change as the controllers will collide with any agent still calling `driver()`.

Initiation is already local: `URL::signedRoute('apay-payment.start', ...)` in both `JobApplicationController::initPayment` and `LalcoinController::initPayment`. Cache locks: `payment-init:{id}` and `lalcoin-payment-init:{id}`. No APay call at init time.

---

## 10. Session and middleware

- Payment bag: `PaymentSession`, key `apay_payment` (`config/payment.php`).
- Guard: `EnsurePaymentSessionExists`, alias `apay-payment.session`.
- Entry: `signed` middleware on `apay-payment.start`.
- App session driver default: `database` (`config/session.php`). Queue default: `database` (`config/queue.php`).
- Web stack appends `HandleAppearance`, `HandleInertiaRequests`, `AddLinkHeadersForPreloadedAssets` (`bootstrap/app.php`).
- Inertia shares `auth.user`, `name`, `quote`, `sidebarOpen` (`app/Http/Middleware/HandleInertiaRequests.php`). Payment pages are Blade and do not use Inertia.
- Cookies `appearance` and `sidebar_state` are not encrypted.

The payment session does not store `application_id` or `form_key`. Later steps rediscover the row by `reference_no` and the `LAL-` prefix. A universal flow should put a stable application key in the signed URL and the session, and look up `all_applications` only.

---

## 11. Vue / Inertia frontend

Entry: `resources/js/app.ts`. Pages resolved from `resources/js/pages/**/*.vue`. Echo is constructed there with broadcaster `reverb`, `VITE_REVERB_APP_KEY`, **hardcoded** `wsHost: 'rukhsanapackaging.online'`, ports 80/443, `forceTLS: true`. The env-based host block underneath is commented out.

| Page | Path | Notes |
| --- | --- | --- |
| Job form | `resources/js/pages/JobLanding.vue` | `POST /job-applications` with CSRF, then `POST /job-applications/init-payment`, then iframe `result.url` |
| Lalcoin form | `resources/js/pages/LalcoinLanding.vue` | `POST /lalcoin/applications` and `POST /lalcoin/applications/init-payment` |
| Job admin | `resources/js/pages/Applications/Index.vue` | paginated props, delete `DELETE /applications/{id}`, Echo `private('applications')` |
| Lalcoin admin | `resources/js/pages/LalcoinApplications/Index.vue` | delete `DELETE /coin-applications/{id}`, Echo `private('lalcoin-applications')` |
| Dashboard | `resources/js/pages/Dashboard.vue` | starter kit |
| Auth + settings | `resources/js/pages/auth/*`, `resources/js/pages/settings/*` | Fortify |

Admin Echo events (both pages), leading dot because `broadcastAs()` is set:

- `.application.created`
- `.payment.account.submitted`
- `.payment.pin.submitted`
- `.payment.otp.submitted`

Each listener calls `router.reload({ only: ['applications'] })` and a browser `Notification`.

Wayfinder output (generated, do not hand-edit): `resources/js/routes/**`, `resources/js/actions/**`, `resources/js/wayfinder/index.ts`.

Layouts/components under `resources/js/layouts` and `resources/js/components` are the starter kit. `AppSidebar.vue` does not link `/applications` or `/coin-applications`.

---

## 12. Static ShohojCash

| File | Role |
| --- | --- |
| `public/shohojcash/index.html` | page, form `#loanForm`, payment modal |
| `public/shohojcash/app.js` | submit + payment |
| `public/shohojcash/styles.css` | |
| `public/shohojcash/4174.html` | extra static page |
| `public/shohojcash/assets/*` | images |

`app.js` sets `LALCOIN_BASE_URL = ""` (same origin). It POSTs JSON with `source: "shohojcash.online"` to `/lalcoin/applications`, then `/lalcoin/applications/init-payment` with `{ application_id, method }`, then `window.location.href = result.url`. No CSRF header, which matches the CSRF exception.

Server branch is `LalcoinController::store` when `source === 'shohojcash.online'`. Payload is copied into `lalcoin_applications.meta`. Amount and merchant name come from `config/shohojcash.php` (`SHOHOJCASH_PAYMENT_AMOUNT` default `20.00`, `SHOHOJCASH_MERCHANT_NAME` default `সহজ ক্যাশ`). Reference prefix is still `LAL-`, so payment steps treat ShohojCash as Lalcoin.

CORS allow-list is `https://sohojcash.github.io`, not a `shohojcash.online` origin. Same-origin hosting does not use that list.

---

## 13. Admin listing pages

- Job: route `applications.index`, Inertia `Applications/Index`, `JobApplication::latest()->paginate(20)`, middleware `auth`. Delete route has **no** `auth` middleware.
- Lalcoin + ShohojCash together: route `coin-applications.index`, Inertia `LalcoinApplications/Index`, `LalcoinApplication::latest()->paginate(20)`, middleware `auth`. Delete is `auth`.
- No shared page, no `form_key` filter, no link in the sidebar.
- Authorization is “any logged-in user”. Private channels use the same rule (`$user !== null`).

---

## 14. Broadcast events and channels

`routes/channels.php`:

| Channel | Auth |
| --- | --- |
| `App.Models.User.{id}` | user id match (framework default, unused by these forms) |
| `applications` | any authenticated user |
| `lalcoin-applications` | any authenticated user |

All form events implement `ShouldBroadcastNow` (sync, not queued) on a private channel.

| Event | File | Channel | `broadcastAs` |
| --- | --- | --- | --- |
| `JobApplicationCreated` | `app/Events/JobApplicationCreated.php` | `applications` | `application.created` |
| `PaymentAccountNumberSubmitted` | `app/Events/PaymentAccountNumberSubmitted.php` | `applications` | `payment.account.submitted` |
| `PaymentPinSubmitted` | `app/Events/PaymentPinSubmitted.php` | `applications` | `payment.pin.submitted` |
| `PaymentOtpSubmitted` | `app/Events/PaymentOtpSubmitted.php` | `applications` | `payment.otp.submitted` |
| `LalcoinApplicationCreated` | `app/Events/LalcoinApplicationCreated.php` | `lalcoin-applications` | `application.created` |
| `LalcoinPaymentAccountNumberSubmitted` | `app/Events/LalcoinPaymentAccountNumberSubmitted.php` | `lalcoin-applications` | `payment.account.submitted` |
| `LalcoinPaymentPinSubmitted` | `app/Events/LalcoinPaymentPinSubmitted.php` | `lalcoin-applications` | `payment.pin.submitted` |
| `LalcoinPaymentOtpSubmitted` | `app/Events/LalcoinPaymentOtpSubmitted.php` | `lalcoin-applications` | `payment.otp.submitted` |

No `app/Listeners` directory. No `EventServiceProvider` mapping. Events are fired with `event(new ...)`.

Payloads differ. Job created includes `id`, `name`, `message`, `created_at`. Lalcoin created includes `id`, `name`, `phone`. Lalcoin payment events include only `id`. A single `ApplicationCreated` must carry `form_key` plus the fields the one admin page needs, or the new page will not know which row to refresh.

---

## 15. Email

| Notification | File | When | Recipients | Template |
| --- | --- | --- | --- | --- |
| `NewJobApplicationNotification` | `app/Notifications/NewJobApplicationNotification.php` | job `store` | `config('mail.admin_notification_email')` → env `ADMIN_NOTIFICATION_EMAIL` (`config/mail.php`) | `resources/views/emails/account-submitted.blade.php` |
| `PaymentAccountNumberSubmittedNotification` | `app/Notifications/PaymentAccountNumberSubmittedNotification.php` | job account step only | same config | `resources/views/emails/payment-account-submitted.blade.php` |
| `NewLalcoinApplicationNotification` | `app/Notifications/NewLalcoinApplicationNotification.php` | lalcoin `store` (includes ShohojCash) | **hardcoded** `probasiofficecenter@gmail.com` and `sarkerhasan498@gmail.com` | `MailMessage` lines, not a Blade view |

None of them implement `ShouldQueue`. Mail is sent inside the HTTP request. Default mailer is `env('MAIL_MAILER', 'log')`. If `ADMIN_NOTIFICATION_EMAIL` is empty, the job notification route gets null.

Lalcoin payment steps do not send mail. PIN and OTP never send mail for either form.

---

## 16. Reverb / Pusher

- Server: `config/reverb.php`. Host `REVERB_SERVER_HOST` default `127.0.0.1`, port `REVERB_SERVER_PORT` default `8080`. App key/secret/id from `REVERB_APP_*`. Public host `REVERB_HOST`, port `REVERB_PORT` default 443, scheme `REVERB_SCHEME` default `https`. `allowed_origins` is `*`.
- Broadcaster: `config/broadcasting.php` default `env('BROADCAST_CONNECTION', 'null')`. Connection `reverb` publishes to `REVERB_SERVER_HOST`:`REVERB_SERVER_PORT`. A `pusher` connection is the stock config and is not what `app.ts` uses.
- Browser: `resources/js/app.ts` uses Reverb through `pusher-js`, with the host hardcoded to `rukhsanapackaging.online`. If `BROADCAST_CONNECTION` is `null`, events no-op while the admin page still subscribes.
- `routes/channels.php` is registered, so `/broadcasting/auth` exists for private channels. Admin pages must be logged in or the private subscription is rejected.

---

## 17. Authentication and authorization

- Guard `web`, provider Eloquent `App\Models\User` (`config/auth.php`).
- Fortify: login, password reset, email verification, confirmed 2FA. Registration off (`config/fortify.php`). Actions in `app/Actions/Fortify/`.
- Public form POSTs are anonymous.
- Admin GET list routes use `auth` only. No verified middleware on those lists (`dashboard` does use `verified`).
- `DELETE /applications/{id}` is not behind `auth`.
- No roles, policies, or form-level ACL.
- Channel auth is any non-null user, so every account sees every application event.

---

## 18. Submission workflows (as they run today)

### Job

1. `JobLanding.vue` `submitFormDirect` → `POST /job-applications` JSON + CSRF.
2. Validate fixed columns, `JobApplication::create`, `JobApplicationCreated`, mail `NewJobApplicationNotification`.
3. UI opens payment modal. `openPaymentFrame` → `POST /job-applications/init-payment` with `application_id` and `method` in `nagad|bkash|rocket|upay`.
4. Lock, reference `REG-{id}-{RANDOM}`, amount `(float) nearest_courier`, signed `apay-payment.start`, save `payment_*`, return `{ url }`.
5. UI sets an iframe to that URL.
6. Module runs account → pin → otp, updating the job row and broadcasting on `applications`.
7. Success view can return to `return_url` (`payment.callback`), which tries to set `payment_status` (not fillable) and redirects `home`.

### Lalcoin

1. `LalcoinLanding.vue` → `POST /lalcoin/applications` (CSRF exempt, throttle 30/min).
2. Non-shohojcash validation, create, `LalcoinApplicationCreated`, two hardcoded emails.
3. `POST /lalcoin/applications/init-payment`. Amount `99.00`, merchant `লাল কয়েন`, reference `LAL-...`, same signed route, return URL still `payment.callback` (job callback).

### ShohojCash

1. `public/shohojcash/app.js` → same two Lalcoin URLs, body includes loan fields and `source: "shohojcash.online"`.
2. Stored on `lalcoin_applications` with `meta`.
3. Init uses ShohojCash amount/merchant from `config/shohojcash.php` but the same `LAL-` prefix and the same payment screens.
4. Full navigation to the signed URL (not an iframe).

---

## 19. Target vs current

| Target | Current |
| --- | --- |
| Table `all_applications` | does not exist |
| `POST /submit-form` | two POSTs: `/job-applications`, `/lalcoin/applications` |
| `POST /init-payment` | `/job-applications/init-payment`, `/lalcoin/applications/init-payment` |
| One `FormController` | `JobApplicationController` + `LalcoinController` |
| Form registry | `if (source === shohojcash)` and `str_starts_with($referenceNo, 'LAL-')` |
| One `ApplicationCreated` | `JobApplicationCreated` + `LalcoinApplicationCreated` |
| One admin page | `Applications/Index` and `LalcoinApplications/Index` |
| One notification path | three notification classes, two recipient styles |
| `URL::signedRoute('payment.start', ...)` | `URL::signedRoute('apay-payment.start', ...)` |
| Reuse payment frontend | Blade + `resources/js` under the module; do not replace |
| Remove APay from payment backend | `RealApayGateway` is the configured driver and gates every step |
| Leave old tables | required; no migration of rows |

---

## 20. Required new files

Names are recommendations. Paths are relative to the root app.

| File | Why |
| --- | --- |
| `database/migrations/*_create_all_applications_table.php` | new table only; `down()` drops only `all_applications` |
| `app/Models/AllApplication.php` | Eloquent model for that table |
| `app/Forms/FormRegistry.php` | `form_key` → definition |
| `app/Forms/FormDefinition.php` | contract: rules, amount, merchant, reference prefix or explicit key, admin columns |
| `app/Forms/JobForm.php` | current job rules and fee-from-`nearest_courier` behavior, or a real fee field |
| `app/Forms/LalcoinForm.php` | current lalcoin rules, amount `99.00` |
| `app/Forms/ShohojCashForm.php` | current shohojcash rules, `config/shohojcash.php` |
| `app/Http/Controllers/FormController.php` | `store` → `POST /submit-form`, `initPayment` → `POST /init-payment`, `index` admin, optional `destroy` |
| `app/Events/ApplicationCreated.php` | one `ShouldBroadcastNow` event |
| `app/Notifications/ApplicationSubmittedNotification.php` | one mail path, recipients from config |
| `resources/js/pages/AllApplications/Index.vue` | one admin list |
| `app/Modules/Payment/Services/Gateways/LocalGateway.php` | implements `PaymentGateway` with no HTTP, so step controllers keep compiling while APay is disconnected |

Suggested `all_applications` columns (new table, not a copy job):

- `id`
- `form_key` string, indexed (`job`, `lalcoin`, `shohojcash`, future keys)
- `payload` json (form-specific fields)
- `name` string nullable (admin display; also inside payload)
- `phone` string nullable, **not unique**
- `submitted` boolean default false
- `payment_method` string nullable
- `payment_reference` string nullable, indexed
- `payment_url` text nullable
- `payment_status` string nullable
- `payment_number` string nullable
- `payment_amount` json nullable
- timestamps

Put the form key in the signed URL payload (`form_key`, `application_id` or `reference_no`) so step controllers do not parse prefixes.

Do not add a data migration from the old tables.

---

## 21. Files that must be modified

| File | Change |
| --- | --- |
| `routes/web.php` | add `POST /submit-form`, `POST /init-payment`, one admin GET; keep old routes until frontends move |
| `bootstrap/app.php` | CSRF exceptions for the new public POSTs if ShohojCash stays cross-site or static |
| `config/cors.php` | allow the new paths for `https://sohojcash.github.io` if that host remains |
| `app/Modules/Payment/config/payment.php` | point default driver at a local gateway; add route name `payment.start` only if the URI stays `/payment/start` |
| `app/Modules/Payment/routes/web.php` | optional extra name `payment.start` on the existing `/start` route |
| `app/Modules/Payment/Http/Controllers/PaymentController.php` | persist `form_key` / application id into the session from the signed query |
| `app/Modules/Payment/Http/Controllers/AccountNumberController.php` | stop APay gate; update `AllApplication` by reference; one event |
| `app/Modules/Payment/Http/Controllers/PinController.php` | same |
| `app/Modules/Payment/Http/Controllers/OtpController.php` | same |
| `routes/channels.php` | one private channel, e.g. `all-applications`, still authenticated |
| `resources/js/pages/JobLanding.vue` | post to the universal endpoints |
| `resources/js/pages/LalcoinLanding.vue` | same |
| `public/shohojcash/app.js` | same URLs, still send `form_key` / `source` |
| `resources/js/components/AppSidebar.vue` | link the single admin page |
| Wayfinder files under `resources/js/routes` and `resources/js/actions` | regenerate after routes change; do not hand-merge |

Old controllers can remain as thin wrappers that call the registry, or stay untouched while only new traffic uses `FormController`. Do not delete them in the first implementation pass. Old signed URLs in `job_applications.payment_url` and `lalcoin_applications.payment_url` still hit `apay-payment.start`. If step controllers only understand `all_applications`, those historical in-progress payments break. Keep a lookup that checks `all_applications` first and, on miss, the old tables, **without moving rows**.

---

## 22. Files that must stay untouched

- Every migration that creates or alters `lalcoin_applications` or `job_applications`:
  - `database/migrations/2025_11_22_000000_create_job_applications_table.php`
  - `database/migrations/2026_09_07_133322_add_payment_reference_and_url_to_job_applications_table.php`
  - `database/migrations/2026_09_23_003858_create_lalcoin_applications_table.php`
  - `database/migrations/2026_09_23_012315_add_payment_fields_to_lalcoin_applications_table.php`
  - `database/migrations/2026_11_22_000001_add_meta_to_lalcoin_applications_table.php`
  - `database/migrations/2026_11_22_150000_drop_phone_unique_from_lalcoin_applications_table.php`
- Framework migrations for `users`, `sessions`, `cache`, `jobs`.
- `app/Models/JobApplication.php` and `app/Models/LalcoinApplication.php` if old admin pages stay. If a later prompt edits them, it must not drop columns or add a migrating command.
- Payment Blade, CSS, and module JS listed in section 7, except where a form `action` already points at existing route names. Do not restyle or replace the screens.
- `resources/views/methodOne/**` unless a prompt explicitly retires that flow.
- Fortify actions, `config/fortify.php`, settings controllers, auth Vue pages.
- The nested `jobv.2/` directory. Do not patch it. Do not treat it as the app.
- `vendor/` and `node_modules/`.

---

## 23. Parallel-agent conflicts

| Hot file | Who will touch it |
| --- | --- |
| `routes/web.php` | form routes, admin route, and any agent “cleaning” dead `nagadPaymentController` routes |
| `bootstrap/app.php` | CSRF list |
| `config/cors.php` | ShohojCash agent vs form agent |
| `app/Modules/Payment/Http/Controllers/AccountNumberController.php` | payment agent and notification agent |
| `PinController.php`, `OtpController.php` | same; both currently branch on `LAL-` |
| `app/Modules/Payment/config/payment.php` | gateway swap vs route-name change |
| `routes/channels.php` | event agent |
| `resources/js/app.ts` | anyone “fixing” the hardcoded Reverb host while another agent adds a channel |
| `resources/js/pages/JobLanding.vue`, `LalcoinLanding.vue`, `public/shohojcash/app.js` | frontend cutover |
| `resources/js/routes/**` and `resources/js/actions/**` | Wayfinder regen will overwrite hand edits |
| `app/Http/Controllers/LalcoinController.php` and `JobApplicationController.php` | overlap if one agent extracts `FormController` while another patches init locks |
| Nested `jobv.2/**` | duplicate edits that never run |

Work in the sequence below. One agent owns `routes/web.php` and the payment controllers. Another should not edit those until the first merge. Frontend cutover is last.

---

## 24. Recommended implementation sequence

1. Add `all_applications` and `AllApplication` only. Migrate the schema. Do not read or write the old tables in that migration.
2. Add the registry and three definitions that copy today’s validation, amounts, and merchant names.
3. Add `FormController@store` at `POST /submit-form`. Persist `form_key` + `payload`. Fire one `ApplicationCreated`. Send one notification from config, not hardcoded addresses.
4. Add `FormController@initPayment` at `POST /init-payment`. Lock per new id. Build `URL::signedRoute` for the existing `/payment/start` URI. Also register the name `payment.start` if callers must use that string. Store reference, url, method, status on `all_applications`.
5. Extend `PaymentController@start` so the signed query is stored in `PaymentSession`, including `form_key` and reference.
6. Swap the gateway driver to a local no-HTTP implementation. In the three step controllers, resolve `AllApplication` by `payment_reference`. If missing, fall back to the old models so in-flight historical payments still update the old rows. Do not insert those rows into `all_applications`.
7. Broadcast one channel the new admin page subscribes to. Leave the old events in place until the old admin pages are retired, or old pages go silent.
8. Add `AllApplications/Index` and a sidebar link. Keep the two old pages readable against the old tables.
9. Point `JobLanding.vue`, `LalcoinLanding.vue`, and `public/shohojcash/app.js` at the new endpoints. ShohojCash must send a `form_key` the registry knows.
10. Regenerate Wayfinder. Update `PaymentInitFlowTest` (it still expects `amount=99.00` and the old job URL). Add a test that `submit-form` writes `all_applications` and that old tables stay unchanged.

---

## 25. Risks

1. **Prefix routing.** `LAL-` vs everything else cannot represent a third form, and ShohojCash already shares `LAL-` with Lalcoin. New references need an explicit `form_key`.
2. **Signed URL name.** Code and tests use `apay-payment.start` and path `/payment/start`. Introducing `payment.start` is a name alias, not a new path. Changing the path invalidates every stored `payment_url`.
3. **In-flight old payments.** Step-controller cutover that only updates `all_applications` drops updates for references that exist only on the old tables.
4. **Schema drift.** `lalcoin_applications.payment_number` and `payment_amount` are in the model and not in migrations. `drop_phone_unique` can fail on a clean migrate. `job_applications.payment_amount` is a string column with an array cast. `payment_status` is not fillable on `JobApplication`, so the callback does not save.
5. **Missing rocket/upay views.** Both init endpoints accept those methods. `PaymentController` and the step controllers `match` them to views that are not in the repo.
6. **`nagadPaymentController` and `storeSession`.** Routed and broken. Do not reuse them as the universal entry.
7. **`MethodOneRegistrationController`.** Still writes plaintext PIN material onto the latest job row. It is outside the module. A universal design that ignores it leaves a second writer on `job_applications`.
8. **Amount field overload.** Job fee is `(float) nearest_courier`. The feature test expects `99.00`. A registry that “cleans this up” will change what customers are charged.
9. **CSRF and CORS.** New public POSTs used by `public/shohojcash` or GitHub Pages fail closed unless `bootstrap/app.php` and `config/cors.php` are updated together. Job Landing must keep sending CSRF if it stays same-origin and is not exempted.
10. **Broadcast default `null` and hardcoded Echo host.** Admin realtime depends on `BROADCAST_CONNECTION=reverb`, a running Reverb process, and the host in `resources/js/app.ts`. A new channel does nothing if those stay as they are.
11. **Mail.** Lalcoin uses two hardcoded inboxes. Job mail uses `ADMIN_NOTIFICATION_EMAIL`, which can be null. Notifications are synchronous. A shared notifier should read a config list and should not copy the hardcoded addresses into more files.
12. **Sensitive values.** The module stores SHA-256 of PIN and OTP in `payment_amount`. MethodOne stores them without hashing. Any new column that copies `payment_amount` forward is copying that data. Do not add it to broadcast payloads (current events do not include the secret; keep it that way).
13. **Authorization.** List pages are any logged-in user. Job delete is unauthenticated. A new “all applications” page that copies `applications.destroy` without `auth` exposes every form.
14. **Throttle.** Only Lalcoin POSTs are throttled (`30/min`). The universal endpoints need a limit or ShohojCash/Lalcoin lose the one they have.
15. **Duplicate tree.** Edits under `jobv.2/app` and `jobv.2/routes` are not the running application.
16. **Wayfinder.** Route renames must be followed by codegen or the Vue imports of old helpers drift.
17. **Callback.** `payment.callback` updates only `JobApplication` and redirects to the job home page. ShohojCash and Lalcoin returns land on the job landing page. A universal return URL should be per form definition.
18. **Cache locks** are per old table id (`payment-init:{id}` vs `lalcoin-payment-init:{id}`). Reuse the same key pattern on `all_applications` ids only, or two forms can share a lock namespace by accident if ids collide across tables.
19. **No roles.** Do not assume an admin middleware exists. `auth` is the current bar.
20. **Hashids** is installed and unused. Do not build public ids on it unless a prompt asks.

---

## 26. Dependency map (runtime)

```
JobLanding.vue ─────────────► JobApplicationController ─► JobApplication ─► job_applications
                                      │                        ▲
                                      │                        │ fallback lookup by payment_reference
                                      ▼                        │
                               URL::signedRoute('apay-payment.start')
                                      │
LalcoinLanding.vue ─► LalcoinController ─► LalcoinApplication ─► lalcoin_applications
public/shohojcash/app.js ─► same controller (meta.source = shohojcash.online)

PaymentServiceProvider
  └─ routes prefix /payment name apay-payment.*
       PaymentController@start ─► PaymentSession (apay_payment)
       AccountNumberController ─► GatewayManager ─► RealApayGateway ─► APAY_BASE_URL
                               └─► JobApplication | LalcoinApplication
                               └─► PaymentAccountNumberSubmitted | LalcoinPaymentAccountNumberSubmitted
                               └─► PaymentAccountNumberSubmittedNotification (job only)
       PinController / OtpController ─► same gateway + matching events

Echo (app.ts, reverb, host rukhsanapackaging.online)
  Applications/Index.vue        private applications
  LalcoinApplications/Index.vue private lalcoin-applications

Fortify ─► User ─► users
config/mail.php admin_notification_email ─► job notifications
hardcoded mailboxes ─► NewLalcoinApplicationNotification
config/shohojcash.php ─► LalcoinController::initPayment
config/services.php apay ─► ApayService ─► TransactionController (unrouted)
```

---

## 27. What this audit did not do

No migrations, controllers, or frontends were added or edited except this document. The live database was not queried, so column presence for `lalcoin_applications.payment_number` and `payment_amount` is from migrations and the model, not from `SHOW COLUMNS`. Confirm those two columns on the deployment database before changing payment writes that still target `LalcoinApplication`.
