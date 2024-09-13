#!/bin/bash
set -e

set_root_passwd() {
  echo "root:$ROOT_PASSWORD" | chpasswd
}

# Set root password from the commandline
set_root_passwd

if [ ! -f /etc/ssh/ssh_host_rsa_key ]
then
  ssh-keygen -A
fi

cp /config/host/sshd_config /etc/ssh/

if [ ! -f /etc/nginx/nginx.conf ]
then
  mkdir -p /etc/nginx/vhosts.a
  mkdir -p /etc/nginx/vhosts.d
  mkdir -p /etc/nginx/default.d
  mkdir -p /etc/nginx/ssl
fi
if [ ! -f /etc/nginx/ssl/dhparam.pem ]
then
  openssl dhparam -out /etc/nginx/ssl/dhparam.pem 2048
fi

mkdir -p /etc/php82
cp -r /etc/default/php82/* /etc/php82/
chown -R nginx:nginx /etc/php82

rm -rf /etc/nginx/conf.d
rm -rf /etc/nginx/modules
cp -r /etc/default/nginx/* /etc/nginx/
cp -r /etc/default/http /srv/
rm -f /etc/nginx/conf.d/default.conf
chown -R nginx:nginx /etc/nginx

mkdir -p /root/.ssh
cp -r /config/ssh/* /root/.ssh/

mkdir -p /srv/fileserv
cp -r /etc/default/nginx/fancyindex/* /srv/fileserv/.nginxy
mkdir -p /tmp/fileserv
chown -R nginx:nginx /tmp/fileserv

exec "$@"
