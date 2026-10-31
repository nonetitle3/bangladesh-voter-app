# Phase 0 — Existing System Audit

Repository: `nonetitle3/bangladesh-voter-app`
Audit baseline: `main` (read-only)
Migration branch: `laravel-hybrid-migration`
Target architecture: Laravel + MySQL + Python PDF worker + existing React + Render.

## 1. Safety decision

- The original `main` branch is not modified by this migration branch.
- The earlier `php-mysql-render` branch is a prototype only. It is not the target architecture and must not be deployed as the replacement.
- No production database, uploaded-PDF volume, Render environment variable, admin password, JWT secret, or live account store was available to this repository-file audit. These must be backed up and inventoried through the relevant provider before migration.
- Do not merge/cut over until data parity, authentication compatibility, PDF/OCR tests, and rollback are verified.

## 2. Repository inventory observed

### Backend (Node/Express)
- `backend/server.js`: Express app, CORS, 100 MB JSON limit, public administrative hierarchy endpoints, global search, voter browse, stats, startup initialization.
- `backend/adminAuth.js`: environment-based admin login; supports `ADMIN_USERNAME`, `ADMIN_PASSWORD`, legacy bcrypt `ADMIN_PASSWORD_HASH`, JWT `JWT_SECRET`; JWT role claim and 8-hour expiry.
- `backend/adminRoutes.js`: config-status, login, me, database route mounting, PDF preview, preview retrieval, preview confirmation. Important: current preview confirmation explicitly does not write records to the database.
- `backend/voterRoutes.js`: authenticated PDF upload, in-memory job status, record listing/search filters, dashboard aggregation, CSV export, Excel-compatible XLS export.
- `backend/voterDatabase.js`: SQLite database at `backend/storage/voters.sqlite`; tables `users`, `documents`, `voter_records`; indexes on name/father, location, voter ID.
- `backend/pdfImporter.js`: native PDF text extraction and voter parsing for preview workflow.
- `backend/pdfProcessor.js`: page split and parser flow; includes Tesseract.js worker helper, but current page processing does not actually render scanned pages and invoke OCR.
- `backend/voterParser.js`: Bengali/English label parsing, gender normalization, voter record segmentation.
- `backend/data/**`: JSON hierarchy and voter data used by public hierarchy/search endpoints.

### Frontend (React)
- `frontend/src/App.js`: public Bengali voter search/browse UI; division → district → upazila → union → ward cascading filters; gender and age filters; global search; stats.
- `frontend/src/AdminPanel.js`: admin login, dashboard overview, PDF upload/preview, system status. Its current API default points to the separate Python service `https://bangladesh-voter-python.onrender.com/api`.
- `frontend/package.json`: Create React App 5 / React 18.

### Deployment
- `backend/render.yaml`: Node runtime, npm install, Node server, persistent disk mounted at `/opt/render/project/src/data`.
- Existing deployment configuration is Node-based, not Laravel/PHP.

## 3. Existing API inventory

### Public API (Express server)
- `GET /`
- `GET /api/health`
- `GET /api/divisions`
- `GET /api/districts/:division`
- `GET /api/upazilas/:division/:district`
- `GET /api/unions/:division/:district/:upazila`
- `GET /api/wards/:division/:district/:upazila/:union`
- `GET /api/voters/:division/:district/:upazila/:union/:ward` (gender, ageFrom, ageTo, page, limit)
- `GET /api/search` (q, division, district, upazila, union, ward, gender, ageFrom, ageTo, limit)
- `GET /api/stats`

### Admin API (mounted at `/api/admin`)
- `GET /api/admin/config-status`
- `POST /api/admin/login`
- `GET /api/admin/me`
- `POST /api/admin/preview-pdf`
- `GET /api/admin/preview/:id`
- `POST /api/admin/preview/:id/confirm`
- Database-backed voter routes mounted under this prefix:
  - `POST /api/admin/upload`
  - `GET /api/admin/upload/:id`
  - `GET /api/admin/records`
  - `GET /api/admin/dashboard`
  - `GET /api/admin/export/csv`
  - `GET /api/admin/export/excel`

## 4. Existing database mapping (repository schema)

| Existing table | Existing fields / notes | Laravel target |
|---|---|---|
| users | id, username, email, password, role, is_active, created_at | users (preserve primary key and password hash) |
| documents | id, filename, stored_path, page_count, status, ocr_used, error_msg, uploaded_at | documents |
| voter_records | id, document_id, voter_id, serial_no, name, father_name, mother_name, birth_date, gender, occupation, address, village, ward, union_name, upazila, district, division, post_code, pdf_filename, page_number, raw_text, confidence, created_at | voter_records |

SQLite is the current code-defined database. The actual live SQLite file and any external DB must be separately inspected and backed up. The `users` table is created but the currently observed admin login uses environment variables rather than a DB user lookup.

## 5. Feature preservation matrix

| Feature | Observed state | Migration requirement |
|---|---|---|
| Admin environment login | Present | Preserve variable names and JWT claim/expiry compatibility |
| DB user/role fields | Schema present; user-management API not observed in inspected routes | Audit full UI/API and implement only verified workflows |
| Public hierarchy browse | JSON-backed | Keep response shapes and URL patterns |
| Global/partial search | JSON hierarchy search + DB admin search | Preserve fields, filters, age behavior and output aliases |
| PDF preview | Native text extraction parser | Keep preview API and response shape |
| Preview confirmation | Explicitly does not write to DB | Do not claim import is active; implement confirmed import with tests |
| PDF upload | Async in-process job; 100 files, 50 MB/file | Replace with durable queue + Python worker and persistent artifacts |
| Scanned PDF OCR | Tesseract helper exists, but processPdf does not call OCR for scanned pages | Implement page render, selective OCR, Bengali+English |
| Page-level progress | callback exists in processor; upload route does not persist page rows/progress | Add processing_pages table and durable status |
| Dashboard | DB counts, district, occupation, gender | Preserve and extend with document processing metrics |
| CSV export | Present | Preserve query filters and Bengali UTF-8 BOM |
| Excel export | Present as SpreadsheetML .xls, not true XLSX | Preserve legacy endpoint; offer tested XLSX separately |
| PDF download/source | PDF storage path exists; dedicated download route not confirmed | Audit remaining UI and add safe authenticated download/page preview |
| Frontend workflows | Public React search and admin login/preview | Keep React UI; switch API only after contract tests |
| Admin user management | Not confirmed in inspected backend/UI | Search full repository and deployed app before asserting absence |

## 6. Migration risks and blockers

1. **Live data not inspected:** Git repository does not expose Render secrets, production SQLite contents, uploaded files on the Render disk, or provider backups.
2. **Credentials:** Existing admin login is env-based. Preserve `ADMIN_USERNAME`, `ADMIN_PASSWORD`, `ADMIN_PASSWORD_HASH`, and `JWT_SECRET`; never rotate or overwrite during migration. Do not copy secrets into Git.
3. **Legacy storage path:** PDFs currently use `backend/storage/pdfs`; the Render disk in `render.yaml` is mounted at `/opt/render/project/src/data`. Confirm actual mount and file inventory before migration.
4. **PDF pipeline gap:** Current worker helper does not perform scanned-page OCR in the main page processing loop. This is a known gap to implement, not functionality to silently remove.
5. **Import confirmation gap:** Existing preview confirmation currently only acknowledges records and does not persist them.
6. **Data source split:** Public search reads JSON files; admin records read SQLite. Migration must import both JSON hierarchy records and SQLite rows without duplicate loss, after inspecting actual data counts and IDs.
7. **Render architecture:** Deploy Laravel and Python worker as separate services. Queue and MySQL must be reachable by both. PDF storage must use a shared persistent disk or object storage; ephemeral container storage is not acceptable for production uploads.
8. **MySQL provider:** Select a MySQL-compatible external provider. Do not treat Render PostgreSQL as MySQL.

## 7. Phase 1 implementation baseline

Recommended Laravel components:
- Laravel current stable release compatible with PHP 8.3.
- MySQL 8 / InnoDB / utf8mb4.
- Laravel Sanctum or JWT bridge selected after frontend token compatibility tests. Existing JWT uses HS256, `role=admin`, `username`, and 8-hour expiry.
- Queue-backed PDF jobs; Python worker consumes durable jobs and writes page-level results through a protected worker API or shared queue/database contract.
- React remains separate and uses `REACT_APP_API_URL` to point to Laravel after contract tests.

## 8. Phase 0 completion boundary

This audit covers repository-visible code and schema only. It is not a live-data audit. Phase 2 migration cannot be declared safe until the owner exports and verifies:
- SQLite database backup and row counts for all three tables.
- Full uploaded-PDF directory backup and file manifest/checksums.
- Render environment variable names/config-status (values must remain private).
- JSON hierarchy file inventory and voter counts.
- Any separate Python service data or preview artifacts that the live admin frontend currently uses.

## 9. Acceptance gates

- Original `main` branch and production service remain untouched until cutover.
- Source and destination counts match by table; IDs and relationships validate.
- Existing admin login succeeds with unchanged secrets.
- API contract tests pass for all listed endpoints.
- Bengali UTF-8 text round-trips exactly.
- Native-text pages skip OCR; scanned pages OCR only where needed.
- Reprocessing a completed page is idempotent.
- PDF downloads and source-page references resolve after deployment.
- Rollback to the old service and backups is documented and rehearsed.
