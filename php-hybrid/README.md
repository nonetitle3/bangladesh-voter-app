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