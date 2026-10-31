# Laravel Hybrid Migration — Phase 1

This is a parallel Laravel foundation. The existing Node/React application and previous prototype branches are not modified.

## Runtime
- PHP 8.3 / Laravel 12
- MySQL via PDO
- Existing admin JWT contract (HS256, 8-hour expiry, role=admin)
- Render Docker Web Service

## Local setup
1. Copy `.env.example` to `.env`.
2. Set DB and existing admin/JWT environment variables. Do not generate replacements for production secrets.
3. Run `composer install`.
4. Run `php artisan key:generate` only for a new Laravel app key; this does not change the existing JWT secret.
5. Run `php artisan serve`.

## Render
- Create a Web Service from this branch.
- Runtime: Docker.
- Dockerfile path: `php-hybrid/Dockerfile`; build context/root directory: `php-hybrid`.
- Configure DB_CONNECTION=mysql, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD.
- Preserve existing ADMIN_USERNAME, ADMIN_PASSWORD or ADMIN_PASSWORD_HASH, and JWT_SECRET values exactly.
- Attach persistent storage at the configured PDF storage path in a later PDF phase. Do not upload production documents until persistent storage and worker are deployed.

## API smoke checks
- `GET /api/health`
- `GET /api/admin/config-status`
- `POST /api/admin/login` JSON: username/password
- `GET /api/admin/me` with Authorization: Bearer <existing-compatible-token>

## Phase 1 scope
This foundation provides Laravel bootstrap, MySQL PDO configuration through Laravel, health check, rate-limited legacy-compatible admin login, and JWT-protected /me. It does not yet replace voter/search/PDF endpoints. Those are added in subsequent phases after database migration and API contract tests.
