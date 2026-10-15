# PHILSURIN integration analysis

## Existing architecture

This is an existing PHP/MySQL application intended for XAMPP. The current web entry points are `index.php`, `locations.php`, `report.php`, `audit.php`, `export.php`, and `report_data.php`. `style.css` and the small JavaScript files provide the current UI and report behavior. `Code.gs` is a separate Google Apps Script implementation and must not be removed.

## Existing data and authentication

`config.php` starts the PHP session, creates a PDO MySQL connection, and currently ensures `grading_records` exists. The legacy SQL file defines `users`, `varieties`, and `validations`; the active grading form writes four replicate rows per submission into `grading_records`. Existing login checks the `users` table and stores `id` and `username` in `$_SESSION['user']`. Existing role checks use the legacy `admin`/`viewer` values.

## Existing PHILSURIN modules

- Login and session handling: `index.php`, `config.php`
- Encoding and 01/02/03/04/HARVEST stage form: `index.php`
- Variety master data: `varieties` table and the variety selector
- Trial locations: `locations.php`
- Reports and CSV export: `report.php`, `export.php`, `report_data.php`
- Audit view: `audit.php`
- Google Sheets alternative: `Code.gs`, `appsscript.json`, `clasp` scripts
- C4.5: no active PHP C4.5 implementation was found; the new API leaves an auditable extension point

## Files kept unchanged

`Code.gs`, `appsscript.json`, the existing report JavaScript, and the existing page behavior remain available. The existing tables are not dropped and no production records are deleted.

## Files added

- `database/schema.sql`, `database/seed.sql`, `database/dev_seed.sql`
- `database/migrations/001_core_architecture.sql`
- `api/.htaccess`, `api/index.php`, `api/bootstrap.php`
- `frontend/services/api.js`
- `docs/DATABASE.md`, `docs/API.md`, `docs/C45.md`, `docs/DEPLOYMENT.md`

## Migration risks

The legacy `users` and `varieties` tables use `id/code/name`, while the requested logical model uses `user_id/variety_code/variety_name`. The migration keeps legacy columns and adds the new tables around them. A production deployment should take a database backup, run the migration, verify foreign keys, and only then migrate legacy records into the new central `masterlist` table. The current login supports existing SHA-256 records; new API-created passwords use `password_hash()`.
