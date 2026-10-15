# PHILSURIN Sugarcane Variety Validation — Permanent Hosting

This document covers **24/7/365 hosting on your local machine** using XAMPP + Cloudflare tunnel.

---

## How It Works

```
Internet → Cloudflare Tunnel → Your PC (port 3000) → Apache (port 80) → PHP + MySQL
```

- **Cloudflare tunnel**: `lnmmkbpg-3000.asse.devtunnels.ms`
- **Apache**: C:\xampp\apache\apache.exe (port 80)
- **Node.js proxy**: devtunnel-proxy.js (port 3000)
- **Database**: MySQL (port 3306)

---

## Permanent Startup Setup (Windows)

This converts your PC into a **24/7 server** — no manual starting required.

### Method A: Startup Folder (Easiest)

1. Open this folder in File Explorer:
   ```
   Win + R → shell:startup
   ```
2. Create a shortcut to `RUN-PERMANENT.bat`
3. **Optional but recommended:** Set the shortcut to open at logon:
   - Right-click shortcut → **Properties** → **Shortcut** tab → **Run** → **Minimized**

### Method B: Registry Run Key (Automatic at Logon)

Run this command in PowerShell as Administrator:

```powershell
@"
reg add "HKCU\Software\Microsoft\Windows\CurrentVersion\Run" /v "Philsurin247" /t REG_SZ /d "\"C:\Users\YourUsername\Downloads\files\RUN-PERMANENT.bat\"" /f
"@ | powershell -Command "& { Write-Host 'Auto-start configured' }"
```

### Method C: Task Scheduler (Runs Even When You're Away)

1. Open **Task Scheduler** (Win + R → `taskschd.msc`)
2. **Create Task** → **Triggers** → **New** → **Begin the task**: `At log on`
3. **Actions** → **New** → **Program/script**: `C:\Windows\System32\cmd.exe`
   - **Add arguments**: `/c "C:\Users\YourUsername\Downloads\files\RUN-PERMANENT.bat"`
   - **Start in**: `C:\Users\YourUsername\Downloads\files`
4. Check **Run with highest privileges** (if needed)

---

## Prerequisites for Permanent Hosting

| Component | Requirement |
|-----------|-------------|
| **PC** | Always on (no sleep/hibernate) |
| **XAMPP** | Apache + MySQL installed |
| **Node.js** | Installed and in PATH |
| **Cloudflare tunnel** | `cloudflared.exe` in project folder or `C:\Program Files\cloudflared\` |
| **Internet** | Static IP or dynamic DNS (Cloudflare tunnel handles this) |

### Required Windows Settings

1. **Disable sleep/hibernate**:
   - Control Panel → Power Options → Choose what the power buttons do → **Change settings that are currently unavailable**
   - Uncheck **Turn on fast startup**
   - Set sleep to **Never**

2. **Keep network connection**:
   - Network adapter settings → **Power management** tab
   - Uncheck **Allow the computer to turn off this device to save power**

---

## Files Included in This Repository

- `app.js` — Node.js port forwarder (port 3000)
- `devtunnel-proxy.js` — Apache ↔ tunnel proxy
- `start-proxy.ps1` — PowerShell startup script
- `RUN-PERMANENT.bat` — **Permanent startup script** (this repo)
- `appsscript_bridge.php` — Apps Script ↔ PHP bridge
- `.htaccess` — Apache security rules
- `config.php`, `index.php`, `dashboard.php`, `users.php` — PHP backend
- `database/schema.sql`, `database/seed.sql` — DB schema

---

## Port Configuration

| Service | Port | Notes |
|---------|------|-------|
| Apache | 80 | PHP + MySQL |
| Proxy | 3000 | Node.js port forwarder |
| MySQL | 3306 | Database |
| Tunnel | `lnmmkbpg-3000.asse.devtunnels.ms` | Cloudflare dev tunnel |

---

## Troubleshooting

| Symptom | Fix |
|---------|-----|
| **502 Bad Gateway** | Proxy not running — check `node devtunnel-proxy.js` |
| **Apache not responding** | Start XAMPP → Apache → Start |
| **Tunnel disconnects** | Restart `code-tunnel.exe` — Cloudflare tunnels expire after ~10 min idle |
| **MySQL not working** | Start XAMPP → MySQL → Start |
| **PC goes to sleep** | Disable sleep in Power Options |

---

## Security Notes

- **Never commit** `NEW-CREDENTIALS.txt`, `.clasp.json`, or `.env` to GitHub
- **Change admin password** after first login (default: `admin` / `admin123`)
- **Keep your PC secure** — the app is exposed to the internet via the tunnel
- **Use HTTPS** — Cloudflare tunnel provides SSL automatically

---

## Deployment Checklist

- [ ] PC set to never sleep/hibernate
- [ ] XAMPP installed (Apache + MySQL)
- [ ] Node.js installed
- [ ] `cloudflared.exe` downloaded
- [ ] `RUN-PERMANENT.bat` added to Startup folder
- [ ] Auto-start verified (restart PC to test)
- [ ] Login works, change default password
- [ ] Test on mobile phone (same Wi-Fi + tunnel)
