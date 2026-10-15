# PHILSURIN Sugarcane Variety Validation

Ready for GitHub upload. Main branch contains a **safe copy** — `.clasp.json` has been stripped of the live Google Apps Script script ID and uses a placeholder instead.

## What is this?

This is a sugarcane variety validation system with a Google Apps Script front-end, a PHP backend, and MySQL. Two deployment targets:

1. **Free hosting** — InfinityFree (PHP 8.4 + MySQL). See `FREE-HOSTING-DEPLOY.md`.
2. **Apps Script web app** — `npm run deploy` from VS Code (clasp). See `README.md` for the VS Code setup.

## Setup for GitHub

### 1. Create the repository

- Go to https://github.com/new
- Create a **private** repository (choose a name, e.g. `philsurin-validation`)
- **Do not** initialize with a README/".gitignore" — you will push the existing code

### 2. Clone (optional)

```sh
git clone https://github.com/YOU/philsurin-validation.git
cd philsurin-validation
```

> Git CLI is not installed on this machine. If you need it:
> - Windows: `winget install Git.Git` then restart the terminal

### 3. Copy the config template

The `.clasp.json` in the repo is a placeholder. Copy the example:

```sh
copy .clasp.json.example .clasp.json
```

Then open `.clasp.json` and paste your Google Apps Script **Project ID** (Apps Script → Project Settings → Script ID).

### 4. Push

```sh
git add -A
git commit -m "chore: initial PHILSURIN upload"
git branch -M main
git remote add origin https://github.com/YOU/philsurin-validation.git
git push -u origin main
```

### 5. Ignore patterns that must NOT be committed

`.gitignore` already excludes:

- `.clasp.json` (live config — replace from `.clasp.json.example` before committing)
- `NEW-CREDENTIALS.txt` (contains the live admin password)
- `.env`, `.env.*` (database credentials)
- `node_modules/`, `*.log`, `*.sql.local`, `_*/`, `_*.*`, `*.bak*`, `.vscode/`, `*.code-workspace`

## Port configuration

- **app.js** (Node.js port forwarder): default port **8080** — change via `PORT=3000 node app.js`
- **Apps Script web app**: controlled by the deploy URL in Google Apps Script (no local port)

## Files included in this repo

- `app.js` — port forwarder (Node.js)
- `appsscript.json`, `src/Code.gs`, `src/page.html` — Apps Script project
- `config.php`, `api/`, `index.php`, `dashboard.php`, `users.php`, `report.php`, ... — PHP backend
- `.htaccess` — Apache security + performance rules
- `database/schema.sql`, `database/seed.sql` — DB schema and seed data

## Deployment checklist (InfinityFree)

1. Create an InfinityFree account (see `FREE-HOSTING-DEPLOY.md` Step 1)
2. Create a free website + MySQL database
3. Import `philsurin_production.sql`
4. Upload the `philsurin-deploy.zip` contents to `htdocs/`
5. Edit `config.php` with your DB hostname/username/password
6. Open `https://YOUR-SUBDOMAIN.infinityfreeapp.com`
