# apache php nodejs

FROM php:8.2-apache

RUN mkdir -p /usr/src/app

WORKDIR /usr/src/app
COPY ./package.json ./
COPY ./composer.json ./

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN /usr/bin/composer --version
ENV COMPOSER_ALLOW_SUPERUSER=1

# curl : curl -sL https://deb.nodesource.com/setup_18.x | bash -
# libonig-dev : mbstring
# tzdata : ln -sf /usr/share/zoneinfo/Asia/Tokyo /etc/localtime
# libsqlite3-0 : php sqlite
# libzip-dev : apache/log4php
RUN apt-get update \
 && apt-get install -y curl libonig-dev tzdata libsqlite3-0 libzip-dev \
 && pecl install apcu \
 && docker-php-ext-enable apcu \
 && docker-php-ext-install -j$(nproc) pdo_mysql mysqli mbstring zip \
 && composer install --apcu-autoloader \
 && curl -sL https://deb.nodesource.com/setup_18.x | bash - \
 && apt-get install -y nodejs \
 && npm install \
 && npm update -g \
 && npm audit fix \
 && apt-get upgrade -y \
 && npm cache clean --force \
 && pecl clear-cache \
 && apt-get purge -y --auto-remove gcc libc6-dev make \
 && apt-get clean \
 && rm -rf /var/lib/apt/lists/*

RUN mkdir -p /var/www/html/auth \
 && mkdir -p /var/www/html/phpmyadmin

COPY ./php.ini ${PHP_INI_DIR}/

RUN a2dissite -q 000-default.conf
# RUN a2enmod -q authz_groupfile rewrite
RUN a2enmod -q authz_groupfile

COPY ./apache.conf /etc/apache2/sites-enabled/

# basic auth
COPY .htpasswd /var/www/html/
RUN chmod 644 /var/www/html/.htpasswd

COPY ./index.html /var/www/html/
COPY ./auth/*.php /var/www/html/auth/

COPY ./log4js.json /usr/src/app/
COPY ./crond.js /usr/src/app/
COPY ./start.js /usr/src/app/
COPY ./start.sh /usr/src/app/

RUN ln -sf /usr/share/zoneinfo/Asia/Tokyo /etc/localtime

RUN curl -o /tmp/phpMyAdmin.tar.xz https://files.phpmyadmin.net/phpMyAdmin/5.2.1/phpMyAdmin-5.2.1-all-languages.tar.xz \
 && tar xf /tmp/phpMyAdmin.tar.xz --strip-components=1 -C /var/www/html/phpmyadmin \
 && rm /tmp/phpMyAdmin.tar.xz \
 && chown www-data:www-data /var/www/html/phpmyadmin -R

COPY ./config.inc.php /var/www/html/phpmyadmin/

# CMD ["bash","/usr/src/app/start.sh"]
ENTRYPOINT ["bash","/usr/src/app/start.sh"]

