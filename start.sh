#!/bin/bash

set -x

node --version
php --version
apache2 -v
cat /proc/version
cat /etc/os-release
strings /etc/localtime
echo 'Processor Count : ' $(grep -c -e processor /proc/cpuinfo)
head -n $(($(< /proc/cpuinfo wc -l) / $(grep -c -e processor /proc/cpuinfo))) /proc/cpuinfo
hostname -A
whoami
free -h
df -h

# npm audit
npm list --depth=0

php -l /var/www/html/auth/crond.php
node -c crond.js

export BLOWFISH_SECRET=$(cat /dev//urandom | tr -dc 'a-zA-Z0-9' | fold -w 32 | head -n 1)

export DEPLOY_DATETIME=$(date +'%Y%m%d%H%M%S')

echo ServerName ${RENDER_EXTERNAL_HOSTNAME} >/etc/apache2/sites-enabled/server_name.conf

. /etc/apache2/envvars && exec /usr/sbin/apache2 -DFOREGROUND &

sleep 3s && ps aux && apt-get update && apt-get -u upgrade &

node crond.js
