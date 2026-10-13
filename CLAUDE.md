# CLAUDE.md

This repository holds a multi-tenant CMS for school websites. The full specification is in `SPEC.md`.

## How to work in this repo

- Read `SPEC.md` before starting any work. Work on one phase from section 10 at a time, and do only that phase.
- At the end of a phase, run the tests, make sure they pass, and summarize what was built and anything left open.
- If the spec is unclear or seems wrong, ask before guessing.

## Repository layout

- `central/`: Laravel + Filament app (dashboard and public API)
- `school-site/`: plain PHP public website, one copy per school

## Conventions: central app

- Laravel and Filament, current stable versions; Pest for tests.
- SQLite in every environment, with WAL mode enabled. In production the database file lives outside the folder that deployments replace.
- Every content model uses the `BelongsToSchool` trait (global scope by school plus automatic `school_id` on create).
- Never accept `school_id` from request or form input.
- Write Pest feature tests for every policy and every API endpoint. Run `php artisan test` before finishing a task.
- All files go through Laravel's `Storage` facade on a configurable disk. Never use fixed file system paths.
- Images: resize to max 2000 px and strip metadata on upload; never store the phone original.
- No queues: `QUEUE_CONNECTION=sync`.
- UI text in Croatian; code, comments and commit messages in English.

## Conventions: school site

- Plain PHP 8.1+. No frameworks, no Composer, no build step.
- Escape all output with `e()`; the only exception is article and page `body`, which arrives sanitized from the API.
- One API call per page view, made server-side with cURL.

## Running locally

- Central app: `cd central && php artisan serve` (http://localhost:8000)
- School site: `cd school-site && php -S localhost:8081 index.php`
