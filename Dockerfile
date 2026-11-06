FROM debian:bookworm-slim
ARG PHPV=8.2.32-1~deb12u1
RUN apt-get update && apt-get install -y --no-install-recommends \
    apache2 libapache2-mod-php8.2=$PHPV php8.2=$PHPV php8.2-cli=$PHPV php8.2-common=$PHPV \
    php8.2-mysql=$PHPV php8.2-opcache=$PHPV php8.2-readline=$PHPV php8.2-mbstring=$PHPV \
    php8.2-curl=$PHPV php8.2-xml=$PHPV php8.2-gd=$PHPV ca-certificates \
 && rm -rf /var/lib/apt/lists/*
COPY apache/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY php/99-app.ini /etc/php/8.2/apache2/conf.d/99-app.ini
COPY . /var/www/html
RUN a2enmod rewrite \
 && sed -i 's/^Listen 80$/Listen 8080/' /etc/apache2/ports.conf \
 && sed -i '/<IfModule ssl_module>/,/<\/IfModule>/d; /<IfModule mod_gnutls.c>/,/<\/IfModule>/d' /etc/apache2/ports.conf \
 && mkdir -p /var/run/apache2 /var/lock/apache2 /var/log/apache2 \
 && chown -R www-data:www-data /var/run/apache2 /var/lock/apache2 /var/log/apache2
USER www-data
EXPOSE 8080
CMD ["apachectl","-D","FOREGROUND"]
