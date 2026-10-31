# Bangladesh Voter App — PHP + MySQL (Render)

This is a parallel PHP implementation on branch `php-mysql-render`. The existing Node backend and React frontend are left intact while the PHP API is validated.

## Included API

- Public hierarchy endpoints: `/api/divisions`, `/api/districts/:division`, `/api/upazilas/:division/:district`, `/api/unions/:division/:district/:upazila`, `/api/wards/...`, `/api/voters/...`
- Public search and stats: `/api/search`, `/api/stats`
- Admin login and token verification: `/api/admin/login`, `/api/admin/me`
- Admin dashboard, searchable/paginated records, PDF preview/import confirmation, CSV and XLSX export.

## Render deployment

1. Create a MySQL 8 database with a provider that allows connections from Render. Obtain host, port, database, username and password.
2. In Render, create a **Web Service** from this repository and select **Docker**.
3. Set **Root Directory** to `php`; Dockerfile path `Dockerfile`.
4. Add environment variables:
   - `DB_HOST`, `DB_PORT` (usually 3306), `DB_NAME`, `DB_USER`, `DB_PASSWORD`
   - `JWT_SECRET` (long random secret), `ADMIN_USERNAME`, `ADMIN_PASSWORD`
   - `CORS_ORIGIN` (your frontend origin, or `*` while testing)
   - `APP_DEBUG=false`
5. Deploy. Open `/api/health` to check the service.
6. Import `database/schema.sql` into the MySQL database before using admin endpoints.

## Existing data

The API reads the legacy JSON hierarchy from `../backend/data` when that directory is present in the container. Database-backed records are stored in MySQL. The Docker image includes the existing `backend/data` folder only if you add it to the Docker build context; for Render with root directory `php`, that sibling folder is outside the context. Therefore copy the legacy data into `php/legacy-data` before deployment if you need JSON hierarchy browsing, or migrate the JSON records to MySQL.

PDF text extraction uses Smalot PDF Parser. Scanned PDFs attempt OCR using `pdftoppm` + Tesseract Bengali/English language packs. OCR is CPU/RAM intensive; process modest batches on the free Render instance.

## Notes

- PDF uploads are stored under `php/storage/pdfs`; Render filesystem is ephemeral unless a persistent disk is attached. Back up PDFs and use persistent storage before production imports.
- This is a first implementation slice; run staging tests against representative voter PDFs before replacing the current production service.
- Existing frontend admin preview uses `/api/admin/preview-pdf` with multipart field `files`; the PHP endpoint in this branch is `/api/admin/upload`. A compatibility alias and UI/API URL switch are included in the next integration step.
