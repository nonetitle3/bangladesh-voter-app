# Bangladesh Voter App — PHP + MySQL (Render)

The PHP implementation lives on branch `php-mysql-render`. Existing Node/React files remain unchanged.

## Render deployment

1. Provision a MySQL 8 database with a provider that accepts connections from Render. Render's standard managed database is PostgreSQL, not MySQL.
2. Create a Render **Web Service** connected to this repository.
3. Runtime: **Docker**. Leave **Root Directory** empty (repository root). Set **Dockerfile Path** to `php/Dockerfile`.
4. Add environment variables:
   - `DB_HOST`, `DB_PORT=3306`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`
   - `JWT_SECRET` (long random secret), `ADMIN_USERNAME`, `ADMIN_PASSWORD`
   - `CORS_ORIGIN` (your frontend origin; use `*` only during testing)
   - `APP_DEBUG=false`
5. Import `php/database/schema.sql` into MySQL. The schema is not run automatically, to avoid making unexpected changes to an existing database.
6. Deploy and test `/api/health`, then `/api/admin/login`.

## Preserved interfaces

- Public: `/api/divisions`, `/api/districts/:division`, `/api/upazilas/:division/:district`, `/api/unions/...`, `/api/wards/...`, `/api/voters/...`, `/api/search`, `/api/stats`.
- Admin: `/api/admin/login`, `/api/admin/me`, `/api/admin/dashboard`, `/api/admin/records`, PDF preview/import confirmation, CSV and XLSX export.
- Admin credentials continue to use the existing `ADMIN_USERNAME`, `ADMIN_PASSWORD`, optional `ADMIN_PASSWORD_HASH`, and `JWT_SECRET` environment variable names.

## Data and storage warnings

- The existing `backend/data` JSON hierarchy is copied into the image and remains readable by the public hierarchy endpoints. The JSON files are not automatically imported into MySQL; a validated migration is a separate step.
- PDF uploads are stored in the container filesystem. Render filesystems are ephemeral unless a persistent disk is attached; attach persistent storage or back up uploads before production use.
- OCR uses Poppler and Bengali/English Tesseract. Large PDF batches can exceed the memory/CPU limits of a free instance. Test with small representative PDFs first.
- The existing React admin preview client still points at the previous Python API and expects `/api/admin/preview-pdf`. Before switching its API URL, the frontend compatibility pass must be completed. Do not switch production traffic yet.

## Validation status

This branch is an initial implementation slice, not a production cutover. Build the Docker image, apply the schema to a staging database, test text and scanned Bengali PDFs, verify import rollback/export and compare search results before moving the live service.
