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


## Phase 5 — PDF upload, storage, queue and worker integration

### API (all routes require an admin bearer token)

- `POST /api/admin/upload` — multipart form with one or more `files[]` PDF fields; returns HTTP 202 and `jobId`.
- `GET /api/admin/upload/{jobId}` — batch status and per-document status.
- `GET /api/admin/documents` — paginated document list; optional `status`, `q`, `page_size`.
- `GET /api/admin/documents/{id}` — document details and counts.
- `GET /api/admin/documents/{id}/pages` — page-level processing metadata.
- `GET /api/admin/documents/{id}/download` — authenticated private PDF download.
- `POST /api/admin/documents/{id}/retry` — retry failed/pending document; completed documents are not reprocessed.

### Render service layout

Deploy these as separate services from the same repository:

1. **Laravel Web Service** — Dockerfile `php-hybrid/Dockerfile`, root directory `php-hybrid`.
2. **Laravel Queue Background Worker** — same Dockerfile and root directory; start command:
   `php artisan queue:work database --queue=default --sleep=3 --tries=3 --timeout=1600`.
3. **Python PDF Worker Web Service** — Dockerfile `python-worker/Dockerfile`, root directory `python-worker`; use its private/internal service URL in `PYTHON_WORKER_URL`.

Set `QUEUE_CONNECTION=database`, `DB_QUEUE_RETRY_AFTER=1900` and run `php artisan migrate --force` before enabling uploads. The queue background worker must use the same MySQL connection as the Laravel web service.

### Private object storage

For separate Render services, use a private S3-compatible bucket (Cloudflare R2 is one option). Set Laravel `FILESYSTEM_DISK=s3`, `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`, and `AWS_ENDPOINT`. Set the same bucket and credentials in the Python worker using `OBJECT_STORAGE_BUCKET`, `OBJECT_STORAGE_ACCESS_KEY`, `OBJECT_STORAGE_SECRET_KEY`, `OBJECT_STORAGE_ENDPOINT`, and `OBJECT_STORAGE_REGION`.

PDF objects are stored under randomized `voter-pdfs/YYYY/MM/<uuid>-<safe-filename>` keys and remain private. MySQL stores the private reference as `s3://bucket/key`. The worker downloads each object into a temporary directory, enforces the configured file-size limit and removes the temporary copy after processing.

Do not use a public bucket. Do not assume Render web and background services share a local disk. The `pdf_local` disk is only for development or a deployment where the worker truly mounts the same volume at the same absolute path; configure `SHARED_STORAGE_ROOT` accordingly.

### Processing and retry behavior

Laravel creates one durable database queue job per uploaded PDF. Each job calls the worker's authenticated internal enqueue endpoint and polls its authenticated status endpoint. The worker performs OCR in the background and commits page results incrementally. Laravel queue retries transient failures up to three attempts; completed documents are skipped. User-triggered retry is available for failed/pending documents.

The worker process's background task is not itself a durable queue. Durability and retry ownership remain with Laravel's database queue; after a worker restart the Laravel job will retry the document, and completed pages are skipped.

### Deployment safety

- Keep the original Render environment-admin username/password/hash and JWT secret unchanged.
- Do not deploy uploads until all three services share the same MySQL database, S3 bucket and worker token.
- The old Node endpoints and React frontend are not switched over in this phase.
- No live PDFs or production data were migrated by these commits.


## Phase 6 — voter search, dashboard and export APIs

Routes (admin bearer JWT required):
- `GET /api/admin/records` — filtered voter search and pagination.
- `GET /api/admin/dashboard` — total records/PDFs and district, occupation, gender breakdowns.
- `GET /api/admin/export/csv` — streaming UTF-8 CSV with BOM and spreadsheet formula protection.
- `GET /api/admin/export/excel` — streaming Excel-compatible SpreadsheetML `.xls`.
- Legacy aliases are also available under `/api/voter-search/records`, `/dashboard`, `/export/csv` and `/export/excel`.

Supported query parameters: `q`, `name`, `father_name`, `mother_name`, `voter_id`, `serial_no`, `district`, `upazila`, `union`/`union_name`, `ward`, `division`, `village`, `address`, `post_code`, `occupation`, `gender`, `page_number`, `document_id`, `page`, `page_size`/`per_page`.

Search uses parameter-bound partial-match filters and a fixed column allow-list. Pagination defaults to 50 rows and is capped at 200. Both exports share the same filters and stream database chunks. CSV is UTF-8 with BOM; Excel output is SpreadsheetML with Bengali text preserved.

See `docs/migration/PHASE-6-SEARCH-EXPORT.md` for the API contract, examples, compatibility notes and performance caveats. Existing hierarchy JSON endpoints and the separate JSON-file search have not yet been ported; React integration is Phase 7.
