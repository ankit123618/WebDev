# Base image/dependency where app runs [name:version-varient/server]
FROM php:8.4-apache  
# Working directory of app [containers working directory]
WORKDIR /var/www/html 
# .htaccess enable mod rewrite
RUN a2enmod rewrite
# Copy souce code to container [local:container]
COPY . /var/www/html
# Installing dependencies
RUN docker-php-ext-install pdo_mysql pdo mysqli 
RUN mkdir -p /var/www/html/storage/logs
RUN chown -R www-data:www-data /var/www/html/storage

EXPOSE 80
