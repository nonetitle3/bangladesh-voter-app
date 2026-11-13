# Phase 6 — Voter Search, Dashboard and Export APIs

## Delivered

- MySQL-backed voter record search with bound query values and allow-listed SQL columns.
- Global partial search across voter ID, serial number, Bengali/English names, father/mother names, administrative location, address, occupation and source PDF filename.
- Individual partial-match filters for name, father name, mother name, voter ID, serial number, district, upazila, union, ward, occupation, division, village, address, post code and source filename.
- Exact gender filter, page-number filter and document-ID filter.
- Combined filters, stable newest-first record order and pagination (default 50, max 200).
- Legacy-compatible response keys: \`records\`, \`total_count\`, \`page\`, \`page_size\`, \`total_pages\`.
- Dashboard totals and top district, occupation and gender breakdowns.
- Streaming UTF-8 CSV export with BOM, shared filter logic and CSV formula-injection protection.
- Excel-compatible SpreadsheetML \`.xls\` export with Bengali Unicode preservation.
- New indexes for serial number and gender. Existing voter ID and document/page indexes are retained.
- Search, pagination, dashboard, export and authentication tests.

## Routes

Both legacy route prefixes are supported. All routes require an admin JWT bearer token.

| Method | Route | Purpose |
|---|---|---|
| GET | \`/api/admin/records\` | Search and paginated voter records |
| GET | \`/api/admin/dashboard\` | Legacy dashboard statistic keys |
| GET | \`/api/admin/export/csv\` | Filtered UTF-8 CSV download |
| GET | \`/api/admin/export/excel\` | Filtered Excel-compatible XLS download |
| GET | \`/api/voter-search/records\` | Legacy alias for voter search |
| GET | \`/api/voter-search/dashboard\` | Legacy alias for dashboard |
| GET | \`/api/voter-search/export/csv\` | Legacy CSV alias |
| GET | \`/api/voter-search/export/excel\` | Legacy Excel alias |

## Query parameters

- \`q\`: global partial search.
- \`name\`, \`father_name\`, \`mother_name\`, \`voter_id\`, \`serial_no\`.
- \`district\`, \`upazila\`, \`union\` or \`union_name\`, \`ward\`, \`division\`.
- \`village\`, \`address\`, \`post_code\`, \`occupation\`, \`gender\`.
- \`page_number\`, \`document_id\`.
- \`page\`, \`page_size\` or \`per_page\`.

Partial filters use SQL LIKE matching. Values are parameter-bound and field names come from a fixed allow-list. The search is not case-folded or transliterated; it follows MySQL collation behavior. Confirm the production MySQL database/table collation is \`utf8mb4\` and test Bengali search against migrated data.

## Example

\`\`\`http
GET /api/admin/records?q=আব্দুল&district=ময়মনসিংহ&gender=পুরুষ&page=1&page_size=50
Authorization: Bearer <admin-token>
\`\`\`

\`\`\`json
{
  "records": [
    {
      "id": 10,
      "document_id": 2,
      "voter_id": "1234567890",
      "name": "মোঃ আব্দুল করিম",
      "father_name": "মোঃ রহিম উদ্দিন",
      "district": "ময়মনসিংহ",
      "pdf_filename": "voters.pdf",
      "page_number": 7
    }
  ],
  "total_count": 1,
  "page": 1,
  "page_size": 50,
  "total_pages": 1
}
\`\`\`

## Performance and limits

- Results default to 50 rows and are capped at 200 per page.
- CSV and Excel exports stream rows in chunks rather than loading the complete result set into PHP memory.
- Exact voter ID and serial number lookups benefit from indexes. Partial substring searches such as \`%term%\` generally cannot use ordinary B-tree indexes; large production datasets may require a separately tested n-gram/full-text search strategy without changing existing partial-match semantics.
- Export requests can still be expensive when the user deliberately exports a very large filtered/unfiltered dataset. Add rate limits or asynchronous export jobs if production volume requires them.

## Not yet complete / not verified

- No live production MySQL data was searched or exported.
- React UI integration and browser download workflows remain Phase 7.
- Existing hierarchy-file routes (\`/api/divisions\`, \`/api/districts/:division\`, \`/api/upazilas/... \`, etc.) and legacy JSON-file search are not yet ported in this phase; this work covers the SQLite-backed PDF voter-record search and export endpoints.
- Database collation, real Bengali query behavior, very large exports and query plans must be validated against a staging copy of the actual data before cutover.
