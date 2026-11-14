# Phase 4 — Python PDF Processing Worker

## Delivered

- Independent Python 3.12 FastAPI worker in `python-worker/`.
- Docker image includes Tesseract plus Bengali (`ben`) and English (`eng`) language data.
- PyMuPDF extracts native text page by page. Tesseract OCR is invoked only when the page has fewer than `PDF_TEXT_MIN_CHARS` non-whitespace native-text characters (default 25).
- OCR uses `ben+eng`, configurable DPI, timeout and maximum file size.
- OCR line/block information is reconstructed into line breaks so the existing label-based parser can recognize Bengali field labels.
- Parser is ported from `backend/voterParser.js`; raw source text remains intact and search-normalized text is stored separately.
- Page text, normalized text, OCR-used flag, confidence and status are saved in `processing_pages`.
- Page processing is streamed and committed incrementally. Completed pages are skipped on retry.
- `processing_page_records` tracks worker-created voter rows. Retry cleanup removes only rows created by the worker; imported/legacy rows are not deleted or overwritten.
- If a migrated legacy page already contains voter records, the worker retains those rows and does not insert duplicates.
- Private processing endpoint requires a bearer token and accepts a document ID only.
- Supports local shared-volume paths constrained to `SHARED_STORAGE_ROOT` and private S3-compatible `s3://bucket/key` objects. Object downloads are bucket-constrained and size-limited.
- Tests cover Bengali parser fields, multiple records, gender normalization, native-text no-OCR behavior, OCR fallback, OCR line reconstruction, path traversal rejection and API token protection.

## Database prerequisite

Apply all Laravel migrations, including:
- `processing_pages`
- `processing_page_records`

The worker uses the same MySQL database as Laravel. It does not run migrations itself.

## Render deployment

Create a separate Docker Web Service with Dockerfile path `python-worker/Dockerfile`. Use a private/internal service URL and configure:

- `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `WORKER_API_TOKEN` (strong random secret)
- `OBJECT_STORAGE_ENDPOINT`, `OBJECT_STORAGE_BUCKET`, `OBJECT_STORAGE_REGION`, `OBJECT_STORAGE_ACCESS_KEY`, `OBJECT_STORAGE_SECRET_KEY`
- `MAX_PDF_MB`, `OCR_DPI`, `OCR_TIMEOUT_SECONDS` as required

For separate Render services, private S3-compatible storage is the recommended PDF handoff. Do not assume two Render services share a persistent disk. Laravel Phase 5 will upload private PDF objects and save their `s3://bucket/key` references in `documents.stored_path`.

## API

- `GET /health`
- `GET /ready`
- `POST /internal/process` with JSON `{"document_id": 123}` and `Authorization: Bearer <WORKER_API_TOKEN>`

This endpoint is synchronous in Phase 4. Laravel queue dispatch, durable retries, upload integration, and user-facing progress APIs are Phase 5.

## Known limitations and gates

- OCR quality depends on scan resolution, skew, print quality and layout; the OCR confidence is a signal, not a guarantee of correct field extraction.
- Password-protected PDFs are rejected.
- The parser preserves the current label-based behavior; representative original voter PDFs must be reviewed before parser changes.
- Documents with existing legacy voter rows are not reparsed into duplicate rows. A future controlled reparse/mapping tool is needed if those records must be replaced.
- No production files have been uploaded, downloaded, changed, or migrated. No production database was accessed.
- CI has been configured to run Python tests and build the worker container; report results only after the current GitHub Actions run finishes.
