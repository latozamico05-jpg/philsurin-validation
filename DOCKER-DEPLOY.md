# Deploy PHILSURIN to Cloud Docker (24/7/365)

This script deploys PHILSURIN to a cloud server using Docker.
Your PC can be OFF — the system runs 24/7/365.

## Prerequisites

1. A cloud server (AWS EC2 t2.micro = Free tier, DigitalOcean, etc.)
2. Docker installed on the server
3. Git access to this repository
4. A domain name or IP address

## Quick Start

### Step 1: Push to GitHub
```bash
GITHUB-UPLOAD.bat
```

### Step 2: Clone on Your Cloud Server
```bash
ssh your-user@your-server.com
git clone https://github.com/YOUR-USERNAME/philsurin-validation.git
cd philsurin-validation
```

### Step 3: Configure for Production
```bash
# Create production environment file
cp docker-compose.yml docker-compose.prod.yml

# Edit with your DB credentials
nano docker-compose.prod.yml
```

### Step 4: Start the Stack
```bash
docker compose -f docker-compose.prod.yml up -d

# Verify
docker compose -f docker-compose.prod.yml ps
curl http://localhost/health.php
```

### Step 5: Set Up Reverse Proxy (HTTPS)

#### Option A: Cloudflare Proxy (Free, Recommended)
1. Add your domain to Cloudflare
2. Set DNS to "DNS only" (gray cloud)
3. Create a CNAME record: `your-domain.com` → `your-service.render.com`
4. Enable "Use Cloudflare Proxy"

#### Option B: Nginx Proxy Manager (Free)
1. Install Nginx Proxy Manager on the server
2. Add a proxy host: `your-domain.com` → `localhost:80`
3. Enable SSL with Let's Encrypt

## Docker Compose (Production)

```yaml
# docker-compose.prod.yml
version: '3.8'

services:
  db:
    image: mysql:8.0
    restart: unless-stopped
    environment:
      MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD}
      MYSQL_DATABASE: ${DB_NAME}
      MYSQL_USER: ${DB_USER}
      MYSQL_PASSWORD: ${DB_PASSWORD}
    volumes:
      - dbdata:/var/lib/mysql
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      interval: 15s
      timeout: 5s
      retries: 10

  app:
    image: php:8.2-apache
    restart: unless-stopped
    depends_on:
      db:
        condition: service_healthy
    ports:
      - "80:80"
    volumes:
      - .:/var/www/html
    environment:
      DB_HOST: db
      DB_NAME: ${DB_NAME}
      DB_USER: ${DB_USER}
      DB_PASSWORD: ${DB_PASSWORD}
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost/health.php"]
      interval: 30s
      timeout: 5s
      retries: 5

volumes:
  dbdata:
```

## Environment Variables

Create a `.env` file:

```env
DB_NAME=philsurin
DB_USER=philsurin
DB_PASSWORD=your-strong-password
DB_ROOT_PASSWORD=your-root-password
```

## Ubuntu/Debian Server Setup

```bash
# Install Docker
sudo apt update
sudo apt install -y docker.io docker-compose
sudo usermod -aG docker $USER

# Start Docker
sudo systemctl start docker
sudo systemctl enable docker

# Clone and deploy
git clone https://github.com/YOUR-USERNAME/philsurin-validation.git
cd philsurin-validation
docker compose -f docker-compose.prod.yml up -d

# Monitor
docker compose -f docker-compose.prod.yml logs -f
```

## Azure VM Setup

```bash
# Install Docker on Azure Ubuntu VM
sudo apt update
sudo apt install -y docker.io
sudo systemctl enable --now docker

# Install docker-compose
sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose

# Deploy
git clone https://github.com/YOUR-USERNAME/philsurin-validation.git
cd philsurin-validation
docker compose -f docker-compose.prod.yml up -d
```

## AWS EC2 Setup

```bash
# Install Docker on Amazon Linux 2
sudo yum update -y
sudo amazon-linux-extras install docker
sudo service docker start
sudo usermod -aG docker ec2-user

# Deploy
git clone https://github.com/YOUR-USERNAME/philsurin-validation.git
cd philsurin-validation
docker compose -f docker-compose.prod.yml up -d
```

## Uptime Monitoring (Free)

Use [UptimeRobot](https://uptimerobot.com):
1. Create a free account
2. Add a monitor: `http://your-domain.com/health.php`
3. Check interval: 5 minutes
4. Alert method: Email

## Security Checklist

- [ ] Change admin password after first login
- [ ] Use strong DB password
- [ ] Enable HTTPS (Cloudflare or Let's Encrypt)
- [ ] Keep Docker images updated
- [ ] Set up automatic backups
- [ ] Monitor logs regularly

## Troubleshooting

| Symptom | Fix |
|---------|-----|
| Containers not starting | `docker compose -f docker-compose.prod.yml up -d` |
| DB connection failed | Check DB credentials in `.env` |
| Site returns 404 | Check volume mount path |
| Docker error | `docker system prune -a` then redeploy |
| HTTPS not working | Check Cloudflare DNS settings |

## Files in This Repository

- `docker-compose.yml` — Docker setup (local + production)
- `README-CLOUD.md` — Cloud hosting setup guide
- `README-PERMANENT.md` — Local Windows auto-start guide
- `GITHUB-UPLOAD.bat` — GitHub upload script
