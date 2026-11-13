# Phase 5 — Laravel PDF Upload and Python Worker Integration

## Delivered

- Private PDF upload endpoint for single or multiple PDFs (up to 100 files; 50 MB per file by default).
- Randomized, sanitized object keys under `voter-pdfs/YYYY/MM/`; uploaded files remain private.
- S3-compatible object storage for separate Render services; local storage is available only when a genuinely shared volume is configured.
- Durable Laravel database queue jobs, one per PDF.
- Upload batch tracking with total, processed, records, failures and batch status.
- Authenticated document list/detail, page progress, private download and retry endpoints.
- Python worker now accepts a job asynchronously and exposes authenticated processing status.
- Laravel queue job polls worker status; retries transient failures up to three attempts.
- Completed documents are not reprocessed; worker resumes by skipping already completed pages.
- Rollback cleanup removes uploaded objects if a multi-file upload batch fails before the batch is accepted.
- Added Laravel feature tests for upload, authorization, batch status, private download and retry behavior.

## API routes

All routes below require an admin JWT bearer token.

| Method | Route | Purpose |
|---|---|---|
| POST | `/api/admin/upload` | Upload one or more PDF files in `files[]`; returns HTTP 202 and `jobId` |
| GET | `/api/admin/upload/{jobId}` | Batch and per-document progress |
| GET | `/api/admin/documents` | Paginated documents; optional status, q, page_size |
| GET | `/api/admin/documents/{id}` | Document detail and record/page counts |
| GET | `/api/admin/documents/{id}/pages` | Page-level status and OCR metadata |
| GET | `/api/admin/documents/{id}/download` | Authenticated private PDF download |
| POST | `/api/admin/documents/{id}/retry` | Retry failed/pending document |

Upload response example:

```json
{
  "success": true,
  "jobId": "UUID",
  "total": 2,
  "documents": [
    {"id": 21, "filename": "sample.pdf", "status": "pending"}
  ]
}
```

## Render deployment

Deploy three independent services from this repository:

1. Laravel web: root directory `php-hybrid`, Dockerfile `Dockerfile`.
2. Laravel queue worker: root directory `php-hybrid`, same Dockerfile, start command:
   `php artisan queue:work database --queue=default --sleep=3 --tries=3 --timeout=1600`.
3. Python PDF worker: root directory `python-worker`, Dockerfile `Dockerfile`, private/internal URL.

All services must connect to the same MySQL database. Apply migrations before starting uploads:

```bash
php artisan migrate --force
```

Set Laravel:
- `QUEUE_CONNECTION=database`
- `DB_QUEUE_RETRY_AFTER=1900`
- `FILESYSTEM_DISK=s3`
- `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`, `AWS_ENDPOINT`
- `PYTHON_WORKER_URL`, `PYTHON_WORKER_TOKEN`, `PYTHON_WORKER_TIMEOUT=1500`

Set Python worker:
- Same MySQL `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- Same worker token as Laravel's `PYTHON_WORKER_TOKEN` in `WORKER_API_TOKEN`
- Matching `OBJECT_STORAGE_BUCKET`, `OBJECT_STORAGE_ENDPOINT`, `OBJECT_STORAGE_REGION`, `OBJECT_STORAGE_ACCESS_KEY`, `OBJECT_STORAGE_SECRET_KEY`

Use a private S3-compatible bucket. Do not enable public access. Do not assume Render web and worker services share local filesystems. A local PDF disk is suitable only when both services mount the same volume at the same absolute path.

## Queue and worker behavior

- Laravel's database queue is the durable retry owner.
- Worker `POST /internal/process` returns HTTP 202 and starts a background task.
- Worker `GET /internal/status/{document_id}` reports the shared MySQL document/page status.
- Laravel polls every five seconds and stops on completed/failed status or timeout.
- Worker background tasks are process-local and not independently durable. If a worker restarts mid-document, the Laravel job retries and the processor skips pages already marked completed.
- Job tries: 3; backoff: 30, 120, 300 seconds; job timeout: 1600 seconds; queue retry_after: 1900 seconds.

## Important limitations

- No live Render upload, MySQL database, production PDF or object bucket was accessed.
- No production records, admin credentials, JWT secret, existing PDFs or frontend files were changed.
- React is not yet connected to these new endpoints; frontend integration is a later phase.
- Existing Node preview/confirm flow is not yet ported; it remains untouched on the old application.
- Full voter search, exports and dashboard APIs are still pending.
- Before production cutover, verify S3 credentials, Render internal networking, long-running worker behavior, real PDFs, page progress, retries, and download authorization in staging.
