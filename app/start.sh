#!/bin/bash

set -x

dpkg -l

cat /proc/version
cat /etc/os-release
strings /etc/localtime
echo 'Processor Count : ' $(grep -c -e processor /proc/cpuinfo)
head -n $(($(< /proc/cpuinfo wc -l) / $(grep -c -e processor /proc/cpuinfo))) /proc/cpuinfo
hostname -A
whoami
# free -h
df -h
ulimit -n
java --version
apachectl -V
apachectl -M

tmp1=$(cat ./Dockerfile | head -n 1)
export DOCKER_HUB_PHP_TAG=${tmp1:9}

# ls -lang /etc/apache2/mods-enabled/
# cat /etc/apache2/mods-enabled/mpm_prefork.conf

export HOST_VERSION=$(cat /proc/version)
export GUEST_VERSION=$(cat /etc/os-release | grep "PRETTY_NAME" | cut -c 13- | tr -d '"')
export PROCESSOR_NAME=$(cat /proc/cpuinfo | grep "model name" | head -n 1 | cut -c 14-)
export APACHE_VERSION=$(apachectl -V | head -n 1)
export PHP_VERSION=$(php --version | head -n 1)
export NODE_VERSION=$(node --version)
export JAVA_VERSION=$(java --version | head -n 1)
export MEMCACHED_VERSION=$(./memcached -h | head -n 1)

export SQLITE_LOG_DB_FILE="/tmp/sqlitelog.db"

# phpMyAdmin
export BLOWFISH_SECRET=$(cat /dev/urandom | tr -dc 'a-zA-Z0-9' | fold -w 32 | head -n 1)

export FIXED_THREAD_POOL=1
export DEPLOY_DATETIME=$(date +'%Y%m%d%H%M%S')

# npm audit
npm list --depth=0

# memcached sasl
useradd memcached -G sasl
export MEMCACHED_SASL_PWDB=/tmp/sasl.db
export SASL_PASSWORD=$(cat /dev/urandom | tr -dc 'a-zA-Z0-9' | fold -w 64 | head -n 1)
# echo ${SASL_PASSWORD} | saslpasswd2 -p -a memcached -c memcached
# echo ${SASL_PASSWORD} | saslpasswd2 -p -a memcached -c memcached -f ${MEMCACHED_SASL_PWDB}
echo "memcached:${SASL_PASSWORD}" >${MEMCACHED_SASL_PWDB}
ls -lang ${MEMCACHED_SASL_PWDB}
cat ${MEMCACHED_SASL_PWDB}
# chown memcached:memcached /etc/sasldb2
chown memcached:memcached ${MEMCACHED_SASL_PWDB}
# sasldblistusers2
export SASL_CONF_PATH=/tmp/memcached.conf
echo "mech_list: plain" >${SASL_CONF_PATH}
# /usr/sbin/saslauthd -a sasldb -n 2 -V 2>&1 |/usr/src/app/log_general.sh saslauthd &
./memcached -l 127.0.0.1 --enable-sasl -vv -B binary -d -u memcached 2>&1 |/usr/src/app/log_general.sh memcached &
# testsaslauthd -u memcached -p ${SASL_PASSWORD}

# memjs
export MEMCACHIER_SERVERS=127.0.0.1:11211
export MEMCACHIER_USERNAME=memcached
export MEMCACHIER_PASSWORD=${SASL_PASSWORD}

php -l /var/www/html/auth/crond.php
php -l /var/www/html/auth/health_check.php
php -l /var/www/html/auth/update_sqlite.php
php -l log.php
/usr/src/app/node_modules/.bin/eslint crond.js
/usr/src/app/node_modules/.bin/eslint MyUtils.js

ls -lang /var/www/html/

sed -i s/__RENDER_EXTERNAL_HOSTNAME__/${RENDER_EXTERNAL_HOSTNAME}/g /etc/apache2/sites-enabled/apache.conf
sed -i s/__DEPLOY_DATETIME__/${DEPLOY_DATETIME}/ /etc/apache2/sites-enabled/apache.conf

echo "${RENDER_EXTERNAL_HOSTNAME} START ${DEPLOY_DATETIME}" >VERSION.txt
echo "Host : ${HOST_VERSION}" >>VERSION.txt
echo "Guest : ${GUEST_VERSION}" >>VERSION.txt
echo "Processor : ${PROCESSOR_NAME}" >>VERSION.txt
echo "Apache : ${APACHE_VERSION}" >>VERSION.txt
echo "PHP : ${PHP_VERSION}" >>VERSION.txt
echo "Node.js : ${NODE_VERSION}" >>VERSION.txt
echo "Java : ${JAVA_VERSION}" >>VERSION.txt
echo "Memcached : ${MEMCACHED_VERSION}" >>VERSION.txt

VERSION=$(cat VERSION.txt)
rm VERSION.txt

curl -sS -X POST -H "Authorization: Bearer ${SLACK_TOKEN}" \
  -d "text=${VERSION}" -d "channel=${SLACK_CHANNEL_01}" https://slack.com/api/chat.postMessage >/dev/null \
 && sleep 1s \
 && curl -sS -X POST -H "Authorization: Bearer ${SLACK_TOKEN}" \
  -d "text=${VERSION}" -d "channel=${SLACK_CHANNEL_02}" https://slack.com/api/chat.postMessage >/dev/null &
. /etc/apache2/envvars >/dev/null 2>&1
exec /usr/sbin/apache2 -DFOREGROUND &

sleep 5s && curl -sS -u ${BASIC_USER}:${BASIC_PASSWORD} http://127.0.0.1/auth/preload.php &

# for i in {1..60} ; do sleep 30s && ps aux ; done &
# while true; do sleep 840s && ps aux && curl -sS -A "health check" -u ${BASIC_USER}:${BASIC_PASSWORD} https://${RENDER_EXTERNAL_HOSTNAME}/; done &

export START_TIME=$(date +%s%3N)

# find / -size +50M | xargs ls -l | sort -rn &

# forever start -c hnode --expose-gc" crond.js
node crond.js
