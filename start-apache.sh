#!/bin/bash
set -e
PORT_VAL="${PORT:-80}"
echo "[philsurin] Starting Apache on port ${PORT_VAL}..."
printf 'Listen %s\n' "$PORT_VAL" > /etc/apache2/ports.conf
if [ -f /etc/apache2/sites-enabled/000-default.conf ]; then
  sed -i "s/<VirtualHost \*:.*>/<VirtualHost *:${PORT_VAL}>/" /etc/apache2/sites-enabled/000-default.conf || true
fi
mkdir -p /var/www/html/data
chown -R www-data:www-data /var/www/html/data || true
chmod -R 775 /var/www/html/data || true
exec apache2-foreground
