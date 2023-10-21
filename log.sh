#!/bin/bash

while read line; do
  curl -X POST -s -o /dev/null -H "Content-Type: text/plain" -d "${line}" "https://logs-01.loggly.com/inputs/${LOGGLY_TOKEN}/tag/${RENDER_EXTERNAL_HOSTNAME},${RENDER_EXTERNAL_HOSTNAME}_${DEPLOY_DATETIME},apache,${1}/" &
  echo "${line}"
done
