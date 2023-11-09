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
# dpkg -l

# npm audit
npm list --depth=0

/usr/bin/memcached --help
/usr/bin/memcached -vvv -d -u memcache

php -l /var/www/html/auth/crond.php
php -l /var/www/html/auth/health_check.php
php -l /var/www/html/auth/log.php
php -l /var/www/html/auth/update_sqlite.php
node -c crond.js
# npm init @eslint/config
# /usr/src/app/node_modules/.bin/eslint --init
# /usr/src/app/node_modules/.bin/eslint /usr/src/app/MyUtils.js
# /usr/src/app/node_modules/.bin/eslint /usr/src/app/crond.js
# find / -name eslint -print

ls -lang /var/www/html/
ls -lang /usr/local/lib/php/extensions/no-debug-non-zts-20220829/

# phpMyAdmin
export BLOWFISH_SECRET=$(cat /dev/urandom | tr -dc 'a-zA-Z0-9' | fold -w 32 | head -n 1)

export FIXED_THREAD_POOL=1
export DEPLOY_DATETIME=$(date +'%Y%m%d%H%M%S')
sed -i s/__RENDER_EXTERNAL_HOSTNAME__/${RENDER_EXTERNAL_HOSTNAME}/ /etc/apache2/sites-enabled/apache.conf
sed -i s/__DEPLOY_DATETIME__/${DEPLOY_DATETIME}/ /etc/apache2/sites-enabled/apache.conf

echo ServerName ${RENDER_EXTERNAL_HOSTNAME} >/etc/apache2/sites-enabled/server_name.conf

# cat /etc/apache2/sites-enabled/apache.conf

echo "${RENDER_EXTERNAL_HOSTNAME} START ${DEPLOY_DATETIME}" >VERSION.txt
echo "Apache" >>VERSION.txt
apachectl -V | head -n 1 >>VERSION.txt
echo -e "PHP" >>VERSION.txt
php --version | head -n 1 >>VERSION.txt
echo "Node.js" >>VERSION.txt
node --version >>VERSION.txt

VERSION=$(cat VERSION.txt)
rm VERSION.txt

curl -sS -X POST -H "Authorization: Bearer ${SLACK_TOKEN}" \
  -d "text=${VERSION}" -d "channel=${SLACK_CHANNEL_01}" https://slack.com/api/chat.postMessage >/dev/null \
 && sleep 1s \
 && curl -sS -X POST -H "Authorization: Bearer ${SLACK_TOKEN}" \
  -d "text=${VERSION}" -d "channel=${SLACK_CHANNEL_02}" https://slack.com/api/chat.postMessage >/dev/null &
. /etc/apache2/envvars >/dev/null 2>&1
exec /usr/sbin/apache2 -DFOREGROUND &

sleep 3s && ps aux &

# find / -size +50M | xargs ls -l | sort -rn &

# forever start -c ”node --expose-gc" crond.js
node --expose-gc crond.js
