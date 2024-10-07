FROM shin1x1/php-dev:8.3-fpm-bookworm

COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

RUN apt-get update \
      && docker-php-ext-install mysqli \
      && pecl install protobuf opentelemetry \
      && docker-php-ext-enable protobuf opentelemetry
