#!/bin/sh
set -e

if [ ! -f /etc/nginx/nginx.conf ]
then
  mkdir -p /etc/nginx/vhosts
  mkdir -p /etc/nginx/default.d
fi

mkdir -p /etc/php82
cp -r /etc/default/php82/* /etc/php82/
chown -R nginx:nginx /etc/php82

rm -rf /etc/nginx/conf.d
rm -rf /etc/nginx/modules
cp -r /etc/default/nginx/* /etc/nginx/
rm -f /etc/nginx/conf.d/default.conf
chown -R nginx:nginx /etc/nginx

mkdir -p /srv/fileserv
cp -r /etc/default/nginx/fancyindex/* /srv/fileserv/.nginxy
mkdir -p /tmp/fileserv
chown -R nginx:nginx /tmp/fileserv

exec "$@"
