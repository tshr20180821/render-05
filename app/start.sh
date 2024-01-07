#!/bin/bash

set -x

send_slack_message() {
  curl -sS -X POST -H "Authorization: Bearer ${SLACK_TOKEN}" \
   -d "text=${1}" -d "channel=${SLACK_CHANNEL_01}" https://slack.com/api/chat.postMessage >/dev/null
  sleep 1s
  curl -sS -X POST -H "Authorization: Bearer ${SLACK_TOKEN}" \
   -d "text=${1}" -d "channel=${SLACK_CHANNEL_02}" https://slack.com/api/chat.postMessage >/dev/null
  sleep 1s
}

apt_result2cache() {
  apt-get -qq update
  curl -X POST -sS -H "Authorization: Bearer ${UPSTASH_REDIS_REST_TOKEN}" \
   -d "$(echo -n '["SET", "__KEY__", "__VALUE__", "EX", "86400"]' | \
    sed "s/__KEY__/APT_RESULT_${RENDER_EXTERNAL_HOSTNAME}/" | \
    sed "s/__VALUE__/$(date +'%Y-%m-%d %H:%M') $(apt-get -s upgrade | grep installed)/")" \
   "${UPSTASH_REDIS_REST_URL}"
  apt-get -s upgrade >/var/www/html/auth/apt_dry_run_result.txt
}

dpkg -l

cat /proc/version
cat /etc/os-release
echo 'Processor Count : ' "$(grep -c -e processor /proc/cpuinfo)"
head -n $(($(< /proc/cpuinfo wc -l) / $(grep -c -e processor /proc/cpuinfo))) /proc/cpuinfo
hostname -A
whoami
# free -h
df -h
ulimit -n
java --version
apachectl -V
apachectl -M

# npm audit
npm list --depth=0

tmp1=$(head -n 1 ./Dockerfile)
export DOCKER_HUB_PHP_TAG=${tmp1:9}
rm ./Dockerfile

# ls -lang /etc/apache2/mods-enabled/
# cat /etc/apache2/mods-enabled/mpm_prefork.conf

export HOST_VERSION=$(cat /proc/version)
export GUEST_VERSION=$(grep "PRETTY_NAME" /etc/os-release | cut -c 13- | tr -d '"')
export PROCESSOR_NAME=$(grep "model name" /proc/cpuinfo | head -n 1 | cut -c 14-)
export APACHE_VERSION=$(apachectl -V | head -n 1)
export PHP_VERSION=$(php --version | head -n 1)
export NODE_VERSION=$(node --version)
export JAVA_VERSION=$(java --version | head -n 1)
export MEMCACHED_VERSION=$(memcached -h | head -n 1)

# log.php LogOperation.jar
export SQLITE_LOG_DB_FILE="/tmp/sqlitelog.db"

# phpMyAdmin
export BLOWFISH_SECRET=$(tr -dc 'a-zA-Z0-9' </dev/urandom | fold -w 32 | head -n 1)

# LogOperation.jar
export FIXED_THREAD_POOL=1

export DEPLOY_DATETIME=$(date +'%Y%m%d%H%M%S')

# memcached sasl
export MEMCACHED_SERVER=127.0.0.1
export MEMCACHED_PORT=11211
export MEMCACHED_USER=memcached
useradd ${MEMCACHED_USER} -G sasl
export SASL_PASSWORD=$(tr -dc 'a-zA-Z0-9' </dev/urandom | fold -w 64 | head -n 1)
echo "${SASL_PASSWORD}" | saslpasswd2 -p -a memcached -c "${MEMCACHED_USER}"
chown "${MEMCACHED_USER}":memcached /etc/sasldb2
sasldblistusers2
export SASL_CONF_PATH=/tmp/memcached.conf
echo "mech_list: plain" >"${SASL_CONF_PATH}"
# /usr/sbin/saslauthd -a sasldb -n 2 -V 2>&1 |/usr/src/app/log_general.sh saslauthd &
memcached --enable-sasl -v -l "${MEMCACHED_SERVER}" -P "${MEMCACHED_PORT}" -B binary -m 32 -t 3 -d -u "${MEMCACHED_USER}" -P /tmp/11211.tmp 2>&1 |/usr/src/app/log_general.sh memcached &
# testsaslauthd -u ${MEMCACHED_USER} -p ${SASL_PASSWORD}

# memjs
export MEMCACHIER_SERVERS="${MEMCACHED_SERVER}":"${MEMCACHED_PORT}"
export MEMCACHIER_USERNAME="${MEMCACHED_USER}"
export MEMCACHIER_PASSWORD="${SASL_PASSWORD}"

pushd /var/www/html/auth || exit
find . -maxdepth 1 -name "*.php" -type f -printf "%f\0" | xargs --max-procs=1 --max-args=1 --null -t php -l | tee -a /tmp/php_error.txt
popd || exit
php -l log.php | tee -a /tmp/php_error.txt

count1=$(grep -c 'No syntax errors detected in' /tmp/php_error.txt)
count2=$(< /tmp/php_error.txt wc -l)
rm /tmp/php_error.txt

if [ "${count1}" -lt "${count2}" ]; then
  send_slack_message 'PHP_SYNTAX_ERROR'
  exit 1
fi

/usr/src/app/node_modules/.bin/eslint crond.js
/usr/src/app/node_modules/.bin/eslint MyUtils.js

ls -lang /var/www/html/

sed -i s/__RENDER_EXTERNAL_HOSTNAME__/"${RENDER_EXTERNAL_HOSTNAME}"/g /etc/apache2/sites-enabled/apache.conf
sed -i s/__DEPLOY_DATETIME__/"${DEPLOY_DATETIME}"/ /etc/apache2/sites-enabled/apache.conf

# version
{ \
  echo "${RENDER_EXTERNAL_HOSTNAME} START ${DEPLOY_DATETIME}"; \
  echo "Host : ${HOST_VERSION}"; \
  echo "Guest : ${GUEST_VERSION}"; \
  echo "Processor : ${PROCESSOR_NAME}"; \
  echo "Apache : ${APACHE_VERSION}"; \
  echo "PHP : ${PHP_VERSION}"; \
  echo "Node.js : ${NODE_VERSION}"; \
  echo "Java : ${JAVA_VERSION}"; \
  echo "Memcached : ${MEMCACHED_VERSION}"; \
} >VERSION.txt

VERSION=$(cat VERSION.txt)
rm VERSION.txt
# send_slack_message "${VERSION}" &

# apache start
htpasswd -c -b /var/www/html/.htpasswd "${BASIC_USER}" "${BASIC_PASSWORD}"
chmod 644 /var/www/html/.htpasswd
. /etc/apache2/envvars >/dev/null 2>&1
exec /usr/sbin/apache2 -DFOREGROUND &

# php opcache cache
sleep 5s && curl -sS -u "${BASIC_USER}":"${BASIC_PASSWORD}" http://127.0.0.1/auth/preload.php &

BACKPORTS_RESULT=/var/www/html/auth/backports_results.txt
touch ${BACKPORTS_RESULT}
chmod 644 ${BACKPORTS_RESULT}

# apt upgrade info cached
# sleep 3m \
#  && apt_result2cache \
#  && dpkg -l | tail -n +6 | awk '{print $2}' | awk -F: '{print $1}' | xargs -I {} ./check_backports.sh {} ${BACKPORTS_RESULT} &

# apt upgrade info cached
# while true; do \
#   for i in {1..144}; do \
#     for j in {1..10}; do sleep 60s && echo "${i} ${j}"; done \
#      && ss -anpt \
#      && ps aux \
#      && curl -sS -A "health check" -u "${BASIC_USER}":"${BASIC_PASSWORD}" https://"${RENDER_EXTERNAL_HOSTNAME}"/; \
#   done \
#    && apt_result2cache; \
# done &

# for npm check delay
export START_TIME=$(date +%s%3N)

node crond.js
