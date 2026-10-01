FROM php:8.2-apache

# Required PHP extensions for MySQL/PDO
RUN docker-php-ext-install pdo pdo_mysql

# Enable Apache rewrite
RUN a2enmod rewrite

# Copy complete application
COPY . /var/www/html/

# Apache permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 80
