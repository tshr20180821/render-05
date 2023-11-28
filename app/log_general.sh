#!/bin/bash

pid=$$

while read line; do
  dt=$(date '+%Y-%m-%d %H:%M:%S.%3N')
  curl -X POST -s -o /dev/null -H "Content-Type: text/plain" -d "${dt} ${RENDER_EXTERNAL_HOSTNAME} ${DEPLOY_DATETIME} ${pid} ${1} ${line}" \
    "https://logs-01.loggly.com/inputs/${LOGGLY_TOKEN}/tag/${RENDER_EXTERNAL_HOSTNAME},${RENDER_EXTERNAL_HOSTNAME}_${DEPLOY_DATETIME},${1}/" &

  echo "${dt} ${RENDER_EXTERNAL_HOSTNAME} ${DEPLOY_DATETIME} ${pid} ${1} ${line}"
done
