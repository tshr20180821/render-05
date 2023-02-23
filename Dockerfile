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

RUN cat /proc/version
RUN cat /etc/os-release
 
COPY index.html /var/www/html/
COPY crond.js /usr/src/app/
COPY start.sh /usr/src/app/

CMD ["sh","/usr/src/app/start.sh"]
