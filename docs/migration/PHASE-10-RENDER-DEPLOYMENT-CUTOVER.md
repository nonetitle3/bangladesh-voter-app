# Phase 10 — Render Staging Deployment and Cutover Runbook

## Status and safety boundary

This phase adds a Render Blueprint and deployment runbook. The Blueprint creates **staging-only** services:
- Laravel API web service
- Laravel database queue background worker
- Python PDF worker private service
- React static frontend

No Render service has been created from this repository, no credentials were entered, no production database was accessed, no old PDF was copied, and no production traffic was changed. Creating the services requires the repository owner to connect the GitHub repository and supply secrets in Render.

The Blueprint intentionally names every service with `-staging`. Do not rename these to production names or point them at the old production database.

## Files added or changed

- `render.yaml`: four staging services and environment-variable prompts.
- `php-hybrid/Dockerfile`: exposes port 10000 and invokes a Render-aware Apache entrypoint.
- `php-hybrid/docker-entrypoint.sh`: binds Apache to the Render `PORT` value (defaults to 10000).
- `php-hybrid/app/Jobs/ProcessVoterDocument.php`: accepts Render's private-service `hostport` binding, adding the HTTP scheme when needed.

The Python worker is a Render private service, not a public web service. The Laravel API and queue worker receive its internal host:port from Render's `fromService` binding. The static React site has an SPA rewrite to `index.html`.

## Required external services

### MySQL
Render's built-in relational database is PostgreSQL, not MySQL. Create/use an external MySQL 8-compatible provider and configure:
- Database charset/collation: `utf8mb4` / `utf8mb4_unicode_ci`
- A dedicated least-privilege database and application user
- TLS if supported by the provider
- Network access from Render's outbound IPs or the provider's supported network integration
- Automated backups and a tested restore procedure

Use an empty **staging** schema for first deployment. Do not import the production SQLite database until a separate backup and migration dry-run have been reviewed.

### Private object storage
Create a private S3-compatible bucket for staging PDFs. Use the same bucket, endpoint, region and credentials in the Laravel API, Laravel queue worker and Python worker. The Python worker must have:
- `OBJECT_STORAGE_ENDPOINT`
- `OBJECT_STORAGE_BUCKET`
- `OBJECT_STORAGE_REGION`
- `OBJECT_STORAGE_ACCESS_KEY`
- `OBJECT_STORAGE_SECRET_KEY`

Laravel uses `AWS_BUCKET`, `AWS_ENDPOINT`, `AWS_DEFAULT_REGION`, `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY` and `AWS_USE_PATH_STYLE_ENDPOINT=true`. Never make this bucket public.

## Render Blueprint setup

1. Open Render Dashboard → **New** → **Blueprint**.
2. Connect GitHub repository `nonetitle3/bangladesh-voter-app`.
3. Select branch `laravel-hybrid-migration` (not `main`).
4. Render reads the root `render.yaml`. Review all four resources; they must retain the `-staging` suffix.
5. Supply all prompted secret/environment values before the first successful deploy.
6. Deploy in the same Render region so private-service networking is available.

Blueprint file: `render.yaml`.

## Environment variables

Set the following consistently:

| Variable | Laravel API | Queue worker | Python private worker |
|---|---:|---:|---:|
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Same staging MySQL | Same staging MySQL | Same staging MySQL |
| `DB_CHARSET` | `utf8mb4` | `utf8mb4` | `utf8mb4` |
| `APP_KEY` | Same generated staging key | Same key | — |
| `JWT_SECRET` | Same staging secret | Same secret | — |
| `PYTHON_WORKER_URL` | Render private hostport binding | Render private hostport binding | — |
| `PYTHON_WORKER_TOKEN` | Same random token | Same random token | — |
| `WORKER_API_TOKEN` | — | — | Same token as above |
| S3 endpoint, bucket, region and credentials | `AWS_*` | `AWS_*` | `OBJECT_STORAGE_*` |

Additional Laravel API values:
- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://<Laravel staging service>.onrender.com`
- `CORS_ALLOWED_ORIGINS=https://<React staging static site>`
- `QUEUE_CONNECTION=database`
- `DB_QUEUE=default`
- `DB_QUEUE_RETRY_AFTER=1900`
- `FILESYSTEM_DISK=s3`
- `PDF_MAX_FILE_MB=50`
- `PDF_MAX_FILES_PER_UPLOAD=100`

Generate a unique staging `APP_KEY` using `php artisan key:generate --show` in a local copy of the Laravel app. Generate separate high-entropy random values for staging `JWT_SECRET` and `PYTHON_WORKER_TOKEN` (for example `openssl rand -hex 32`). Put the **same** APP_KEY and JWT_SECRET in both Laravel services, and the **same** worker token in Laravel API, queue and Python worker. Never paste secrets into GitHub files, commits, issue comments or chat.

For the staging legacy admin, set a staging-only username and password (or a compatible staging password hash). Do not use production credentials during staging tests. At cutover, preserve the existing production admin username/password/hash and JWT secret exactly; do not rotate them as part of migration.

## Deployment order and commands

The Blueprint creates the Laravel web service with a pre-deploy command:
`php artisan migrate --force`

This applies schema migrations to the **staging MySQL database only**. It does not import legacy records.

After services are live, run these checks from the Render Shell where available, or from a secure machine with the staging environment configured:

```bash
cd php-hybrid
php artisan about
php artisan migrate:status
php artisan route:list --path=api
```

Do not run `voters:import-sqlite --execute` against production as part of deployment. First create and verify a full backup, then use a copy of the SQLite database for a dry-run and parity report.

## Staging smoke tests

Replace placeholders with actual staging URLs:

```bash
curl -fsS https://<laravel-staging>.onrender.com/api/health
curl -fsS https://<python-worker-private-host>:10000/health
```

The Python private service is not publicly addressable; check it from another Render service or the Render private network. `/ready` also checks the worker's MySQL connectivity.

Then verify in order:
1. Admin login with staging-only credentials; confirm `/api/admin/me`.
2. Dashboard totals, document status counts, division/district/gender/occupation aggregates.
3. Create a disposable viewer and admin; verify admin-only API access and account protections.
4. Upload a small text-based PDF; confirm it enters the Laravel database queue and worker completes it.
5. Upload a scanned Bengali PDF; confirm native text is preferred where available and OCR uses `ben+eng` only as fallback.
6. Check page-level processing status, document download and retry of a deliberately failed staging job.
7. Search Bengali and English partial names, voter ID, parent names and combined location filters.
8. Export CSV and Excel; inspect Bengali Unicode and spreadsheet-safe values.
9. Restart/redeploy each service and verify database records and private PDFs remain present.
10. Restore the staging database and object bucket from backups to a separate staging target; verify recovery.

Never use real citizen data for routine test uploads unless it has been lawfully approved and protected. Prefer sanitized sample PDFs.

## Frontend

The Blueprint builds the React site from `frontend/`. Set build-time:
`REACT_APP_API_URL=https://<Laravel staging service>.onrender.com/api`

The React Admin page is integrated with Laravel. **The public voter search page and hierarchy routes still use the legacy API contract.** Do not advertise the new static frontend as the public voter search site or direct normal users to it until public API parity is completed and verified. Use staging admin testing only.

## Cost and free-tier constraints

The current Blueprint selects Render's legacy `starter` compute plan for the Laravel API, Laravel queue worker and Python private service. Current Render pricing lists this plan at **$7/month per continuously running service** (0.5 CPU / 512 MB RAM), so the three compute services are approximately **$21/month** before external MySQL, object storage, bandwidth and any workspace charges. The React static site has no compute plan and is free to deploy. Verify current rates before creating services.

This architecture is not fully free on Render:
- Background workers and private services do not have a free compute tier.
- Free web services cannot attach persistent disks and spin down after inactivity.
- OCR at 512 MB RAM may be constrained; use representative large/scanned PDFs and monitor memory. Increase the worker plan if staging shows OOMs or timeouts.
- MySQL and S3-compatible storage are separate provider costs.

## Cutover gate — all items required

- [ ] Public search, age/gender filters and division/district/upazila/union/ward hierarchy APIs are migrated and tested.
- [ ] Viewer read-only permissions and UI are implemented and verified.
- [ ] Any required legacy preview/confirm workflow is restored.
- [ ] Full backup of original SQLite database, all PDFs and hierarchy JSON is stored offsite and restore-tested.
- [ ] Dry-run import against a copy of the real database reports exact row counts, IDs, password hashes, Bengali text and document references.
- [ ] Production MySQL import is executed once, under change control, only after parity approval.
- [ ] Production object storage is private and all old PDF references are verified.
- [ ] Worker, queue, retry, OCR accuracy, large PDFs and failure recovery pass staging.
- [ ] Existing production admin credentials and JWT secret are preserved and verified.
- [ ] DNS/frontend cutover plan, maintenance window, rollback and read-only freeze of old writes are approved.
- [ ] Post-cutover smoke tests and monitoring are ready.

## Rollback

Keep the original Node service, original SQLite database and original PDF files untouched and available. If any cutover validation fails:
1. Stop new writes to the PHP system.
2. Restore traffic to the old application.
3. Keep all new PHP database and object-storage artifacts for diagnosis; do not delete them.
4. Reconcile any records created during the test/cutover window before attempting again.

**Current result:** deployment configuration and runbook are committed to the migration branch. No actual Render deployment, production data import or public traffic cutover has been performed.
