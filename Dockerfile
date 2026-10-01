FROM php:8.2-apache

# Required PHP extensions for MySQL/PDO
RUN docker-php-ext-install pdo pdo_mysql \
    && a2enmod rewrite

# Copy complete application
COPY . /var/www/html/

# Runtime directories must be writable by Apache/PHP
RUN mkdir -p /var/www/html/backend/logs /var/www/html/backend/uploads \
    && chown -R www-data:www-data /var/www/html/backend/logs /var/www/html/backend/uploads

EXPOSE 80
