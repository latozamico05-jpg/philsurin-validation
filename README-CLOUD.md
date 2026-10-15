# PHILSURIN — Deploy to Cloud (24/7/365, even when your PC is OFF)

This setup lets you host PHILSURIN on a **cloud server** using Docker. Your PC can be OFF — the system runs 24/7/365.

---

## Why Docker + Cloud?

| Local Hosting | Cloud Docker Hosting |
|---------------|---------------------|
| Your PC must be ON 24/7 | Runs on cloud server (always up) |
| No sleep/hibernate | Auto-restarts on failure |
| Single user / limited | Scales as needed |
| Port forwarding needed | Direct internet access |

---

## Option A: Deploy to Docker Cloud (Render.com) — Free

[Render.com](https://render.com) offers a free tier with auto-restart.

### Step 1: Build Locally and Push to Docker Hub (Optional)

```bash
# Build the image
docker build -t philsurin-app .

# Tag and push to Docker Hub
docker tag philsurin-app yourusername/philsurin-app
docker push yourusername/philsurin-app
```

### Step 2: Deploy on Render.com

1. Sign up at [render.com](https://render.com)
2. **New +** → **Web Service**
3. Connect your GitHub repo
4. Set:
   - **Dockerfile**: `Dockerfile` (create one below)
   - **Port**: `80`
   - **Health Check**: `http://localhost/health.php`
5. Click **Create Web Service**
6. Wait for the build to complete

### Step 3: Connect Database

Render's free tier doesn't include a database. Use:

1. **New +** → **PostgreSQL Database**
2. Create the database (Render handles this automatically)
3. Update `config.php` with your Render DB credentials:
   - `DB_HOST`: your Render DB hostname
   - `DB_NAME`: your database name
   - `DB_USER`, `DB_PASS`: your database credentials

### Step 4: Verify

Visit your Render URL: `https://your-service.onrender.com`

---

## Option B: Deploy to AWS EC2 (Free Tier) — $0 for 12 months

### Step 1: Launch an EC2 Instance

1. Go to [AWS Console](https://aws.amazon.com/console)
2. **EC2** → **Launch Instance**
3. Choose **Amazon Linux 2023** (or Ubuntu 22.04)
4. **Instance type**: `t2.micro` (Free tier eligible)
5. **Storage**: 30GB (root)
6. **Security group**: Allow ports 80 (HTTP), 443 (HTTPS), 22 (SSH)
7. **Launch** → Select key pair → **Launch Instance**

### Step 2: Install Docker on EC2

```bash
sudo yum update -y
sudo yum install -y docker
sudo service docker start
sudo usermod -aG docker ec2-user
# Log out and log back in to activate Docker
```

### Step 3: Clone and Deploy

```bash
git clone https://github.com/YOUR-USERNAME/philsurin-validation.git
cd philsurin-validation

# Create a Docker environment file
cp docker-compose.yml docker-compose.prod.yml
# Edit docker-compose.prod.yml with your DB credentials

# Start the stack
docker compose -f docker-compose.prod.yml up -d

# Verify
curl http://localhost:8080/health.php
```

### Step 4: Set Up SSL (HTTPS)

Use **Cloudflare** (free) for SSL:
1. Add your domain to Cloudflare
2. Use Cloudflare's **Universal SSL** (free)
3. Point your domain DNS to your EC2 instance

---

## Option C: Local PC as Backup Server (Always On 24/7)

If you want to keep using your PC but ensure it's always running:

1. **Disable sleep/hibernate** (Windows Power Settings)
2. **Add to Startup Folder** (already done via `RUN-PERMANENT.bat`)
3. **Keep network adapter awake** (Power Management → "Allow this device to wake the computer")

This is the **current state** of your system. Test it now:
- `http://localhost:80` → Apache
- `http://localhost:3000` → Proxy
- `lnmmkbpg-3000.asse.devtunnels.ms` → Cloudflare tunnel

---

## Files in This Repository (GitHub)

- `app.js` — Node.js port forwarder (port 3000)
- `devtunnel-proxy.js` — Apache ↔ tunnel proxy
- `start-proxy.ps1` — PowerShell startup script
- `RUN-PERMANENT.bat` — Permanent startup script (Windows auto-start)
- `SETUP-PERMANENT.bat` — One-click setup for auto-start
- `README-PERMANENT.md` — Permanent hosting guide
- `README-CLOUD.md` — Cloud hosting setup (this file)
- `GITHUB-UPLOAD.bat` — GitHub upload script

---

## Port Mapping

| Service | Local Port | Cloud Port |
|---------|-----------|------------|
| Apache | 80 | 80 (or 8080) |
| Proxy | 3000 | 3000 |
| Database | 3306 | Via cloud DB |

---

## Security Checklist

- [ ] Change admin password after first login
- [ ] Use strong DB password
- [ ] Enable HTTPS (Cloudflare universal SSL or Let's Encrypt)
- [ ] Keep Docker images updated
- [ ] Monitor logs regularly
- [ ] Set up UptimeRobot (free) to ping `/health.php` every 5 min

---

## Troubleshooting

| Symptom | Fix |
|---------|-----|
| 502 Bad Gateway | Docker containers not running — `docker ps` |
| DB connection failed | Check DB credentials in `config.php` |
| Container restarts | Check logs — `docker logs <container>` |
| Site not accessible | Check firewall — allow ports 80/443 |
| Docker down | `docker system prune` to clean up |

---

## Uptime Monitoring (Free)

Use [UptimeRobot](https://uptimerobot.com):
1. Create a free account
2. Add a monitor: `http://your-domain.com/health.php`
3. Check interval: 5 minutes
4. Alert method: Email

---

## Quick Start: Cloud Deployment (Render.com)

```bash
# 1. Push to GitHub
GITHUB-UPLOAD.bat

# 2. Go to render.com → New + → Web Service
# 3. Connect repo → Build Docker → Port 80
# 4. Add PostgreSQL database → Update config.php
# 5. Done — system runs 24/7/365
```

---

## Files in This Repository

- `docker-compose.yml` — Docker setup (local + cloud)
- `README-PERMANENT.md` — Local permanent hosting (Windows auto-start)
- `README-CLOUD.md` — Cloud hosting setup (this file)
- `RUN-PERMANENT.bat` — Local Windows auto-start
- `SETUP-PERMANENT.bat` — Local Windows auto-start setup
- `GITHUB-UPLOAD.bat` — GitHub upload script
- `.github/workflows/ci.yml` — GitHub CI/CD workflow
