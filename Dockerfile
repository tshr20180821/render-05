# apache php nodejs

FROM php:8.2-apache

RUN mkdir -p /usr/src/app
WORKDIR /usr/src/app
COPY ./package.json ./

# curl : curl -sL https://deb.nodesource.com/setup_18.x | bash -
# libonig-dev : mbstring
# tzdata : ln -sf /usr/share/zoneinfo/Asia/Tokyo /etc/localtime
# libsqlite3-0 : php sqlite
RUN apt-get update \
 && apt-get install -y curl libonig-dev tzdata libsqlite3-0 \
 && pecl install apcu \
 && docker-php-ext-enable apcu \
 && docker-php-ext-install -j$(nproc) pdo_mysql mysqli mbstring \
 && curl -sL https://deb.nodesource.com/setup_18.x | bash - \
 && apt-get install -y nodejs \
 && apt-get clean \
 && rm -rf /var/lib/apt/lists/* \
 && npm install \
 && npm update -g \
 && npm audit fix \
 && npm cache clean --force

RUN mkdir -p /var/www/html/auth/

COPY ./php.ini ${PHP_INI_DIR}/

RUN a2dissite -q 000-default.conf
# RUN a2enmod -q authz_groupfile rewrite
RUN a2enmod -q authz_groupfile

COPY ./apache.conf /etc/apache2/sites-enabled/

# basic auth
COPY .htpasswd /var/www/html/
RUN chmod 644 /var/www/html/.htpasswd

COPY ./index.html /var/www/html/
COPY ./crond.php /var/www/html/auth/
COPY ./update_sqlite.php /var/www/html/auth/

COPY ./crond.js /usr/src/app/
COPY ./start.sh /usr/src/app/

RUN ln -sf /usr/share/zoneinfo/Asia/Tokyo /etc/localtime

RUN cd /tmp && curl -o /tmp/phpMyAdmin.tar.xz https://files.phpmyadmin.net/phpMyAdmin/5.2.1/phpMyAdmin-5.2.1-all-languages.tar.xz \
 && tar xf /tmp/phpMyAdmin.tar.xz \
 && ls -Rlang /tmp

# CMD ["bash","/usr/src/app/start.sh"]
ENTRYPOINT ["bash","/usr/src/app/start.sh"]

