#!/bin/bash
# PHILSURIN — VPS One-Click Deployment Script
# Run this on your VPS (Ubuntu 22.04 / Debian):
#   curl -sSL https://raw.githubusercontent.com/latozamico05-jpg/philsurin-validation/main/DEPLOY-VPS.sh | sudo bash
#
# Or upload this file to your VPS and run:  sudo bash DEPLOY-VPS.sh

set -e

echo "============================================"
echo "  PHILSURIN VPS Deployment"
echo "============================================"

# --- 1. Update system ---
echo "[1/6] Updating system packages..."
apt-get update -y
apt-get install -y curl git ufw ca-certificates gnupg lsb-release

# --- 2. Install Docker ---
echo "[2/6] Installing Docker..."
if ! command -v docker &> /dev/null; then
  curl -fsSL https://get.docker.com -o get-docker.sh
  sh get-docker.sh
  usermod -aG docker $USER || true
  rm -f get-docker.sh
fi

# --- 3. Install Docker Compose ---
echo "[3/6] Installing Docker Compose..."
if ! command -v docker compose &> /dev/null; then
  mkdir -p /usr/local/lib/docker/cli-plugins
  curl -SL https://github.com/docker/compose/releases/latest/download/docker-compose-linux-x86_64 \
    -o /usr/local/lib/docker/cli-plugins/docker-compose
  chmod +x /usr/local/lib/docker/cli-plugins/docker-compose
fi

# --- 4. Clone the repository ---
echo "[4/6] Cloning PHILSURIN repository..."
APP_DIR="/opt/philsurin"
if [ -d "$APP_DIR" ]; then
  cd "$APP_DIR"
  git pull origin main
else
  git clone https://github.com/latozamico05-jpg/philsurin-validation.git "$APP_DIR"
  cd "$APP_DIR"
fi

# --- 5. Configure environment ---
echo "[5/6] Configuring environment..."
if [ ! -f .env ]; then
  cp .env.example .env
fi
# Generate strong passwords if not set
sed -i "s/change-me-to-something-strong/$(openssl rand -base64 18)/" .env
sed -i "s/change-me-root-strong/$(openssl rand -base64 18)/" .env
chmod 600 .env

# --- 6. Start the stack ---
echo "[6/6] Starting PHILSURIN stack..."
docker compose -f docker-compose.prod.yml up -d --build

echo ""
echo "============================================"
echo "  PHILSURIN deployed successfully!"
echo "============================================"
echo ""
echo "  App URL:     http://$(curl -s ifconfig.me || echo '<VPS-IP>')"
echo "  Health:      http://$(curl -s ifconfig.me || echo '<VPS-IP>')/health.php"
echo "  Login:       admin / admin123   (CHANGE THIS AFTER FIRST LOGIN!)"
echo ""
echo "  Manage:      cd /opt/philsurin && docker compose -f docker-compose.prod.yml ps"
echo "  Logs:        cd /opt/philsurin && docker compose -f docker-compose.prod.yml logs -f"
echo "  Stop:        cd /opt/philsurin && docker compose -f docker-compose.prod.yml down"
echo ""
echo "  IMPORTANT: Open port 80 (HTTP) and 443 (HTTPS) in your VPS firewall/security group."
echo "  For HTTPS: Add Cloudflare in front of your VPS (free SSL)."
echo ""