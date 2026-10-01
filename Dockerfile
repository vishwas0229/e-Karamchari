FROM php:8.2-apache

<<<<<<< HEAD
# Required PHP extensions for MySQL/PDO
RUN docker-php-ext-install pdo pdo_mysql

# Enable Apache rewrite
RUN a2enmod rewrite

# Copy complete application
COPY . /var/www/html/

# Apache permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html
=======
RUN docker-php-ext-install pdo pdo_mysql \
    && a2enmod rewrite

COPY . /var/www/html/

RUN mkdir -p /var/www/html/backend/logs /var/www/html/backend/uploads \
    && chown -R www-data:www-data /var/www/html/backend/logs /var/www/html/backend/uploads
>>>>>>> refs/remotes/origin/main

EXPOSE 80
