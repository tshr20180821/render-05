#!/bin/bash

set -x

node --version
php --version
apachectl -V
apachectl -l
cat /proc/version
cat /etc/os-release
strings /etc/localtime
echo 'Processor Count : ' $(grep -c -e processor /proc/cpuinfo)
head -n $(($(< /proc/cpuinfo wc -l) / $(grep -c -e processor /proc/cpuinfo))) /proc/cpuinfo
hostname -A
whoami
free -h
df -h
ulimit -n
# dpkg -l

# npm audit
npm list --depth=0

php -l /var/www/html/auth/crond.php
node -c crond.js
node -c start.js

ls -lang /var/www/html/

# phpMyAdmin
export BLOWFISH_SECRET=$(cat /dev/urandom | tr -dc 'a-zA-Z0-9' | fold -w 32 | head -n 1)

export DEPLOY_DATETIME=$(date +'%Y%m%d%H%M%S')
sed -i s/__DEPLOY_DATETIME__/${DEPLOY_DATETIME}/ /etc/apache2/sites-enabled/apache.conf

cat /etc/apache2/sites-enabled/apache.conf

echo "Apache\n" >/tmp/VERSION.txt
apachectl -V >>/tmp/VERSION.txt
echo -e "PHP\n" >>/tmp/VERSION.txt
php --version >>/tmp/VERSION.txt
echo "Node.js\n" >>/tmp/VERSION.txt
node --version >>/tmp/VERSION.txt

VERSION=$(/tmp/VERSION.txt)

curl -sS -X POST -H "Authorization: Bearer ${SLACK_TOKEN}" \
  -d "text=${VERSION}" -d "channel=${SLACK_CHANNEL_01}" https://slack.com/api/chat.postMessage >/dev/null \
&& sleep 1s \
&& curl -sS -X POST -H "Authorization: Bearer ${SLACK_TOKEN}" \
  -d "text=${VERSION}" -d "channel=${SLACK_CHANNEL_02}" https://slack.com/api/chat.postMessage >/dev/null \
&& sleep 1s

node start.js &

echo ServerName ${RENDER_EXTERNAL_HOSTNAME} >/etc/apache2/sites-enabled/server_name.conf

. /etc/apache2/envvars && exec /usr/sbin/apache2 -DFOREGROUND &

# sleep 3s && ps aux && apt-get update && apt-get -s upgrade &
sleep 3s && ps aux &

# forever start -c ”node --expose-gc" crond.js
node --expose-gc crond.js
