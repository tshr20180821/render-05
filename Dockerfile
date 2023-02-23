FROM php:8.2-apache

RUN apt-get update
RUN apt-get install -y nodejs npm
RUN apt-get clean

RUN mkdir -p /usr/src/app
WORKDIR /usr/src/app
COPY package.json ./
RUN npm install

RUN node --version

COPY index.html /var/www/html/
