FROM php:8.2-apache

ENV NODE_ENV=production

WORKDIR /usr/src/app

COPY ./php.ini ${PHP_INI_DIR}/
COPY ./index.html /var/www/html/
COPY .htpasswd /var/www/html/
COPY ./apache.conf /etc/apache2/sites-enabled/
COPY ./app/*.json /usr/src/app/
# render-07
COPY --chmod=755 ./bin/gpg /tmp/
COPY ./apt-fast.conf /tmp/

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1
ENV NODE_MAJOR=20

# binutils : strings
# ca-certificates : node.js
# curl : node.js
# default-jdk : javac
# libmemcached-dev : pecl memcached
# gnupg : node.js
# libonig-dev : mbstring
# libsqlite3-0 : php sqlite
# libssl-dev : pecl memcached
# libzip-dev : docker-php-ext-configure zip --with-zip
# memcached : memcached
# nodejs : nodejs
# tzdata : ln -sf /usr/share/zoneinfo/Asia/Tokyo /etc/localtime
# zlib1g-dev : pecl memcached
RUN mkdir -p /etc/apt/keyrings \
 && curl -fsSL 'https://keyserver.ubuntu.com/pks/lookup?op=get&search=0xA2166B8DE8BDC3367D1901C11EE2FF37CA8DA16B' | /tmp/gpg --dearmor -o /etc/apt/keyrings/apt-fast.gpg \
 && echo "deb [signed-by=/etc/apt/keyrings/apt-fast.gpg] http://ppa.launchpad.net/apt-fast/stable/ubuntu jammy main" | tee /etc/apt/sources.list.d/apt-fast.list \
 && curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | /tmp/gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg \
 && echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_$NODE_MAJOR.x nodistro main" | tee /etc/apt/sources.list.d/nodesource.list \
 && apt-get update \
 && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends apt-fast \
 && cp -f /tmp/apt-fast.conf /etc/ \
 && apt-fast  install -y --no-install-recommends \
  binutils \
  ca-certificates \
  curl \
  default-jdk \
  libmemcached-dev \
  libonig-dev \
  libsqlite3-0 \
  libssl-dev \
  libzip-dev \
  memcached \
  nodejs \
  tzdata \
  zlib1g-dev \
 && MAKEFLAGS="-j $(nproc)" pecl install apcu \
 && docker-php-ext-enable apcu \
 && MAKEFLAGS="-j $(nproc)" pecl install memcached \
 && docker-php-ext-enable memcached \
 && docker-php-ext-configure zip --with-zip \
 && docker-php-ext-install -j$(nproc) pdo_mysql mysqli mbstring \
 && /usr/bin/composer --version \
 && composer install --apcu-autoloader \
 && npm install \
 && npm update -g \
 && npm audit fix \
 && apt-get upgrade -y --no-install-recommends \
 && npm cache clean --force \
 && pecl clear-cache \
 && apt-get purge -y --auto-remove gcc libc6-dev make \
 && apt-get clean \
 && rm -rf /var/lib/apt/lists/* \
 && mkdir -p /var/www/html/auth \
 && mkdir -p /var/www/html/phpmyadmin \
 && a2dissite -q 000-default.conf \
 && a2enmod -q authz_groupfile rewrite \
 && ln -sf /usr/share/zoneinfo/Asia/Tokyo /etc/localtime \
 && chmod 644 /var/www/html/.htpasswd \
 && curl -o /tmp/phpMyAdmin.tar.xz https://files.phpmyadmin.net/phpMyAdmin/5.2.1/phpMyAdmin-5.2.1-all-languages.tar.xz \
 && tar xf /tmp/phpMyAdmin.tar.xz --strip-components=1 -C /var/www/html/phpmyadmin \
 && rm /tmp/phpMyAdmin.tar.xz \
 && chown www-data:www-data /var/www/html/phpmyadmin -R \
 && curl -L -O https://github.com/xerial/sqlite-jdbc/releases/download/3.43.2.0/sqlite-jdbc-3.43.2.0.jar \
 && curl -L -O https://repo1.maven.org/maven2/org/slf4j/slf4j-api/2.0.9/slf4j-api-2.0.9.jar \
 && curl -L -O https://repo1.maven.org/maven2/org/slf4j/slf4j-nop/2.0.9/slf4j-nop-2.0.9.jar

COPY ./app/* /usr/src/app/
COPY --chmod=755 ./app/log.sh /usr/src/app/
COPY ./auth/log.php /usr/src/app/
COPY ./config.inc.php /var/www/html/phpmyadmin/

COPY ./auth/*.php /var/www/html/auth/
COPY ./src/*.java /usr/src/app/
RUN javac /usr/src/app/*.java

# CMD ["bash","/usr/src/app/start.sh"]
ENTRYPOINT ["bash","/usr/src/app/start.sh"]
