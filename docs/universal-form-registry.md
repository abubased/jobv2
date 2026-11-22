# Universal form registry

A standard form is one PHP file in `app/Modules/Forms/Configurations/`. The registry scans that directory. It does not keep a hand-written list of forms.

Public URL: `/forms/{slug}`

Submission: `POST /submit-form`

Payment start: `POST /init-payment`

Admin list: `GET /all-applications` (authenticated)

Rows go to `all_applications` through `App\Models\Application`. `lalcoin_applications` and `job_applications` are not read or written by this path.

## Add a form

1. Add one PHP file that returns the array below. `EducationLoan.php` is already in the configurations directory as a complete disabled example.
2. Set `enabled` to `true`.
3. In production run `php artisan forms:cache`. Locally the cache is off, so the next request discovers the file. `php artisan forms:clear` drops a stale cache.

No new controller, route, migration, table, or Vue file is required. `/forms/education-loan` starts rendering `resources/js/pages/Forms/Show.vue` when the example is enabled.

## Configuration schema

| Key | Required | Meaning |
| --- | --- | --- |
| `slug` | yes | Public identifier. Lowercase kebab-case. Used when the key is omitted as the kebab-case filename. |
| `form_name` | yes | Stored on the row and shown as the title. |
| `description` | yes | Shown under the title. May be empty. |
| `enabled` | yes | `false` skips the file after it has been validated. |
| `reference_prefix` | yes | 2–10 characters, starts with a letter, uppercase. Reference becomes `{prefix}-{id}-{random}`. |
| `merchant_name` | yes | Server-side merchant. The client cannot replace it. |
| `fields` | yes | Form-specific inputs. Not `name`, `phone`, or `address`. |
| `validation_rules` | yes | Laravel rules. Keys are `name`, `phone`, `address`, or a field name. Field-name rules override that field's `validation`. |
| `payment_amount` | yes | Fixed amount, or `null` when the amount comes from a field. |
| `payment_amount_source` | yes | `type` is `fixed`, `config`, or `field`. |
| `accepted_payment_methods` | yes | Method slugs. Empty plus `settings.payment_enabled` false means no payment step. |
| `merchant_notification_emails` | yes | List of addresses. |
| `admin_notification_email` | yes | One address or `null`. |
| `settings` | yes | Renderer and server options. |

`payment_amount_source`:

- `fixed` uses `payment_amount`.
- `config` uses `key` (a `config()` key) and falls back to `payment_amount`.
- `field` uses `field`. The value must be in `allowed`, or in that field's options when `allowed` is omitted. Anything else is rejected. `loanAmount` is not a fee.

`settings` keys the renderer understands:

- `payment_enabled`
- `common_fields` labels, placeholders, and address `type`
- `compose_address_from`: ordered form-data keys joined into `address` when the client does not send one

Field keys: `name`, `label`, `type`, `placeholder`, `required`, `default`, `options`, `validation`, `help_text`, `section`, `order`.

Types: `text`, `email`, `number`, `tel`, `date`, `textarea`, `select`, `radio`, `checkbox`, `hidden`.

Options are `{value, label}` lists. `store: false` validates the field and leaves it out of `form_data`. `locked: true` stores `default` even if the client sends another value.

`name`, `phone`, and `address` are columns. Every other stored answer is `form_data`.

The submission API ignores client `payment_amount`, `merchant_name`, `payment_reference`, and `payment_status`.

## Registered forms

| File | Slug | Prefix | Fee |
| --- | --- | --- | --- |
| `Lalcoin.php` | `lalcoin` | `LAL` | Fixed `99.00` |
| `JobApplication.php` | `job-application` | `JOB` | `nearest_courier`, only `20` or `99` |
| `ShohojCash.php` | `shohojcash` | `LOAN` | `config('shohojcash.payment_amount')`, default `20.00` |
| `EducationLoan.php` | `education-loan` | `EDU` | Fixed `150.00`, disabled |

Job `nearest_courier` is still the registration fee. On `/forms/job-application` it is a server allow-list, not a free-typed amount. The older `JobLanding.vue` page is unchanged.

ShohojCash address is `road, thana, district, division`. `loanAmount` stays in `form_data`.

Payment initialization signs the existing `apay-payment.start` URL (`GET /payment/start`). Account, PIN, and OTP screens are still the payment module. Those screens still attach follow-up data by the legacy `LAL-` / job reference lookup, so a `JOB-` or `LOAN-` reference is not written back onto `all_applications` by the current step controllers.

## EducationLoan.php

The complete example is `app/Modules/Forms/Configurations/EducationLoan.php`. It is valid and `enabled` is `false`, so `/forms/education-loan` returns 404 until that flag is turned on.

Publishing it:

1. Open `app/Modules/Forms/Configurations/EducationLoan.php`.
2. Set `'enabled' => true`.
3. Run `php artisan forms:cache` when `FORM_REGISTRY_CACHE` or production caching is on.

The form is then available at `/forms/education-loan`, validates through `POST /submit-form`, stores in `all_applications` with `form_slug` `education-loan`, numbers references `EDU-{id}-{random}`, charges `150.00`, and appears on `/all-applications`.

Cache behavior lives in `config/forms.php`. `forms:cache` rebuilds the cache. `forms:clear` deletes it. Invalid files throw while `APP_DEBUG` is on, or while the app is `local` or `testing`. In production with debug off, an invalid file is logged and skipped. Duplicate slugs are rejected the same way.
