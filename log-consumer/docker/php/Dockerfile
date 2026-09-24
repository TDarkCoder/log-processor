FROM php:8.5-fpm-alpine

ARG DOCKER_UID=1000
ARG DOCKER_GID=1000

RUN --mount=type=cache,target=/var/cache/apk \
    apk add --no-cache \
    postgresql-dev \
    rabbitmq-c-dev \
    $PHPIZE_DEPS \
    && docker-php-ext-install -j$(nproc) pdo_pgsql pgsql \
    && pecl install amqp \
    && docker-php-ext-enable amqp \
    && apk del $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN addgroup -g ${DOCKER_GID} -S appuser \
    && adduser -u ${DOCKER_UID} -S -G appuser appuser

WORKDIR /var/www/app

COPY composer.json composer.lock* ./

RUN composer install --no-dev --classmap-authoritative --no-scripts --no-interaction

COPY . .

RUN composer dump-autoload --classmap-authoritative

RUN chown -R appuser:appuser /var/www/app

USER appuser

EXPOSE 9000
