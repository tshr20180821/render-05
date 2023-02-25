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

npm audit

touch /tmp/$(hostname)
ls -lang /tmp/

. /etc/apache2/envvars && exec /usr/sbin/apache2 -DFOREGROUND &
node crond.js
