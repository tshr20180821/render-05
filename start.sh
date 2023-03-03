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
npm list --depth=0

php -l /var/www/html/auth/crond.php
node -c crond.js

export DEPLOY_DATETIME=$(date +'%Y%m%d%H%M%S')

mkdir /tmp/db
php /usr/src/app/init_sqlite.php
chgrp -Rv www-data /tmp/db
ls -lang /tmp/db

. /etc/apache2/envvars && exec /usr/sbin/apache2 -DFOREGROUND &

sleep 3s && ps aux

node crond.js
