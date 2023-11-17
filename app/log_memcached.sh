#!/bin/bash

pid=$$

while read line; do
dt=$(date '+%Y-%m-%d %H:%M:%S.%3N')
echo "${dt} ${RENDER_EXTERNAL_HOSTNAME} ${DEPLOY_DATETIME} ${pid} Memcached ${line}"
done
