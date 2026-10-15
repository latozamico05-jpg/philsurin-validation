# PHILSURIN — PHP 8.2 + Apache, runs with PC OFF on any cloud
FROM php:8.2-apache

# MySQL driver (your app uses PDO mysql) + zip for composer if needed
RUN docker-php-ext-install pdo pdo_mysql mysqli \
  && a2enmod rewrite \
  && apt-get update && apt-get install -y --no-install-recommends curl \
  && rm -rf /var/lib/apt/lists/*

# Serve this repo root as the site
COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html

# Apache: allow .htaccess, listen on 80 + 8080 (Render/Railway inject PORT)
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf
RUN printf 'Listen 80\nListen 8080\n' > /etc/apache2/ports.conf

EXPOSE 80 8080

HEALTHCHECK --interval=30s --timeout=5s --retries=5 \
  CMD curl -f http://localhost/health.php || exit 1

CMD ["apache2-foreground"]
