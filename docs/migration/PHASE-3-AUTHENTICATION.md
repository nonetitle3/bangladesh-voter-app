# Phase 3 — Authentication and User Management

## Preserved legacy authentication contract

- Existing environment variables remain supported: `ADMIN_USERNAME`, `ADMIN_PASSWORD`, `ADMIN_PASSWORD_HASH`, `JWT_SECRET`.
- Environment admin login continues to issue HS256 JWTs with `iat`, `exp` (8 hours), `username`, and `role=admin`; it does not add a `sub` claim, so existing React/Node clients and tokens remain compatible.
- If the username matches the configured environment admin, a wrong password is rejected and does not fall through to a database account with the same username.
- Database users authenticate using Laravel's configured password hasher (bcrypt by default). Their JWTs include a `sub` user ID, so deactivation or role change revokes access on the next protected request.
- Existing legacy JWTs without `sub` remain accepted for compatibility until their normal expiry. Their validity is controlled by the unchanged JWT secret and expiry.

## User management API

All routes below are under `/api/admin` and require an admin JWT.

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/users` | Paginated user list; optional `q`, `role`, `is_active`, `per_page` |
| POST | `/users` | Create admin/viewer user |
| GET | `/users/{id}` | Read user metadata |
| PUT/PATCH | `/users/{id}` | Update username/email/role/status/password |
| DELETE | `/users/{id}` | Delete user |

Passwords are hashed before storage and are never returned by API responses. New passwords must be at least 12 characters. The last active database administrator cannot be demoted, deactivated, or deleted. A database user cannot deactivate, demote, or delete their own account.

## Important compatibility and safety notes

- The original admin environment account remains the bootstrap/super-admin path and is not inserted into or overwritten in the `users` table.
- Imported user IDs and password hashes remain unchanged by Phase 2. No password reset, seed admin, or default credentials are created.
- Legacy JWTs do not have a database subject and therefore cannot be individually revoked; they expire naturally (8 hours). Rotating `JWT_SECRET` would revoke them, but must not happen during this migration.
- Role values are `admin` and `viewer`. Admin-only routes return 403 to viewers. Viewer read access to voter records will be attached to the read-only endpoints in later API phases.
- Database password hashing uses Laravel's bcrypt default. Verify imported hash formats against a protected staging copy before enabling database-user login for imported accounts.
- Configure Laravel environment values through Render environment settings. Never commit real secrets or put them in GitHub Actions logs.

## Verification

The feature tests cover legacy environment login/JWT claims, database viewer login and role denial, inactive-account rejection, database admin user creation without password leakage, and revocation of database-subject tokens after deactivation.

Run locally in `php-hybrid`:

```bash
composer install
php artisan test
php artisan route:list --path=api/admin
```

No production credentials, users, or live service settings have been changed.
