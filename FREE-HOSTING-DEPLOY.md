# PHILSURIN — Free Permanent Hosting (InfinityFree)

Put the system on the internet so **everyone can access it**, online 24/7 even when your PC is off.
Cost: **$0** (InfinityFree: free forever, no credit card, no ads on your site, free SSL, PHP 8.4 + MySQL).

**Package to upload:** `philsurin-deploy.zip` (in this folder)
Contains: all app files + `.htaccess` security rules + `philsurin_production.sql`
(full database with your current data and the new secure passwords — **no default passwords**).

---

## Step 1 — Create your free account
1. Open **https://www.infinityfree.com** → **Register**.
2. Enter email + password → confirm the verification email.

## Step 2 — Create the website
1. After login → **Create Account** → **Free Website**.
2. Choose a free subdomain, e.g. `philsurin.great-site.net`
   (you can also pick other extensions like `.rf.gd`, or add your own domain later).
3. Submit — the account is created instantly (no approval wait).

## Step 3 — Create the MySQL database
1. In the **Control Panel** of your new site → **MySQL Databases**.
2. Create a database and a database user (set a password you choose).
3. **Write down the 4 values shown:** Database Name, Username, Password, **Hostname**
   (looks like `sqlXXX.infinityfree.com` — not `localhost`).

## Step 4 — Import the database
1. Control Panel → **phpMyAdmin** (next to your database).
2. Select your database on the left → **Import** tab.
3. Choose the file `philsurin_production.sql` from `philsurin-deploy.zip` → **Go**.
4. You should see the tables (users, varieties, grading_records, audit_logs, …) appear.

## Step 5 — Upload the app
1. Control Panel → **File Manager** → open the **`htdocs`** folder.
2. **Upload** `philsurin-deploy.zip` → when finished, **right-click the zip → Extract**.
3. The files must sit directly in `htdocs` (you should see `index.php`, `api/`, `assets/` there).
4. **Delete the zip** after extracting.

## Step 6 — Connect the app to the database
1. In File Manager, edit **`config.php`** (inside `htdocs`).
2. Find these 4 lines (around lines 36–39) and replace the defaults with your Step 3 values:

```php
$db_host = getenv('DB_HOST') ?: 'YOUR-HOSTNAME-HERE';   // e.g. sql300.infinityfree.com
$db_name = getenv('DB_NAME') ?: 'YOUR-DATABASE-NAME';   // e.g. if123456_philsurin
$db_user = getenv('DB_USER') ?: 'YOUR-DATABASE-USER';   // e.g. if123456_philsurin
$db_pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : 'YOUR-DATABASE-PASSWORD';
```
3. Save.

## Step 7 — Open the live site
1. Visit **`https://YOUR-SUBDOMAIN.infinityfreeapp.com`** (the URL shown in your panel).
2. Log in with the **admin username** and the new password from **`NEW-CREDENTIALS.txt`**
   (the old `admin123` does **not** exist anywhere anymore).
3. Free SSL is automatic — the `https://` padlock should be on.

## Step 8 — First checks after going live
- [ ] Log in as admin, open Encoding / Report / Audit pages.
- [ ] Log in from your Android phone using the public URL.
- [ ] Change the admin password to something only you know: **Users** page.
- [ ] Create separate accounts for validators/viewers — don't share the admin login.

---

## Troubleshooting
| Problem | Fix |
|---|---|
| **500 Internal Server Error** | Open `.htaccess` in File Manager and delete the line `Options -Indexes`, save. |
| **API / login links give 404** | Make sure `api/.htaccess` was uploaded (it's a dotfile — enable "Show hidden files" in File Manager). |
| **"Database connection failed"** | Re-check the 4 values in `config.php` (Step 6), especially the **Hostname**. |
| **Import does nothing** | Import one file only: `philsurin_production.sql`. Don't also import the old `database.sql`. |
| **Site suspended / inactive** | Free sites need a few hits per month — visit it regularly. |

## Important notes
- **Updates:** local changes (your XAMPP copy) are NOT synced automatically —
  re-upload changed files via File Manager each time you modify the app.
- **Backups:** Control Panel → MySQL → dump, and download `htdocs` occasionally.
- **Do not upload** `database.sql`, `*.md`, `*.ps1`, `Code.gs` or any dev files —
  the bundled `.htaccess` blocks them anyway (403) as a second line of defense.
- `admin123` / `viewer123` no longer exist anywhere: local DB, production SQL and
  the login page hint were all changed/removed in this hardening pass.
