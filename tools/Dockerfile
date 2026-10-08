# Development-only image for running the test suite locally; not shipped.
FROM php:8.2.30-cli
RUN apt-get update && apt-get install -y --no-install-recommends git unzip && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2.8 /usr/bin/composer /usr/local/bin/composer
RUN pecl install pcov && docker-php-ext-enable pcov
ENV COMPOSER_ALLOW_SUPERUSER=1
WORKDIR /app
