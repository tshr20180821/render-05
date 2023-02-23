#!/bin/bash

set -x

source /etc/apache2/envvars && exec /usr/sbin/apache2 -DFOREGROUND &
node crond.js
