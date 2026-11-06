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

## Task evidence

| Task | Evidence | Where |
|---|---|---|
| T-01 | `GET /api/v1/health` returns `{"status":"ok"}` without a token | `tests/Feature/HealthTest.php` |
| T-02 | `data-model.md` §10 queries match: columns and types, no DEFAULT, FKs, CHECKs, indexes, seeded categories; the engine rejects rows that break the CHECKs; ISBN unique only among active books | `tests/Feature/InitialMigrationTest.php` |

## Secrets

The real `.env` is ignored by git. Only `.env.example` is versioned.
