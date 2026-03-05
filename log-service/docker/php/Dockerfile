FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    bash \
    rabbitmq-c-dev \
    $PHPIZE_DEPS \
    && pecl install amqp \
    && docker-php-ext-enable amqp opcache \
    && apk del $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/app

COPY composer.json composer.lock* ./

RUN composer install --no-dev --classmap-authoritative --no-scripts --no-interaction

COPY . .

RUN composer dump-autoload --classmap-authoritative

EXPOSE 9000
