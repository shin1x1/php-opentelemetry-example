FROM shin1x1/php-dev:8.3-fpm-bookworm

COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

RUN apt-get update \
      && pecl install protobuf \
      && docker-php-ext-enable protobuf
