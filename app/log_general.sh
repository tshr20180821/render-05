#!/bin/bash

pid=$$
string_part_1="${RENDER_EXTERNAL_HOSTNAME} ${DEPLOY_DATETIME} ${pid} ${1}"
url="https://logs-01.loggly.com/inputs/${LOGGLY_TOKEN}/tag/${RENDER_EXTERNAL_HOSTNAME},${RENDER_EXTERNAL_HOSTNAME}_${DEPLOY_DATETIME},${1}/"

while read line; do
  dt=$(date '+%Y-%m-%d %H:%M:%S.%3N')
  string_part_2="${dt} ${string_part_1} ${line}"
  # curl -X POST -s -o /dev/null -H "Content-Type: text/plain" -d "${dt} ${RENDER_EXTERNAL_HOSTNAME} ${DEPLOY_DATETIME} ${pid} ${1} ${line}" \
  #   "https://logs-01.loggly.com/inputs/${LOGGLY_TOKEN}/tag/${RENDER_EXTERNAL_HOSTNAME},${RENDER_EXTERNAL_HOSTNAME}_${DEPLOY_DATETIME},${1}/" &
  curl -X POST -s -o /dev/null -H "Content-Type: text/plain" -d "${string_part_2}" "${url}" &

  echo "${string_part_2}"
done
