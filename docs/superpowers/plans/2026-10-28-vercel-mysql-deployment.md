# Vercel + External MySQL Deployment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deploy the existing PHP application to Vercel with durable state in an external MySQL database.

**Architecture:** Vercel serves PHP pages and `/api/*` through the `vercel-php` community runtime. The existing PHP bootstrap remains the compatibility layer; production uses external MySQL and database-backed sessions, while local MySQL/SQLite fallback remains available for development. No durable state is written to the Vercel function filesystem.

**Tech Stack:** PHP 8.2, PDO MySQL, existing PHP application, Vercel Functions, `vercel-php`, MySQL-compatible managed database, PowerShell/Node verification scripts.

**Spec:** `docs/superpowers/specs/2026-10-28-vercel-mysql-deployment-design.md`

## Global Constraints

- Keep the existing UI, PHP routes, API, authentication, and database schema.
- Durable application data must live in an external cloud MySQL database.
- Do not commit `.env`, database passwords, Vercel tokens, or provider credentials.
- Preserve the unrelated existing edit in `dashboard.php`.
- Keep local XAMPP/SQLite fallback behavior for development.
- Do not claim deployment success without fresh verification output.

## Review Focus

- Vercel requests to `/`, `.php` pages, and `/api/*` must reach PHP rather than static-file fallbacks; test via the generated routing configuration.
- Production must not silently write to SQLite or the deployed project directory; test with cloud DB variables and `SESSION_HANDLER=db`.
- Repeated bootstrap calls must be safe and idempotent; test schema/bootstrap twice against a clean MySQL database.
- Failed or missing database configuration must return a controlled health/error response without exposing credentials; test the health endpoint and logs.
- Existing local behavior must remain intact; run the current project checks and confirm the pre-existing `dashboard.php` diff is unchanged.

### Task 1: Add Vercel PHP runtime and routing

**Files:**
- Create: `vercel.json`
- Create: `api/index.php` adapter or modify existing `api/index.php` only if required by runtime routing
- Inspect/modify: `.gitignore` if deployment artifacts or local env files are not already excluded

**Interfaces:**
- Consumes: existing root PHP pages, `api/index.php`, `config.php`, and static assets.
- Produces: Vercel configuration using `vercel-php` with requests for PHP pages and `/api/*` mapped to the correct PHP entrypoint.

- [ ] **Step 1: Add the minimal `vercel.json` runtime and route definitions**

  Configure the community runtime for the PHP entrypoint, preserve static assets, and route `/api/(.*)` to `api/index.php` without exposing internal files.

- [ ] **Step 2: Validate the JSON and inspect route coverage**

  Run: `node -e "JSON.parse(require('fs').readFileSync('vercel.json','utf8')); console.log('valid')"`

  Expected: `valid`, with explicit coverage for `/`, PHP pages, static assets, and `/api/*`.

- [ ] **Step 3: Verify local PHP entrypoints still parse**

  Run: `Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }`

  Expected: no PHP syntax errors.

### Task 2: Make production database and session behavior explicit

**Files:**
- Modify: `config.php`
- Inspect/modify: `db-fallback.php`
- Inspect/modify: `db-bootstrap.php`
- Inspect/modify: `.env.example`

**Interfaces:**
- Consumes: `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, optional TLS settings, `SESSION_HANDLER`, and `API_BASE_URL`.
- Produces: production-safe `philsurin_open_db()` selection and database-backed sessions when `SESSION_HANDLER=db`.

- [ ] **Step 1: Add a configuration test matrix**

  Exercise the existing DB selection helpers with local SQLite fallback variables, cloud MySQL variables, and missing credentials. Assert the selected driver and that secrets are not included in returned error text.

- [ ] **Step 2: Implement production selection rules**

  Ensure cloud variables select PDO MySQL, local development can still fall back to SQLite, and production does not silently fall back to a mutable local database when `VERCEL` is set.

- [ ] **Step 3: Harden database-backed sessions for serverless requests**

  Ensure session storage is configured before `session_start()`, uses the existing `php_sessions` table, and applies secure, HTTP-only, SameSite-compatible cookies behind Vercel HTTPS.

- [ ] **Step 4: Update `.env.example` with non-secret variable names and safe examples**

  Include MySQL connection variables, `SESSION_HANDLER=db`, `API_BASE_URL`, and a note that values belong in Vercel project settings.

- [ ] **Step 5: Run focused PHP/config checks**

  Run the new configuration test and `php -l config.php db-fallback.php db-bootstrap.php`.

  Expected: all assertions pass and no syntax errors are reported.

### Task 3: Make cloud schema initialization repeatable

**Files:**
- Inspect/modify: `database/schema.sql`
- Inspect/modify: `database/migrations/001_core_architecture.sql`
- Inspect/modify: `database/seed.sql`
- Inspect/modify: `db-bootstrap.php`
- Create: `tests/cloud-db-bootstrap.php` or an equivalent repository-style smoke test

**Interfaces:**
- Consumes: a clean MySQL database connection from Task 2.
- Produces: repeatable schema, migration, seed, and bootstrap behavior suitable for a serverless deployment.

- [ ] **Step 1: Add a clean-database bootstrap smoke test**

  The test must create/use a disposable database, apply schema/migration/seed/bootstrap, repeat the operation, and assert that required tables (`users`, `php_sessions`, `masterlist`, `validation_sessions`, `audit_logs`) exist.

- [ ] **Step 2: Fix non-idempotent schema or seed statements found by the test**

  Use `CREATE TABLE IF NOT EXISTS`, guarded inserts, or migration markers consistent with the existing schema; do not drop production tables.

- [ ] **Step 3: Verify the database bootstrap twice**

  Run the smoke test twice against the same disposable database.

  Expected: both runs exit 0 with no duplicate-key or missing-table errors.

### Task 4: Add deployment health and operational documentation

**Files:**
- Inspect/modify: `health.php`
- Modify: `docs/DEPLOYMENT.md`
- Create: `docs/VERCEL-MYSQL-DEPLOYMENT.md`
- Inspect/modify: `README-CLOUD.md` only if links or commands are stale

**Interfaces:**
- Consumes: the production database/session configuration from Tasks 1–3.
- Produces: a health endpoint that confirms application/database readiness without leaking credentials and a step-by-step Vercel/MySQL guide.

- [ ] **Step 1: Add/verify a database-aware health response**

  Return HTTP 200 only when the application can connect and query the database; return a non-sensitive failure status otherwise. Preserve any existing health response fields that consumers rely on.

- [ ] **Step 2: Write the Vercel/MySQL deployment guide**

  Document database creation, schema import order, Vercel project creation, required environment variables for Preview and Production, session configuration, deployment command, health check, login smoke test, and rollback notes.

- [ ] **Step 3: Verify docs against the actual files and commands**

  Check every referenced file, command, variable, and endpoint exists in the repository.

### Task 5: Run integrated verification and deploy when credentials are available

**Files:**
- Modify only files required by earlier tasks; preserve unrelated changes.

**Interfaces:**
- Consumes: completed Vercel configuration, cloud MySQL credentials, and optional Vercel CLI/account access.
- Produces: a verified deployment URL or a precise external-credential blocker.

- [ ] **Step 1: Inspect the final diff and pre-existing changes**

  Run: `git status --short` and `git diff -- dashboard.php`

  Expected: the original `dashboard.php` change remains present and is not mixed into deployment edits.

- [ ] **Step 2: Run repository checks**

  Run: `npm run check`, the cloud DB smoke test, JSON validation, and PHP syntax checks.

  Expected: each command exits 0; failures are fixed or reported with evidence.

- [ ] **Step 3: Deploy using the configured Vercel project**

  Run the approved Vercel deployment command only after environment variables are available; never pass secrets in committed files or command history when the CLI supports project environment settings.

- [ ] **Step 4: Smoke-test the deployed application**

  Verify the deployment URL, `/health.php`, login, `/api/auth/session`, and one authenticated read endpoint. Confirm the response does not use SQLite fallback.

- [ ] **Step 5: Report the exact deployment result**

  Include the deployed URL, database initialization status, verification commands/output, and any remaining provider/account action required.
