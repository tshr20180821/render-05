#!/bin/bash

while read line; do
  curl -X POST -s -o /dev/null -H "Content-Type: text/plain" -d "${line}" \
    "https://logs-01.loggly.com/inputs/${LOGGLY_TOKEN}/tag/${RENDER_EXTERNAL_HOSTNAME},${RENDER_EXTERNAL_HOSTNAME}_${DEPLOY_DATETIME},apache,${1}/" &
  if [ "${1}" = "access" ]; then
    echo "${line}" | sed 's/\(HTTP\/1..\) \([0-9][0-9][0-9]\)/\1 \x1b[36m\2\x1b[0m/'
  else
    echo "${line}"
  fi
done
