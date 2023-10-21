# apache php nodejs

FROM php:8.2-apache

ENV NODE_ENV=production

WORKDIR /usr/src/app
COPY ./app/* ./

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN /usr/bin/composer --version
ENV COMPOSER_ALLOW_SUPERUSER=1
ENV NODE_MAJOR=20

# binutils : strings
# ca-certificates : node.js
# curl : node.js
# gnupg : node.js
# libonig-dev : mbstring
# libsqlite3-0 : php sqlite
# tzdata : ln -sf /usr/share/zoneinfo/Asia/Tokyo /etc/localtime
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
  binutils \
  ca-certificates \
  curl \
  default-jdk \
  gnupg \
  libonig-dev \
  libsqlite3-0 \
  libzip-dev \
  tzdata \
 && pecl install apcu \
 && docker-php-ext-enable apcu \
 && docker-php-ext-configure zip --with-zip \
 && docker-php-ext-install -j$(nproc) pdo_mysql mysqli mbstring \
 && composer install --apcu-autoloader \
 && mkdir -p /etc/apt/keyrings \
 && curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg \
 && echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_$NODE_MAJOR.x nodistro main" | tee /etc/apt/sources.list.d/nodesource.list \
 && apt-get update \
 && apt-get install -y nodejs \
 && npm install \
 && npm update -g \
 && npm audit fix \
 && apt-get upgrade -y --no-install-recommends \
 && npm cache clean --force \
 && pecl clear-cache \
 && apt-get purge -y --auto-remove gcc libc6-dev make \
 && apt-get clean \
 && rm -rf /var/lib/apt/lists/*

RUN mkdir -p /var/www/html/auth \
 && mkdir -p /var/www/html/phpmyadmin

COPY ./php.ini ${PHP_INI_DIR}/

RUN a2dissite -q 000-default.conf \
 && a2enmod -q authz_groupfile rewrite

COPY ./apache.conf /etc/apache2/sites-enabled/
COPY ./log.sh /usr/src/app/

# basic auth
COPY .htpasswd /var/www/html/
RUN chmod +x /usr/src/app/log.sh \
 && chmod 644 /var/www/html/.htpasswd

COPY ./index.html /var/www/html/
COPY ./auth/log.php /usr/src/app/
COPY ./auth/*.php /var/www/html/auth/

COPY ./log4js.json /usr/src/app/
COPY ./MyUtils.js /usr/src/app/
COPY ./crond.js /usr/src/app/
COPY ./start.js /usr/src/app/
COPY ./start.sh /usr/src/app/

RUN ln -sf /usr/share/zoneinfo/Asia/Tokyo /etc/localtime

RUN curl -o /tmp/phpMyAdmin.tar.xz https://files.phpmyadmin.net/phpMyAdmin/5.2.1/phpMyAdmin-5.2.1-all-languages.tar.xz \
 && tar xf /tmp/phpMyAdmin.tar.xz --strip-components=1 -C /var/www/html/phpmyadmin \
 && rm /tmp/phpMyAdmin.tar.xz \
 && chown www-data:www-data /var/www/html/phpmyadmin -R

COPY ./config.inc.php /var/www/html/phpmyadmin/

COPY ./src/*.java /usr/src/app/
RUN curl -L -O https://github.com/xerial/sqlite-jdbc/releases/download/3.43.2.0/sqlite-jdbc-3.43.2.0.jar \
 && curl -L -O https://repo1.maven.org/maven2/org/slf4j/slf4j-api/2.0.9/slf4j-api-2.0.9.jar \
 && curl -L -O https://repo1.maven.org/maven2/org/slf4j/slf4j-nop/2.0.9/slf4j-nop-2.0.9.jar \
 && javac /usr/src/app/*.java

# CMD ["bash","/usr/src/app/start.sh"]
ENTRYPOINT ["bash","/usr/src/app/start.sh"]

