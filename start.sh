#!/bin/bash

set -x

ps aux

. /etc/apache2/envvars && exec /usr/sbin/apache2 -DFOREGROUND &
node crond.js
