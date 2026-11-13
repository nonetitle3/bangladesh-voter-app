# Bangladesh Voter PDF Worker (Phase 4)

Independent Python 3.12 service for page-by-page extraction and OCR. It shares the Laravel MySQL database and the same persistent PDF storage volume. It does not own authentication, user management, uploads, or public voter APIs.

## Processing policy

1. Resolve the stored PDF path strictly inside `SHARED_STORAGE_ROOT`.
2. Extract native text from each page using PyMuPDF.
3. If a page has fewer than `PDF_TEXT_MIN_CHARS` non-whitespace characters (default 25), render that page and run Tesseract `ben+eng`.
4. Store extracted text, conservative normalized text, OCR flag, confidence and status separately in `processing_pages`.
5. Parse using the same label-driven field rules as the existing `backend/voterParser.js`. The original text is retained in `raw_text`; no dictionary-only spelling corrections are applied.
6. Commit page results incrementally. Completed pages are skipped on retry; failed/incomplete pages can be retried.
7. Update the parent document status and OCR-used flag.

## Required shared infrastructure

- MySQL 8 database with Phase 2 migrations already applied.
- Both services must connect to the same MySQL database and use the same strong `WORKER_API_TOKEN` contract (Laravel stores it as `PYTHON_WORKER_TOKEN`; worker stores it as `WORKER_API_TOKEN`).
- **Recommended for Render:** use a private S3-compatible bucket (for example, Cloudflare R2) because separate Render services do not automatically share a local filesystem or persistent disk. Configure `OBJECT_STORAGE_*` in the worker and Laravel's S3 disk in the upload phase. Store the private object key in `documents.stored_path` as `s3://<bucket>/<key>`.
- Local filesystem mode is supported only when both containers genuinely see the same mounted volume at the same absolute path; do not assume separate Render services share `/var/data`.

Deploy the worker as a separate Render Docker Web Service using `python-worker/Dockerfile`, with a private/internal service URL. Add its DB settings, `WORKER_API_TOKEN`, and private object-storage credentials as Render environment variables. Never commit those values.

## Run locally

```bash
cd python-worker
python -m venv .venv
. .venv/bin/activate
pip install -r requirements.txt
cp .env.example .env
# Fill DB values, WORKER_API_TOKEN and shared path; install Tesseract with ben and eng language data.
uvicorn app.main:app --host 0.0.0.0 --port 8000
pytest -q
```

## Internal API

- `GET /health`: process health, no database credentials returned.
- `GET /ready`: database connectivity check.
- `POST /internal/process`: body `{"document_id": 123}`, header `Authorization: Bearer <WORKER_API_TOKEN>`.

The API accepts document IDs only, never arbitrary file paths. It does not expose uploaded PDFs or OCR output publicly. It supports either a validated local path under `SHARED_STORAGE_ROOT` or a private `s3://bucket/key` reference constrained to the configured bucket.

## Limitations to validate before production

- Tesseract Bengali OCR quality depends on scan resolution, skew, print quality and page layout. This is a first-pass OCR engine, not a guarantee of perfect voter field extraction.
- The existing parser is label-based and may not understand every PDF layout. Inspect representative original PDFs before changing parsing or normalization.
- The worker accepts processing requests asynchronously. Laravel's durable database queue owns retries and polls the authenticated status endpoint. The worker's in-process background task is not a durable queue; if the worker restarts, the Laravel job retries and already-completed pages are skipped.
- The worker expects the Phase 2 `processing_pages` and Phase 4 `processing_page_records` migrations to be applied before it processes a document.
- Password-protected PDFs are rejected with an explicit error.
