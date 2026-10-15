# PHILSURIN Sugarcane Variety Validation (VS Code + clasp)

Your one-file Apps Script, set up so you can edit it in VS Code and push it to Google.

## Quick Start

1. Install Node.js, then run `npm install` in this folder
2. Turn on the Apps Script API: https://script.google.com/home/usersettings
3. Run `npm run login`
4. Make the Google Sheet, open **Extensions > Apps Script**, copy the Script ID
5. Copy `.clasp.json.example` to `.clasp.json` and paste the Script ID
6. Replace `src/Code.gs` with your script
7. Run `npm run push` to deploy to Apps Script

---

## Run Even When Your PC Is OFF (Cloud Hosting)

See `README-CLOUD.md` for cloud deployment guides (Docker + Render/AWS EC2).
Your PC can be OFF — the system runs 24/7/365 on a cloud server.

---

## Local Permanent Hosting (Windows Auto-Start)

If you want to keep your PC as the host, set up auto-start:

1. Run `SETUP-PERMANENT.bat` (adds to Startup folder + Registry)
2. Restart your PC — the system starts automatically
3. Verify: `http://localhost:80`, `http://localhost:3000`

See `README-PERMANENT.md` for full setup instructions.

---

## Deployment Options (Pick One)

| Option | Cost | PC Off? | Uptime |
|--------|------|---------|--------|
| A. Cloud (Docker + Render/AWS/EC2) | Free (AWS free tier) | ✅ Yes | 24/7/365 |
| B. Local (Windows Auto-Start) | $0 | ❌ PC must be on | Depends on PC uptime |

---

## Files in This Repository

- `app.js` — Node.js port forwarder (port 3000)
- `devtunnel-proxy.js` — Apache ↔ tunnel proxy
- `start-proxy.ps1` — PowerShell startup script
- `RUN-PERMANENT.bat` — Permanent startup script (Windows auto-start)
- `SETUP-PERMANENT.bat` — One-click setup for auto-start
- `README-PERMANENT.md` — Local permanent hosting guide
- `README-CLOUD.md` — Cloud hosting setup guide
- `GITHUB-UPLOAD.bat` — GitHub upload script
- `docker-compose.yml` — Docker setup (local + cloud)
- `.github/workflows/ci.yml` — GitHub CI/CD workflow

---

## Security Notes

- Never commit `NEW-CREDENTIALS.txt`, `.clasp.json`, or `.env` to GitHub
- Change the admin password after first login
- Use HTTPS (Cloudflare or Let's Encrypt)
- Keep your PC secure if hosting locally

