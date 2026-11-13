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
- The worker and Laravel container must mount the same Render persistent disk at the same absolute path (default `/var/data`).
- Worker environment must contain the same DB connection values and a separate strong `WORKER_API_TOKEN`.
- The PDF storage root must contain the original PDF paths stored in `documents.stored_path`.

Render worker service should be a separate Docker Web Service built with `python-worker/Dockerfile`. Set `SHARED_STORAGE_ROOT=/var/data` and attach the same shared storage only if the selected Render service/disk arrangement supports it. If Render cannot mount one disk to both services, use a private object-storage bucket and add a signed download strategy before production; do not pretend local container files are shared.

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

The API accepts document IDs only, never arbitrary file paths. It does not expose uploaded PDFs or OCR output publicly.

## Limitations to validate before production

- Tesseract Bengali OCR quality depends on scan resolution, skew, print quality and page layout. This is a first-pass OCR engine, not a guarantee of perfect voter field extraction.
- The existing parser is label-based and may not understand every PDF layout. Inspect representative original PDFs before changing parsing or normalization.
- The current phase processes one document synchronously per internal request. Phase 5 adds Laravel queue dispatch, retries, progress APIs and upload integration.
- Password-protected PDFs are rejected with an explicit error.
