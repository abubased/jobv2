# `all_applications` schema

This table is the only store for the universal form system. It does not receive rows from `lalcoin_applications` or `job_applications`. Those tables, their migrations, and their existing rows stay as they are.

Model: `app/Models/Application.php` (`protected $table = 'all_applications'`).

Migration: `database/migrations/2026_11_22_180000_create_all_applications_table.php`.

`down()` drops only `all_applications`.

## Columns

| Column | Type | Null | Default | Notes |
| --- | --- | --- | --- | --- |
| id | bigint unsigned | no | auto increment | primary key |
| form_slug | string(64) | no | | stable key such as `job-application`, `lalcoin`, `shohojcash`. Indexed. Length stays under MySQL's index byte limit. |
| form_name | string | no | | label stored with the row |
| name | string | no | | common applicant name |
| phone | string(32) | no | | duplicates allowed; indexed, not unique |
| address | text | no | | common address |
| form_data | json | yes | | form-specific fields only |
| payment_method | string(32) | yes | | |
| payment_reference | string(64) | yes | | indexed, not unique |
| payment_url | text | yes | | signed payment URL can be long |
| payment_status | string(32) | yes | | indexed, not unique |
| payment_amount | decimal(10, 2) | yes | | fee charged for this application, not a JSON blob |
| merchant_name | string | yes | | |
| submitted | boolean | no | `true` | |
| created_at / updated_at | timestamps | yes | | |

Indexes (all non-unique): `form_slug`, `phone`, `payment_reference`, `payment_status`.

There is no per-form table and no foreign key to `users`. The model defines no relationships.

## Casts

| Attribute | Cast |
| --- | --- |
| form_data | `array` |
| payment_amount | `decimal:2` (string, two fractional digits) |
| submitted | `boolean` |

## Scopes

| Scope | Effect |
| --- | --- |
| `forForm($formSlug)` | `where form_slug` |
| `submitted()` | `where submitted = true` |
| `paymentStatus($status)` | `where payment_status` |

## What goes in `form_data`

Common fields (`name`, `phone`, `address`) are columns. Everything that used to be form-specific stays under its existing key.

Lalcoin (`form_slug` `lalcoin`), matching `LalcoinController` non-ShohojCash fields:

```json
{
  "coin_count": "100",
  "coin_year": "2026"
}
```

Job (`form_slug` `job`), matching `JobApplicationController` fields that are not name, phone, or address:

```json
{
  "father_or_husband": "Father Name",
  "mother": "Mother Name",
  "education": "bachelor",
  "courier_help": "yes",
  "nearest_courier": "99"
}
```

`nearest_courier` keeps its current meaning from the job form. On the legacy table that same value is also cast to a payment fee. On this table the fee belongs in `payment_amount` (`decimal(10, 2)`), for example `99.00`.

ShohojCash (`form_slug` `shohojcash`), matching the keys `LalcoinController` writes into `lalcoin_applications.meta`:

```json
{
  "fatherName": "Father Name",
  "motherName": "Mother Name",
  "dob": "1995-01-01",
  "nid": "1234567890",
  "division": "Dhaka",
  "district": "Dhaka",
  "thana": "Uttara",
  "road": "Road 10",
  "loanType": "ব্যক্তিগত ঋণ",
  "loanAmount": 50000,
  "duration": 6,
  "source": "shohojcash.online"
}
```

Live ShohojCash `loanType` values are `ব্যক্তিগত ঋণ`, `শিক্ষা সহায়তা ঋণ`, `নতুন উদ্যোক্তা ঋণ`, and `কৃষি ঋণ`. `loanAmount` is one of `5000, 10000, 20000, 25000, 30000, 40000, 50000, 75000, 100000, 150000, 200000, 250000, 300000, 400000, 500000`. `duration` is `3`, `6`, or `12`.

`payment_amount` is only the fee (`99.00` for Lalcoin, `20.00` from `config/shohojcash.php` for ShohojCash, and the job fee once payment starts). It is not the legacy `payment_amount` JSON that holds PIN and OTP material on the old tables.

## Legacy tables

`job_applications` and `lalcoin_applications` are not read, copied, altered, or dropped by this migration.
