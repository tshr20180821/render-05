FROM php:8.2-apache

RUN apt-get update
RUN apt-get install -y curl
# RUN apt-get clean

RUN curl -sL https://deb.nodesource.com/setup_18.x | bash -
RUN apt-get install -y nodejs npm

RUN mkdir -p /usr/src/app
WORKDIR /usr/src/app
COPY package.json ./
RUN npm install

RUN node --version

RUN cat /proc/version
RUN cat /etc/os-release
 
COPY index.html /var/www/html/
