#!/bin/sh
set -e

mkdir -p /srv/fileserv
mkdir -p /srv/fileserv/.nginxy
cp -r /etc/nginx/fancyindex/* /srv/fileserv/.nginxy
mkdir -p /tmp/fileserv
mkdir -p /tmp/fileserv/.archived_dirs
chown -R nginx:nginx /tmp/fileserv

exec "$@"
