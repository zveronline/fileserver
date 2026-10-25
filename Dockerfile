FROM nginx:1.30.6-alpine

ENV LANG=ru_RU.UTF-8 \
LANGUAGE=ru_RU.UTF-8

ADD config /config
ADD srv /srv/fileserv
ADD entrypoint.sh /entrypoint.sh

RUN apk add --update --no-cache nginx-mod-http-fancyindex \
&& cp /config/fileserv.conf /etc/nginx/conf.d/

ADD fancyindex /etc/nginx/fancyindex

ENTRYPOINT ["sh", "/entrypoint.sh"]
