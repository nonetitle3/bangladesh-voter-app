# Phase 7 — React Admin Frontend Integration

## Delivered

- Replaced the React admin dashboard's hard-coded Python API URL with a configurable Laravel API base URL.
- Preserved the existing admin route and React entry point.
- Connected admin login and session verification to Laravel `POST /api/admin/login` and `GET /api/admin/me`; expired tokens are cleared.
- Connected dashboard metrics and health status to Laravel.
- Connected multi-PDF upload to `POST /api/admin/upload` using the Laravel `files[]` multipart field.
- Added batch progress polling every five seconds using `GET /api/admin/upload/{jobId}`.
- Added document listing, page-level status, private PDF download and failed/pending retry actions.
- Connected Bengali/English voter search filters and pagination to `GET /api/admin/records`.
- Connected filtered CSV and Excel downloads to Laravel export endpoints.
- Added responsive layout and error/success feedback for the integrated admin screens.
- Existing public voter-search React page (`frontend/src/App.js`) is intentionally unchanged because the Laravel migration currently exposes admin-protected record APIs; the public hierarchy/search API parity is not yet implemented. It must not be pointed at those admin-only endpoints.

## Frontend configuration

Set this build-time variable in the frontend hosting provider:

```env
REACT_APP_API_URL=https://YOUR-LARAVEL-RENDER-SERVICE.onrender.com/api
```

An example file is available at `frontend/.env.example`.

For GitHub Pages, GitHub Actions must receive `REACT_APP_API_URL` as a repository Actions variable and build the React app with that value. A relative default is suitable only when the React app is served from the same origin as Laravel.

## Admin route

The existing React router continues to render the admin panel at:
- `/admin`
- `/bangladesh-voter-app/admin` (legacy GitHub Pages path)

The admin dashboard uses the same `adminToken` localStorage key as the previous frontend to ease migration. The token is issued by Laravel and uses the preserved HS256 contract.

## API calls used

- `POST /api/admin/login`
- `GET /api/admin/me`
- `GET /api/admin/dashboard`
- `GET /api/health`
- `POST /api/admin/upload`
- `GET /api/admin/upload/{jobId}`
- `GET /api/admin/documents`
- `GET /api/admin/documents/{id}/pages`
- `GET /api/admin/documents/{id}/download`
- `POST /api/admin/documents/{id}/retry`
- `GET /api/admin/records`
- `GET /api/admin/export/csv`
- `GET /api/admin/export/excel`

## Deploying the React admin frontend

1. Deploy Laravel Web Service first and confirm `/api/health` returns `status: ok`.
2. Configure `CORS_ALLOWED_ORIGINS` in Laravel with the exact frontend origin (scheme + host, no trailing slash).
3. Configure `REACT_APP_API_URL` in the frontend build environment to the Laravel API URL.
4. Build the frontend from the `frontend` directory with `npm install` then `npm run build`.
5. Publish the generated `frontend/build` directory using the chosen static hosting provider.
6. Open `/admin`, sign in with the existing configured admin credentials, and verify dashboard, PDF upload, documents, search and exports against staging data.

## Important remaining work

- The public voter search screen is still on the legacy API contract; Phase 7 only integrates the admin application. Public hierarchy endpoints and public search must be migrated before switching the public site.
- The old preview-only Node/Python flow is not replaced by the new worker pipeline. The new admin upload directly queues PDFs for processing; preview/confirm parity still needs separate migration if that workflow is required.
- User management UI is not yet added even though the Laravel user-management API exists.
- No live Render deploy, production database, user account, credential, PDF, or storage bucket was accessed or changed.
- The CI frontend build validates compilation, not live API connectivity. Run the documented staging smoke tests before production cutover.
