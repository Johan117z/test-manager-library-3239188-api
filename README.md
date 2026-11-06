# test-manager-library-3239188-api

Backend of **Manager Library** in **Laravel 13 / PHP 8.3**, implementing the spec in
[`test-manager-library-3239188-docs`](https://github.com/code-sena/test-manager-library-3239188-docs) (`spec-python/`)
and the team decisions in `team/spec-gaps-and-decisions.md`.

> Exam: ADSO 3239188, Thursday 2026-11-05, 7:00 p.m. to 11:00 p.m. (Colombia).

## Requirements

Only Docker. PHP and Composer run inside the image built from `Dockerfile` (`php:8.3-cli` + `pdo_pgsql` + Composer).
The database is the empty PostgreSQL 16 from the **infra** repo; this API joins its network `manager-library_library-net`.

## Run

```bash
# 1. Database (from the infra repo)
cd ../test-manager-library-3239188-infra && cp .env.example .env && docker compose up -d

# 2. API
cd ../test-manager-library-3239188-api
cp .env.example .env                      # set DB_PASSWORD = infra POSTGRES_PASSWORD, JWT_SECRET, ADMIN_PASSWORD
docker compose run --rm api composer install
docker compose run --rm api php artisan key:generate
docker compose run --rm api php artisan migrate
docker compose up -d                       # http://localhost:8000/api/v1/health -> {"status":"ok"}
```

## Tests

```bash
docker compose run --rm api php artisan test
```

Tests run against the real PostgreSQL in a separate database, `library_test`, which `tests/bootstrap.php` creates if it is
missing. Only the database is created there; every table comes from the migrations.

## Schema

The schema belongs to the Laravel migrations (ADR-001). `database/migrations/2026_11_05_000001_create_library_schema.php` creates:

- schema `library` with the six tables of `data-model.md` §3 (`category`, `book`, `member`, `user`, `loan`, `loan_item`), with **no column DEFAULT**;
- the five FKs (RESTRICT, except `loan_item.loan_id` CASCADE); `loan_item.category_name` has no FK on purpose (ADR-004);
- unique indexes: `category.name`, `member.document_number`, `user.username`, `(loan_id, book_id)`, and `book.isbn` **partial** on active books;
- access indexes from §4;
- CHECKs `ck_book_available_range`, `ck_book_total_positive`, `ck_user_role` (the last two moved forward from T-18, team decision #7);
- the five categories with their fixed ids (§9).

Laravel's own `migrations` bookkeeping table is kept in `public`, so schema `library` contains exactly the six tables.

## Startup

The container runs `php artisan migrate --force`, then `php artisan library:ensure-admin` (creates the admin from
`ADMIN_USERNAME` / `ADMIN_PASSWORD` if missing; never changes an existing user; fails if they are not set), then the server.

## Structure

| Layer | Folder | Depends on |
|---|---|---|
| Domain | `app/Domain` (`Book`, `Isbn`, `Category`, `User`, `Role`, `BusinessRuleViolation`, `Uuid`) | plain PHP only |
| Application | `app/Application` (use cases + `Ports/`) | Domain |
| Adapters | `app/Infrastructure` (PostgreSQL, Argon2id, JWT, disk covers), `app/Http` (controllers, middleware, error renderer) | Application |
| Composition | `app/Providers/AppServiceProvider.php` | the only place a port meets its adapter |

## Task evidence

| Task | Evidence | Where |
|---|---|---|
| T-01 | `GET /api/v1/health` returns `{"status":"ok"}` without a token | `tests/Feature/HealthTest.php` |
| T-02 | `data-model.md` §10 queries match: columns and types, no DEFAULT, FKs, CHECKs, indexes, seeded categories; the engine rejects rows that break the CHECKs; ISBN unique only among active books | `tests/Feature/InitialMigrationTest.php` |
| T-14 | Argon2id hash; JWT HS256 with 8 h, only `sub`/`role`; expired or tampered tokens rejected; the password never appears in responses or `laravel.log` (RNF-3) | `tests/Unit/Security/SecurityAdaptersTest.php`, `AuthApiTest::test_password_never_appears_in_responses_or_logs` |
| T-07 | HU-01 and HU-02 against fakes; same over HTTP: 401 without token, 403 wrong role, `"Ana "` -> `ana`, 409 duplicate, 422 for role `admin` | `tests/Unit/Auth/AuthServicesTest.php`, `tests/Feature/AuthApiTest.php` |
| T-17 | On an empty database the startup command creates the admin, who can log in; idempotent; fails without credentials | `tests/Feature/InitialAdminTest.php` (also checked live: dropped schema, `docker compose up`, login as admin) |
| T-08 | HU-03 and HU-04 against fakes; same over HTTP: contract shape, 13-digit ISBN, 409 `ISBN_TAKEN`, total change moves available and never below 0, soft delete (404 after, row kept, ISBN reusable, 409 with open loans), search by text/category, order by title, pagination with total, size capped at 50 | `tests/Unit/Books/BookServicesTest.php`, `tests/Feature/BooksApiTest.php` |
| T-15 | 2 MB PNG accepted and served back; 3 MB -> 422; no cover -> `cover_key` null and GET 404; type checked by content; replace/remove delete the old binary after saving | `tests/Feature/CoversApiTest.php` (also checked live with real multipart uploads) |
| HU-05 | Members: create (409 `DOCUMENT_TAKEN`), list/search by name or document, detail with `active_loans` | checked live (no dedicated test, by request) |
| T-09 | HU-06: contract shape, frozen title/ISBN/category, due in 14 days, copies taken; 1-3 lines and repeated book 422; limit of 3 counting requested lines with current count; overdue 409; error order of team decision #5; no copies 409 and **nothing** saved | `tests/Feature/LendApiTest.php` |
| T-13 | Last copy (RNF-2): two real parallel loans give exactly one 201 and one 409, `available_copies` ends at 0 (5 of 5 rounds); plus a deterministic race test of the conditional UPDATE | `docker compose run --rm api php tests/Concurrency/last_copy.php`, `LendApiTest::test_conditional_update_stops_a_race_on_the_last_copy` |
| T-10 | Return twice -> 409 `ITEM_ALREADY_RETURNED`, copy added once; status `open`/`overdue`/`closed` computed; overdue lines with `days_late`; list filters; inverted range 422 | `tests/Feature/ReturnAndOverdueTest.php` |
| T-11 | Report groups by frozen values: old title kept after a rename (ADR-004); end day inclusive; empty range -> `rows: []`; inverted range 422; admin only | `tests/Feature/MostBorrowedReportTest.php` |
| T-16 | Every protected route: 401 without/with bad token, 403 for the wrong role; 404/422/500 all with exactly `{code, message, details}` and no traces, SQL or paths | `tests/Feature/ErrorContractTest.php` |

## Decisions made while implementing

Besides `team/spec-gaps-and-decisions.md`:

| Topic | Decision |
|---|---|
| 401 for a missing, invalid or expired token | The contract only lists `INVALID_CREDENTIALS` for 401, so it is reused (with its own message) instead of inventing a code. |
| `GET /categories` body | Plain JSON array of `{id, name}` ordered by name (the contract does not give the shape; it is not paginated). |
| Unknown `category_id` | On create/update: 422 `VALIDATION_ERROR` on `category_id`. As a search filter: just no results. |
| Malformed ids in the URL | 404 `NOT_FOUND`, the same as an unknown id. |
| Cover upload method | PHP does not parse `multipart/form-data` on PUT. Clients send **`POST /books/{id}/cover` with form field `_method=PUT`** (Laravel method spoofing), which reaches the PUT route. A raw PUT with multipart answers 422 (`file` required). |
| `DELETE /books/{id}/cover` | 204; also 204 when the book had no cover. `PUT` returns 200 with the book. |
| Cover keys | 32 random hex chars, file stored under `COVERS_DIR`; the key is validated so it can never be a path. |
| Logging | Business errors (409/422/...) are not logged, and PHP runs with `zend.exception_ignore_args=On`, so arguments such as passwords never reach stack traces. A test found the leak before this was set. |
| Concurrency on books | `available_copies` is written as an atomic delta (`available_copies + n`) so editing a book never overwrites a concurrent loan; the CHECK is the last barrier. |
| Unknown `member_id` or `book_id` in `POST /loans` | 422 `VALIDATION_ERROR` on that field (step 1 "request shape" of team decision #5), not 404: the URL resource exists, the body is wrong. |
| Error details for loans | `MEMBER_LIMIT_EXCEEDED` details: `{"field":"items","issue":"sin devolver: N"}` (N = current count, CA-06.4). `BOOK_NOT_AVAILABLE` details: `items[i].book_id` / `sin ejemplares`, as in the contract example. |
| `loaned_at` | Truncated to whole seconds, so `due_at` is exactly `loaned_at + 14 days` in the `...:00Z` format of the contract. |
| `GET /loans` `from` / `to` | Optional dates `YYYY-MM-DD`, inclusive like the report (team decision #6). |
| `GET /loans/overdue` body | Paginated envelope `{items, page, size, total}`; each item has exactly `loan_id, item_id, book_title, member_name, due_at, days_late`. Ordered by `due_at` ascending (= most days late first). |
| `days_late` | Started days, rounded up: one hour past `due_at` is 1 day late. |
| Wrong HTTP method on a known path | 404 `NOT_FOUND` with the single body (the contract has no 405). |
| Unexpected server error | 500 with the single body and code `INTERNAL_ERROR` (not in the contract table, which lists no 500); never a trace, SQL or path. |

## Known limitations

- A true `PUT` with a multipart body is not parsed (see cover upload above). PHP 8.4's `request_parse_body()` would fix it; the image is PHP 8.3.
- The limit of 3 unreturned books and the overdue check are **domain only** (as `data-model.md` §4 says). Two loans for the **same member** sent at the very same instant could both pass the check and leave the member with more than 3. The last-copy race (RNF-2) is covered; this one is not.
- The concurrent last-copy check (`tests/Concurrency/last_copy.php`) is a script against the running API, not part of `php artisan test`, because PHPUnit runs requests one at a time. `LendApiTest::test_conditional_update_stops_a_race_on_the_last_copy` covers the same barrier deterministically inside the suite.
- No architecture tests (T-19), skipped on purpose. No load test for RNF-1 (search under 300 ms with 5 000 books).
- `GET /members` and `GET /loans` have no extra indexes beyond `data-model.md` §4.

## Secrets

The real `.env` is ignored by git. Only `.env.example` is versioned.
