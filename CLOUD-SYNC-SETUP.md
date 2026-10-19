# PHILSURIN — Stay online with PC OFF (Render cloud + 2-way sync)

Your devtunnel port (`lnmmkbpg-3000.asse.devtunnels.ms`) forwards to YOUR PC,
so it always dies when the PC sleeps or powers off — no setting can fix that.
This setup gives you a SECOND, always-on copy in the cloud with its own cloud
database, plus a sync bridge so **all data reflect on both systems**.

```
PC ON:   you + cloud both read/write -> SYNC-NOW.bat mirrors both ways
PC OFF:  cloud keeps working alone (public URL stays up)
PC BACK: run SYNC-NOW.bat -> cloud rows made while you were away land in XAMPP
```

Cost: $0 (Render free web + Aiven free MySQL). No credit card.

## Step 1 — Free cloud MySQL (10 min, do once)

1. Sign up: https://console.aiven.io/signup (free plan, no card)
2. Create service → **MySQL** (free plan, any region near you)
3. Copy: Host, Port, Database, User, Password. Keep this tab open.

Alternative: https://railway.app → New → Database → MySQL → copy the same 5 values.

## Step 2 — Deploy the web copy on Render (5 min)

1. Push this folder's new files to GitHub (run `DEPLOY-CLOUD.bat`, or
   `git add -A && git commit -m "cloud sync" && git push origin main`).
   Must include: `Dockerfile`, `docker-compose.yml`, `render.yaml`,
   `config.php`, `db-bootstrap.php`, `cloud-sync.php`, `health.php`.
2. https://dashboard.render.com → **New +** → **Blueprint**
3. Connect repo `latozamico05-jpg/philsurin-validation` → **Apply**
4. Service → **Environment** → add:
   ```
   DB_HOST=<aiven host>
   DB_PORT=<port, e.g. 12345>
   DB_NAME=<database>
   DB_USER=<user>
   DB_PASS=<password>
   SESSION_HANDLER=db
   SYNC_KEY=<make a long random string, e.g. 40 letters+numbers>
   ```
   Save (auto-redeploys). Wait for **Live** (green).
5. Open `https://<your>.onrender.com/health.php` → want
   `{"ok":true,"db":"up",...}`. Tables auto-create on first hit
   (`config.php` → `db-bootstrap.php`); no phpMyAdmin needed.
6. Open `https://<your>.onrender.com/cloud-sync.php?action=ping` → want
   `{"success":true,...,"pc_required":false}`.

Free-plan naps: Render sleeps after ~15 min idle, wakes in ~30-60s.
Keep awake free: https://uptimerobot.com → monitor `.../health.php` every 5 min.

## Step 3 — Link your PC to the cloud (5 min, do once)

On your PC, in this folder:

1. Create file `sync.key` containing ONLY your SYNC_KEY (same as Render):
   ```
   echo YOUR-SYNC-KEY > sync.key
   ```
   (`sync.key` is gitignored — never committed.)
2. Set the cloud URL once (stays in `sync-state.json`):
   ```
   set CLOUD_URL=https://<your>.onrender.com
   php sync-push.php
   ```
   First push uploads your XAMPP rows to the cloud. Then:
   ```
   php sync-pull.php
   ```
   (or double-click `SYNC-NOW.bat` — does push+pull).

## Step 4 — Daily use

- PC ON: work on either URL; run `SYNC-NOW.bat` before/after big encoding
  sessions (or leave the scheduled task below to do it hourly).
- PC OFF: give everyone the **cloud URL** — it keeps working, collecting
  encodings into cloud MySQL (the "alternative storage" you asked for).
- PC BACK ON: run `SYNC-NOW.bat` — cloud rows flow into
  `C:\xampp\htdocs\philsurin` MySQL, local rows flow up. Both identical.

## Step 5 — Auto-sync hourly (optional, PC side)

Task Scheduler → Create Task `PHILSURIN-cloud-sync`:
- Trigger: Daily, repeat every 1 hour
- Action: `node` with args `"C:\Users\Hp\Downloads\files\sync-both.js"`
- Start in: `C:\Users\Hp\Downloads\files`
- Condition: **Start only if network is available**

## Safety notes

- Sync is idempotent (natural-key upserts): re-running never duplicates.
  `grading_records` key = location_id + stage + variety + replicate.
- `sync-state.json` watermarks (`last_push`/`last_pull`) make each run
  incremental — only new rows move after the first full sync.
- Never share SYNC_KEY. Rotate it by changing Render env + local `sync.key`.
- Change cloud admin password on first login (Users page), like local.
