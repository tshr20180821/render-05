FROM php:8.2-apache

RUN apt-get update
RUN apt-get upgrade -y
RUN apt-get install -y curl libonig-dev
RUN docker-php-ext-install -j$(nproc) pdo_mysql mysqli
RUN docker-php-ext-install -j$(nproc) mbstring

RUN curl -sL https://deb.nodesource.com/setup_18.x | bash -
RUN apt-get install -y nodejs
RUN apt-get clean

RUN mkdir -p /usr/src/app
WORKDIR /usr/src/app
COPY package.json ./
RUN npm install
RUN npm update -g

RUN node --version
RUN php --version
RUN httpd -v

RUN cat /proc/version
RUN cat /etc/os-release
RUN strings /etc/localtime

RUN mkdir -p /var/www/html/auth/

COPY ./config/php.ini ${PHP_INI_DIR}/

RUN a2dissite -q 000-default.conf
# RUN a2enmod -q authz_groupfile rewrite
RUN a2enmod -q authz_groupfile

COPY ./apache.conf /etc/apache2/sites-enabled/
RUN htpasswd -c -b /var/www/html/.htpasswd ${BASIC_USER} ${BASIC_PASSWORD}

RUN chmod 644 /var/www/html/.htpasswd

COPY index.html /var/www/html/
COPY crond.php /var/www/html/auth/

COPY crond.js /usr/src/app/
COPY start.sh /usr/src/app/

CMD ["sh","/usr/src/app/start.sh"]
