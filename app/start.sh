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

# npm audit
npm list --depth=0

tmp1=$(cat ./Dockerfile | head -n 1)
export DOCKER_HUB_PHP_TAG=${tmp1:9}
rm ./Dockerfile

# ls -lang /etc/apache2/mods-enabled/
# cat /etc/apache2/mods-enabled/mpm_prefork.conf

export HOST_VERSION=$(cat /proc/version)
export GUEST_VERSION=$(cat /etc/os-release | grep "PRETTY_NAME" | cut -c 13- | tr -d '"')
export PROCESSOR_NAME=$(cat /proc/cpuinfo | grep "model name" | head -n 1 | cut -c 14-)
export APACHE_VERSION=$(apachectl -V | head -n 1)
export PHP_VERSION=$(php --version | head -n 1)
export NODE_VERSION=$(node --version)
export JAVA_VERSION=$(java --version | head -n 1)
export MEMCACHED_VERSION=$(memcached -h | head -n 1)

# log.php LogOperation.jar
export SQLITE_LOG_DB_FILE="/tmp/sqlitelog.db"

# phpMyAdmin
export BLOWFISH_SECRET=$(cat /dev/urandom | tr -dc 'a-zA-Z0-9' | fold -w 32 | head -n 1)

# LogOperation.jar
export FIXED_THREAD_POOL=1

export DEPLOY_DATETIME=$(date +'%Y%m%d%H%M%S')

# memcached sasl
export MEMCACHED_SERVER=127.0.0.1
export MEMCACHED_PORT=11211
export MEMCACHED_USER=memcached
useradd ${MEMCACHED_USER} -G sasl
export SASL_PASSWORD=$(cat /dev/urandom | tr -dc 'a-zA-Z0-9' | fold -w 64 | head -n 1)
echo ${SASL_PASSWORD} | saslpasswd2 -p -a memcached -c ${MEMCACHED_USER}
chown ${MEMCACHED_USER}:memcached /etc/sasldb2
sasldblistusers2
export SASL_CONF_PATH=/tmp/memcached.conf
echo "mech_list: plain" >${SASL_CONF_PATH}
# /usr/sbin/saslauthd -a sasldb -n 2 -V 2>&1 |/usr/src/app/log_general.sh saslauthd &
memcached --enable-sasl -v -l ${MEMCACHED_SERVER} -P ${MEMCACHED_PORT} -B binary -m 32 -t 3 -d -u ${MEMCACHED_USER} 2>&1 |/usr/src/app/log_general.sh memcached &
# testsaslauthd -u ${MEMCACHED_USER} -p ${SASL_PASSWORD}

# memjs
export MEMCACHIER_SERVERS=${MEMCACHED_SERVER}:${MEMCACHED_PORT}
export MEMCACHIER_USERNAME=${MEMCACHED_USER}
export MEMCACHIER_PASSWORD=${SASL_PASSWORD}

dragonfly --bind=127.0.0.1 --requirepass=${SASL_PASSWORD} --version_check=false --memcached_port=11212 --tcp_keepalive=120 --port 6379 --colorlogtostderr &

pushd /var/www/html/auth
find . -maxdepth 1 -name "*.php" -type f -printf "%f\0" | xargs --max-procs=1 --max-args=1 --null -t php -l | tee -a /tmp/php_error.txt
popd
php -l log.php | tee -a /tmp/php_error.txt

count1=$(grep -c 'No syntax errors detected in' /tmp/php_error.txt)
count2=$(< /tmp/php_error.txt wc -l)
rm /tmp/php_error.txt

if [ ${count1} -lt ${count2} ]; then
  curl -sS -X POST -H "Authorization: Bearer ${SLACK_TOKEN}" \
   -d "text=PHP_SYNTAX_ERROR" -d "channel=${SLACK_CHANNEL_01}" https://slack.com/api/chat.postMessage >/dev/null \
    && sleep 1s \
    && curl -sS -X POST -H "Authorization: Bearer ${SLACK_TOKEN}" \
        -d "text=PHP_SYNTAX_ERROR" -d "channel=${SLACK_CHANNEL_02}" https://slack.com/api/chat.postMessage >/dev/null \
    && sleep 1s
fi

/usr/src/app/node_modules/.bin/eslint crond.js
/usr/src/app/node_modules/.bin/eslint MyUtils.js

ls -lang /var/www/html/

sed -i s/__RENDER_EXTERNAL_HOSTNAME__/${RENDER_EXTERNAL_HOSTNAME}/g /etc/apache2/sites-enabled/apache.conf
sed -i s/__DEPLOY_DATETIME__/${DEPLOY_DATETIME}/ /etc/apache2/sites-enabled/apache.conf

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

curl -sS -X POST -H "Authorization: Bearer ${SLACK_TOKEN}" \
 -d "text=${VERSION}" -d "channel=${SLACK_CHANNEL_01}" https://slack.com/api/chat.postMessage >/dev/null \
  && sleep 1s \
  && curl -sS -X POST -H "Authorization: Bearer ${SLACK_TOKEN}" \
      -d "text=${VERSION}" -d "channel=${SLACK_CHANNEL_02}" https://slack.com/api/chat.postMessage >/dev/null &
. /etc/apache2/envvars >/dev/null 2>&1
exec /usr/sbin/apache2 -DFOREGROUND &

sleep 5s && curl -sS -u ${BASIC_USER}:${BASIC_PASSWORD} http://127.0.0.1/auth/preload.php &

# while true; do sleep 840s && ps aux && curl -sS -A "health check" -u ${BASIC_USER}:${BASIC_PASSWORD} https://${RENDER_EXTERNAL_HOSTNAME}/; done &
while true; \
  do for i in {1..16}; do sleep 60s && echo ${i}; done \
  && ss -anpt \
  && ps aux \
  && curl -sS -A "health check" -u ${BASIC_USER}:${BASIC_PASSWORD} https://${RENDER_EXTERNAL_HOSTNAME}/; \
done &

export START_TIME=$(date +%s%3N)

# find / -size +50M | xargs ls -l | sort -rn &

# forever start -c ”node --expose-gc" crond.js
node crond.js
