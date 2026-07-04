FROM php:8.3-fpm-alpine

# SSE precisa que o PHP NAO segure o output em buffer e nao tenha timeout curto.
COPY ./php/sse.ini /usr/local/etc/php/conf.d/sse.ini

WORKDIR /var/www/html
