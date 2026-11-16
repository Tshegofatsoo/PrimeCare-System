# PrimeCare web + API (PHP 8 / Apache). Used by docker-compose.yml.
FROM php:8.2-apache

RUN docker-php-ext-install pdo_mysql \
 && a2enmod rewrite headers \
 && printf '<Directory /var/www/html>\n    AllowOverride All\n</Directory>\n' > /etc/apache2/conf-available/primecare.conf \
 && a2enconf primecare
