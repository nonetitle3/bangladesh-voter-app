# Laravel Hybrid Migration — Phase 1

Laravel 12 / PHP 8.3 API foundation, preserving the existing HS256 admin JWT contract.

## Render setup
Create a Docker Web Service. Set Root Directory to `php-hybrid` and Dockerfile path to `Dockerfile`. Configure MySQL 8 connection variables and preserve existing `ADMIN_USERNAME`, `ADMIN_PASSWORD` or `ADMIN_PASSWORD_HASH`, and `JWT_SECRET` values exactly. Set a new Laravel `APP_KEY` separately; it is not the JWT secret.

Set `APP_DEBUG=false`. Set `CORS_ALLOWED_ORIGINS` to the deployed React origin. Configure a persistent disk before enabling PDF uploads.

## Local
`composer install`; copy `.env.example` to `.env`; set environment variables; run `php artisan key:generate`; run `php artisan serve`.

## API
- `GET /api/health`
- `GET /api/admin/config-status`
- `POST /api/admin/login` JSON username/password
- `GET /api/admin/me` Authorization Bearer token

This phase is foundation only. Existing voter/search/PDF endpoints are not cut over until their Laravel equivalents and contract tests are ready.

## Phase 2 — safe legacy data migration

See [Phase 2 preservation runbook](../docs/migration/PHASE-2-DATABASE-PRESERVATION.md) and [Phase 0 audit](../docs/migration/PHASE-0-SYSTEM-AUDIT.md).

Commands added:
- `php artisan migrate --force` — create Laravel MySQL tables.
- `php artisan voters:import-sqlite /secure/path/voters.sqlite` — dry run only.
- `php artisan voters:import-sqlite /secure/path/voters.sqlite --execute` — import after backup review, only into a staging database.
- `php artisan voters:verify-sqlite /secure/path/voters.sqlite` — compare row counts, IDs and mapped values.

Create the source backup with `python3 scripts/backup_legacy_voter_data.py` as documented in the runbook. The import command refuses unknown source columns and orphaned document references rather than silently dropping or detaching data. It never truncates or deletes source rows.
