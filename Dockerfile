FROM php:8.2-apache

# เปิด mod_rewrite
RUN a2enmod rewrite

# ติดตั้ง curl extension
RUN docker-php-ext-install curl 2>/dev/null || true

# อนุญาตเขียนไฟล์ inbox/outbox
RUN mkdir -p /var/www/html/data && chmod 777 /var/www/html/data

# คัดลอกโค้ด
COPY index.php /var/www/html/index.php

# Apache ฟัง PORT จาก environment
RUN sed -i 's/Listen 80/Listen ${PORT}/' /etc/apache2/ports.conf \
 && sed -i 's/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/' /etc/apache2/sites-available/000-default.conf

# ให้ Apache เข้าถึง AllowOverride
RUN echo '<Directory /var/www/html>\n  AllowOverride All\n  Options -Indexes\n</Directory>' \
    >> /etc/apache2/apache2.conf

EXPOSE 8080

ENV RENDER=true
CMD ["apache2-foreground"]
