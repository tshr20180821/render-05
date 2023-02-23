FROM php:8.2-apache

RUN apt-get update
RUN apt-get install -y node

COPY index.html /var/www/html/
