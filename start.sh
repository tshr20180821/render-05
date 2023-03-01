#!/bin/bash

set -x

ps aux

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

# npm audit

php -l /var/www/html/auth/crond.php
node -c crond.js

# php -f /usr/src/app/update_server.php

export DEPLOY_DATETIME=$(date +'%Y%m%d%H%M%S')

. /etc/apache2/envvars && exec /usr/sbin/apache2 -DFOREGROUND &
node crond.js
