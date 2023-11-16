#!/bin/bash

set -x

ls -lang /etc/apache2/mods-enabled/
cat /etc/apache2/mods-enabled/mpm_prefork.conf

# memcached sasl
useradd memcached -G sasl
export SASL_PASSWORD=$(cat /dev/urandom | tr -dc 'a-zA-Z0-9' | fold -w 64 | head -n 1)
echo ${SASL_PASSWORD} | saslpasswd2 -p -a memcached -c memcached
chown memcached:memcached /etc/sasldb2
# sasldblistusers2
export SASL_CONF_PATH=/tmp/memcached.conf
echo "mech_list: plain cram-md5" >${SASL_CONF_PATH}
/usr/sbin/saslauthd -a sasldb -n 2 -V
/usr/bin/memcached -S -v -B binary -d -u memcached
testsaslauthd -u memcached -p ${SASL_PASSWORD}

# memjs
export MEMCACHIER_SERVERS=127.0.0.1:11211
export MEMCACHIER_USERNAME=memcached
export MEMCACHIER_PASSWORD=${SASL_PASSWORD}

echo ${MEMCACHIER_SERVERS}

# phpMyAdmin
export BLOWFISH_SECRET=$(cat /dev/urandom | tr -dc 'a-zA-Z0-9' | fold -w 32 | head -n 1)

export FIXED_THREAD_POOL=1
export DEPLOY_DATETIME=$(date +'%Y%m%d%H%M%S')
sed -i s/__RENDER_EXTERNAL_HOSTNAME__/${RENDER_EXTERNAL_HOSTNAME}/ /etc/apache2/sites-enabled/apache.conf
sed -i s/__DEPLOY_DATETIME__/${DEPLOY_DATETIME}/ /etc/apache2/sites-enabled/apache.conf

echo ServerName ${RENDER_EXTERNAL_HOSTNAME} >/etc/apache2/sites-enabled/server_name.conf

exec /usr/sbin/apache2 -DFOREGROUND &

sleep 3s && ps aux &

node --expose-gc crond.js
