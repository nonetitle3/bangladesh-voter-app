# Phase 2 — Database and Data Preservation

## Scope

This phase adds Laravel/MySQL migrations, Eloquent models, a non-destructive backup utility, a dry-run-first SQLite importer, and a row-level parity verifier. It does not connect to or alter the live Render database or files.

## Legacy schema confirmed from source

- `users(id, username, email, password, role, is_active, created_at)`
- `documents(id, filename, stored_path, page_count, status, ocr_used, error_msg, uploaded_at)`
- `voter_records(id, document_id, voter_id, serial_no, name, father_name, mother_name, birth_date, gender, occupation, address, village, ward, union_name, upazila, district, division, post_code, pdf_filename, page_number, raw_text, confidence, created_at)`

The importer keeps original primary IDs and password hash strings. It does not create/reset admin accounts, rotate secrets, delete source files, truncate tables, or overwrite an existing row. If a destination primary key exists, mapped values must match or import aborts.

## 1. Create a consistent backup bundle

Run this on a copy of the old application environment where the actual SQLite/PDF/JSON files are accessible:

```bash
python3 scripts/backup_legacy_voter_data.py \
  --sqlite backend/storage/voters.sqlite \
  --pdf-dir backend/storage/pdfs \
  --json-dir backend/data \
  --output ./migration-backups
```

The script uses Python SQLite's online backup API (including committed WAL content), verifies `PRAGMA integrity_check`, copies PDFs and JSON files, computes SHA-256 checksums, and writes a manifest. It does not modify source files. If the actual Render mount paths differ, pass the actual paths; do not guess them.

Keep at least two encrypted copies of the resulting backup outside the Render service. Do not upload secrets or `.env` files into this bundle or GitHub.

## 2. Prepare a separate, empty MySQL database

Use a new MySQL 8 database/schema for migration rehearsal. Never point this command at the production database before a verified backup and explicit cutover approval.

Set Laravel's MySQL environment variables and run:

```bash
cd php-hybrid
composer install
cp .env.example .env
# Set DB_* to the staging database; preserve existing ADMIN_* and JWT_SECRET values.
php artisan key:generate
php artisan migrate --force
```

The Laravel APP_KEY is independent from JWT_SECRET. Never generate a new production JWT secret as part of this phase.

## 3. Dry-run import

Copy `voters.sqlite` from the backup bundle to a protected location accessible to the Laravel container. First run:

```bash
php artisan voters:import-sqlite /secure/path/voters.sqlite
```

Without `--execute`, the command is a dry run and performs no writes. It checks SQLite integrity and required tables and reports source counts.

## 4. Execute staging import

Only after reviewing the backup manifest and confirming the destination is the empty staging database:

```bash
php artisan voters:import-sqlite /secure/path/voters.sqlite --execute
```

Import order is users → documents → voter_records to satisfy foreign keys. It runs in batches, retains IDs and password hash strings, skips identical existing IDs on a rerun, and refuses conflicting values. The source database is opened read-only. An audit row is written for each imported table.

## 5. Verify

Run:

```bash
php artisan voters:verify-sqlite /secure/path/voters.sqlite
```

This compares row counts, primary IDs and every mapped field against MySQL. A mismatch is a hard failure; do not cut over. Also compare the backup manifest counts and PDF SHA-256 manifest. Review orphaned document references and inspect Bengali text samples in the target DB.

## Important limitations / next work

- This source-code audit found no extra columns beyond the schema listed above. The importer deliberately maps only known fields; inspect the actual live SQLite `PRAGMA table_info` before production and extend mappings if the live DB has additional fields.
- Legacy public hierarchy is stored in JSON files, not these SQLite tables. The backup tool preserves those JSON files and their checksums, but Phase 2 does not yet normalize them into MySQL or switch public hierarchy APIs.
- PDF files are copied into the backup bundle, but their live Render storage paths are not rewritten. A later storage migration must verify every file hash and map document paths to persistent object/disk storage before cutover.
- Existing `users` table is code-defined but current admin login is environment-based. Existing env credentials and JWT secret remain separate and unchanged.
- `processing_pages` is a new table for the Python worker; old source schema has no per-page status rows, so none are fabricated.
- No live production data has been imported or modified by this repository change.
