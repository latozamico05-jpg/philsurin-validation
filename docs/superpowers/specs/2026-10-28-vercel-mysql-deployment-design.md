# Vercel + External MySQL Deployment Design

## Intent

Deploy the existing PHILSURIN PHP application to Vercel while keeping the current UI, PHP routes, API, authentication, and database schema. Durable application data must live in an external cloud MySQL database so the deployment does not depend on Vercel's ephemeral filesystem.

## Goals

- Serve the existing PHP pages and `/api/*` endpoints through Vercel.
- Add Vercel configuration for the repository's PHP entrypoints and static assets.
- Make production database access use external MySQL through environment variables.
- Run the existing schema, migrations, and seed data against the cloud database.
- Use database-backed PHP sessions in production.
- Preserve local XAMPP/SQLite fallback behavior for development.
- Verify the deployment configuration, PHP syntax, database bootstrap, authentication, health endpoint, and production build/deploy command where credentials are available.

## Non-goals

- Rewriting the PHP application in Node.js or Next.js.
- Replacing the existing UI or API contract.
- Storing uploads or mutable files on the Vercel filesystem.
- Removing the existing local development and Render/Docker deployment paths.

## Constraints

- Vercel PHP is provided through the `vercel-php` community runtime.
- Vercel Functions have read-only persistent storage; durable state belongs in MySQL or object storage.
- The repository has an existing unrelated edit in `dashboard.php`; it must be preserved.
- Vercel, cloud database, and Git provider credentials are external inputs and must not be committed.

## Approaches considered

1. **Selected — PHP on Vercel + external MySQL.** Keeps the existing code and schema, with a small deployment adapter and production configuration. It requires careful handling of sessions, file writes, and community-runtime limitations.
2. **Vercel frontend + PHP API on Render/Railway.** Better fit for long-lived Apache/PHP behavior, but requires splitting the application, CORS configuration, and cross-origin cookie handling.
3. **Native Node.js rewrite.** Fits Vercel's primary runtime but has the highest risk and scope because it replaces the current PHP pages, authentication, and API implementation.

The selected approach is option 1 because it satisfies the requested Vercel hosting target with the least product change.

## Design

- Add `vercel.json` with the PHP community runtime, routing for PHP pages/API requests, and static asset handling.
- Keep the current PHP bootstrap as the central compatibility layer, but make production behavior explicit through environment variables such as `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_SSL`, `SESSION_HANDLER=db`, and `API_BASE_URL`.
- Ensure the database bootstrap is safe for repeated serverless invocations and does not rely on SQLite or writes inside the deployed project directory.
- Apply `database/schema.sql`, `database/migrations/001_core_architecture.sql`, and `database/seed.sql` to a newly provisioned cloud MySQL database, with a backup/export step if an existing database is used.
- Add a deployment health check that confirms the app can connect to MySQL and returns a useful status without exposing credentials.
- Document Vercel project setup, environment variables, database initialization, and deployment commands.
- Use the existing package scripts only where useful; Vercel deployment is configured separately from the current Apps Script scripts.

## Builder decisions

- Default to a MySQL-compatible managed provider supplied by the user; if no provider is already available, use Railway MySQL as the deployment guide's example rather than embedding provider-specific code.
- Do not commit `.env`, database passwords, Vercel tokens, or provider credentials.
- Treat uploads/exports as request-time downloads or external storage follow-up work; do not claim Vercel-local persistence.

## Acceptance checks

- `vercel.json` is valid and routes both PHP pages and API requests.
- PHP syntax checks pass for changed PHP files.
- The schema and seed scripts apply to a clean MySQL database without errors.
- A configured deployment returns the health endpoint and login/API smoke tests pass.
- The existing local edit in `dashboard.php` remains untouched.
