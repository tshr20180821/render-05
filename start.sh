#!/bin/bash

set -x

ps aux

echo 'Processor Count : ' $(grep -c -e processor /proc/cpuinfo)

. /etc/apache2/envvars && exec /usr/sbin/apache2 -DFOREGROUND &
node crond.js
