# Phase 9 — Testing, Security and Production Readiness

## CI result

The Phase 9 verification commit `3be03545931317b501b94496f385ae5b4cc9077b` completed GitHub Actions run `43572257389` successfully.

All four CI jobs passed:
- PHP/Laravel: Composer validation, PHP syntax, route boot, Laravel feature tests, MySQL 8.4 migrations, legacy SQLite fixture import and parity verification.
- Python worker: Bengali+English Tesseract engines installed, dependency install, Python compilation, pytest and worker Docker image build.
- React frontend: Node 20 dependency install and production build.
- Backup utility: Python compilation and integration test that creates a SQLite backup and archives PDF/data artifacts.

## Fix made after CI found a defect

The previous Phase 8 dashboard controller had over-escaped PHP namespace separators. CI caught a PHP parse error before tests could run. This was corrected in `VoterRecordController.php`, and the subsequent complete CI run passed.

## Added security tests

Added `php-hybrid/tests/Feature/UserManagementSecurityTest.php` to verify:
- Admin can list, create, update and delete database users.
- User password hashes are not returned in API responses.
- Passwords are hashed and can be verified after creation.
- The last active database admin cannot be demoted, deactivated or deleted.
- An administrator cannot delete their own account.
- Deactivated database-user JWTs are rejected.
- Legacy environment-admin credentials continue to issue the original eight-hour HS256 token without a database subject.

Existing authentication tests also cover viewer-role denial, inactive account login rejection and database-admin token revocation.

## Security and operational checks present

- Admin APIs require a valid HS256 bearer token and admin role.
- Database-user tokens are checked against the current active state and role.
- Upload API validates PDF MIME/extension, count and configured file size.
- PDFs are stored on private S3-compatible storage or a configured private persistent disk; no public PDF URLs are returned.
- Worker internal endpoints require a separately configured bearer token.
- Search SQL column names are allow-listed and user values are bound.
- CSV export neutralizes spreadsheet formula prefixes; Excel XML values are escaped.
- Import tooling supports dry-run and explicit execution; CI verifies fixture counts and Bengali Unicode.

## Not production-certified yet

CI passing proves the code builds and automated tests pass in GitHub Actions; it does not certify the live service. The following remain cutover blockers:
1. Migrate the public React voter search screen and hierarchy APIs (divisions/districts/upazilas/unions/wards, public search, age/gender filters and browse routes).
2. Port the existing preview/confirm workflow if it is still required.
3. Implement and test viewer read-only API permissions and UI; current protected admin endpoints require admin.
4. Run end-to-end staging tests with real Render networking, external MySQL, private object storage, Laravel queue worker and Python worker.
5. Test OCR against representative native-text and scanned Bengali PDFs, including extraction accuracy and large-file memory/time limits.
6. Execute a full production backup and restore rehearsal, verify storage retention and rollback steps.
7. Verify production environment secrets, HTTPS/CORS allow-list, rate limits, database least-privilege user, logs and alerting.
8. Run a data migration dry-run on a copy of the actual SQLite database and compare every table count, ID, password hash, Bengali text value and PDF reference before importing.

No production database, existing admin credential, JWT secret, uploaded PDF or Render service was accessed or changed. Do not merge the PR or switch public traffic until all cutover blockers are resolved and staging is approved.
