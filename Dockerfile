# BASE PHP-FPM ---------------------------------------------------------------------------------------------
# This Base Php-FPM docker image can be seperated into other repository
# ----------------------------------------------------------------------------------------------------------
FROM quay.io/cashrewards/php-base-ms

# PHP-FPM Config-------------------------------------------------------------------------------------------
# ..
COPY ./docker-config/php-fpm/conf/laravel.conf /usr/local/etc/php-fpm.d/laravel.conf

# NGINX Config ----------------------------------------------------------------------------------------------
# This file for sharing with Nginx Container
# Following copy path should be same as Nginx Container path
# ..
COPY ./docker-config/nginx/conf.d/ /etc/nginx/conf.d/

# SOURCE CODE  ----------------------------------------------------------------------------------------------
# ..
COPY ./src/ /var/www/html/

# COMPOSER     ----------------------------------------------------------------------------------------------
ENV COMPOSER_ALLOW_SUPERUSER 1
RUN /usr/bin/curl -sS https://getcomposer.org/installer |/usr/local/bin/php \
    && /bin/mv composer.phar /usr/local/bin/composer \
    && cd /var/www/html \
    && /usr/local/bin/composer update

# SET FILE PERMISSION  --------------------------------------------------------------------------------------
RUN chown -R :www-data /var/www/html \
    && chmod -R ug+rwx /var/www/html/storage /var/www/html/bootstrap/cache

VOLUME ["/etc/nginx/conf.d/", "/var/www/html/"]