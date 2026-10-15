# PHILSURIN — Go 24/7 With PC OFF (Render Free + Free MySQL)

Your repo is live. Your guides referenced Docker files that didn't exist — I just
created them locally: `Dockerfile`, `docker-compose.yml`, `.env.example`, `render.yaml`.
Push them first, then deploy. After that your PC can be OFF.

## Step 0 — Push the new files (2 min)

Double-click:
```
c:\Users\Hp\Downloads\files\DEPLOY-CLOUD.bat
```
It runs: `git add Dockerfile docker-compose.yml .env.example render.yaml` +
`git commit` + `git push origin main`. If it asks for login:
user `latozamico05-jpg`, password = Token from
https://github.com/settings/tokens (classic, scope `repo`).

Verify: https://github.com/latozamico05-jpg/philsurin-validation shows
`Dockerfile`, `docker-compose.yml`, `render.yaml`.

## Step 1 — Free MySQL (Render free has no DB, 10 min)

Render free web + free external MySQL (pick one):

**Aiven free (easiest):** https://console.aiven.io/signup
→ Create MySQL (free plan) → copy Host, Port, Database, User, Password.

**Or Railway:** https://railway.app → New → Database → MySQL → copy vars.

Your `config.php` already reads `DB_HOST/DB_PORT/DB_NAME/DB_USER/DB_PASS` and
auto-creates tables via `db-bootstrap.php` — no phpMyAdmin needed.

## Step 2 — Deploy web on Render (5 min, PC can go OFF after)

1. https://dashboard.render.com → **New +** → **Blueprint**
2. Connect repo: `latozamico05-jpg/philsurin-validation` → **Apply**
   (uses `render.yaml`: docker runtime, health check `/health.php`)
3. Service page → **Environment** → add:
   ```
   DB_HOST=<aiven host>
   DB_PORT=<port, e.g. 12345>
   DB_NAME=<database>
   DB_USER=<user>
   DB_PASS=<password>
   SESSION_HANDLER=db
   ```
   → **Save** (auto-redeploys).
4. Wait for **Live** (green). Open the `*.onrender.com` URL → login page.
5. Check `https://<your>.onrender.com/health.php` → `{"ok":true,"db":"up",...}`.

Free-plan note: spins down after ~15 min idle, wakes in ~30-60s on visit.
Keep awake free: https://uptimerobot.com → monitor `.../health.php` every 5 min.

## Step 3 — Optional local test (Docker on your PC)

```bash
copy .env.example .env
# edit .env passwords, then:
docker compose up -d --build
curl http://localhost:8080/health.php
docker compose down
```

## Security (do right after first cloud login)

- [ ] Change admin password
- [ ] Strong DB passwords, never commit `.env`
- [ ] UptimeRobot monitor on `/health.php`
