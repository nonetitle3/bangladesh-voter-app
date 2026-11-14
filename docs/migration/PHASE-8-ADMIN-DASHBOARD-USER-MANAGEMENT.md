# Phase 8 — Dashboard Analytics and User Administration

## Delivered

### Expanded Laravel dashboard
The admin dashboard response now includes:
- Total voter records and uploaded PDF documents
- Total database users and active database users
- Document processing status counts
- Division-wise voter record counts
- District-wise top counts
- Occupation breakdown
- Gender breakdown

All aggregates are calculated from the MySQL-backed Eloquent models at request time. No existing voter or user data is modified by these read-only queries.

### React admin user management
Added an administrator-only User Management screen:
- Search by username/email
- Filter by admin/viewer role and active/inactive status
- Paginated user listing
- Create a user with username, optional email, role and password
- Update username/email/role/active state
- Change a password without exposing the previous hash
- Delete a database user with confirmation

The screen calls the existing Laravel `/api/admin/users` resource routes and does not store or display password hashes.

### Safety rules
- Only a token whose role is `admin` can reach the user-management endpoints.
- Existing environment-configured admin username/password and JWT secret are not changed.
- The environment-configured legacy admin account is separate from MySQL users and is intentionally not listed as a deletable database user.
- Backend checks prevent a database administrator from demoting or deactivating their own account, deleting their own account, or removing the last active database admin.
- New user passwords must be at least 12 characters and are hashed by Laravel.
- The authenticated `/api/admin/me` response now includes the database user ID when the login token belongs to a database user; legacy environment-admin tokens return null for that field.

## API routes used
- `GET /api/admin/users`
- `POST /api/admin/users`
- `GET /api/admin/users/{user}`
- `PUT /api/admin/users/{user}`
- `DELETE /api/admin/users/{user}`
- `GET /api/admin/dashboard`
- `GET /api/admin/me`

## Frontend
- User Management is shown only when the signed-in identity has role `admin`.
- Dashboard now displays database-user counts, division breakdown and document processing status.
- Existing upload, documents, voter search and export screens remain in place.

## Not yet completed / limitations
- The public voter search page and public division/district/upazila/union/ward browsing APIs remain on the legacy contract. They are not part of the admin-only scope and must be migrated before traffic cutover.
- Viewer-role read-only access to the dashboard and voter search is not enabled by the current admin-only API middleware; viewer users can be created, but a separate read-only middleware/policy and UI experience are still required before assigning viewer accounts for production use.
- No live Render service, MySQL database, production admin credentials, PDFs or object storage were modified.
- The frontend and backend changes require CI and staging verification. Do not treat this phase as production-ready until the GitHub Actions run passes and the CRUD/permission checks are exercised against a staging database.
