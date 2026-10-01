FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql \
    && a2enmod rewrite

COPY . /var/www/html/

RUN mkdir -p /var/www/html/backend/logs /var/www/html/backend/uploads \
    && chown -R www-data:www-data /var/www/html/backend/logs /var/www/html/backend/uploads

EXPOSE 80
