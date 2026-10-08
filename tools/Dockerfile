# Development-only image for running the test suite locally; not shipped.
# Pick the PHP version with the PHP_VERSION build arg (see docker-compose.yml).
ARG PHP_VERSION=8.2.34
FROM php:${PHP_VERSION}-cli
RUN apt-get update && apt-get install -y --no-install-recommends git unzip && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2.8 /usr/bin/composer /usr/local/bin/composer
RUN pecl install pcov && docker-php-ext-enable pcov
ENV COMPOSER_ALLOW_SUPERUSER=1
WORKDIR /app
