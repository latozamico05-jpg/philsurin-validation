# PHILSURIN — PHP 8.2 + Apache, runs 24/7 on Render/Railway/Fly with PC OFF
FROM php:8.2-apache

# PDO mysql (primary) + sqlite (instant zero-config fallback) + mysqli
RUN docker-php-ext-install pdo pdo_mysql pdo_sqlite mysqli \
  && a2enmod rewrite headers \
  && apt-get update && apt-get install -y --no-install-recommends curl sqlite3 \
  && rm -rf /var/lib/apt/lists/*

# Serve this repo root as the site
COPY . /var/www/html/
RUN mkdir -p /var/www/html/data \
  && chown -R www-data:www-data /var/www/html \
  && chmod -R 775 /var/www/html/data

# Apache: allow .htaccess
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
  && echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Dynamic PORT for Render/Railway (they inject $PORT, e.g. 10000).
# Falls back to 80 locally / 8080 in docker-compose.
COPY start-apache.sh /usr/local/bin/start-apache.sh
RUN chmod +x /usr/local/bin/start-apache.sh

EXPOSE 80 8080 10000

HEALTHCHECK --interval=30s --timeout=5s --retries=5 \
  CMD curl -f http://localhost:${PORT:-80}/health.php || curl -f http://localhost:80/health.php || exit 1

CMD ["/usr/local/bin/start-apache.sh"]
