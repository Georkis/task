FROM thecodingmachine/php:8.4-v4-fpm

WORKDIR /var/www/html

COPY docker/php/startup.sh /etc/container/startup.sh
USER root
RUN chmod +x /etc/container/startup.sh
USER docker

COPY . /var/www/html

RUN mkdir -p var/cache var/log var/share config/jwt
