FROM alpine:3.20

ENV LANG=ru_RU.UTF-8 \
LANGUAGE=ru_RU.UTF-8

ADD config /config
ADD entrypoint.sh /entrypoint.sh

RUN apk add --update --no-cache supervisor tzdata nginx php82-fpm php82-zip nginx-mod-http-fancyindex apache2-utils zip \
&& cp /usr/share/zoneinfo/Europe/Moscow /etc/localtime \
&& echo "Europe/Moscow" > /etc/timezone \
&& apk del tzdata \
&& mkdir -p /etc/supervisor.d \
&& chmod 755 /entrypoint.sh \
&& mkdir -p /etc/default \
&& mv /etc/nginx /etc/default/nginx \
&& mv /etc/php82 /etc/default/php82 \
&& mkdir -p /etc/default/nginx/default.d \
&& mkdir -p /etc/default/nginx/conf.d \
&& mkdir -p /run/nginx \
&& mkdir -p /run/php-fpm \
&& cp /config/supervisor/nginx.ini /etc/supervisor.d/nginx.ini \
&& cp /config/supervisor/php-fpm.ini /etc/supervisor.d/php-fpm.ini \
&& cp /config/nginx/nginx.conf /etc/default/nginx/nginx.conf \
&& cp /config/nginx/php-fpm.conf /etc/default/nginx/conf.d/php-fpm.conf \
&& cp /config/nginx/php.conf /etc/default/nginx/default.d/php.conf \
&& cp /config/php/php.ini /etc/default/php82/php.ini \
&& cp /config/php/www.conf /etc/default/php82/php-fpm.d/www.conf

ADD fancyindex /etc/default/nginx/fancyindex
# RUN cp -r /config/fileserv/modules /etc/default/nginx/ \
RUN mkdir -p /etc/default/nginx/vhosts \
&& cp -r /config/vhosts/* /etc/default/nginx/vhosts/

VOLUME ["/srv", "/etc/nginx", "/etc/php82"]
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisord.conf"]
ENTRYPOINT ["sh", "/entrypoint.sh"]
