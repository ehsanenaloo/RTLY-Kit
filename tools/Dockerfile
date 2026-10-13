# Development-only image for running the test suite locally; not shipped.
# Pick the PHP version with the PHP_VERSION build arg (see docker-compose.yml).
ARG PHP_VERSION=8.2.34
FROM php:${PHP_VERSION}-cli
RUN apt-get update && apt-get install -y --no-install-recommends git unzip && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2.8 /usr/bin/composer /usr/local/bin/composer
# Optional MySQL and PostgreSQL drivers for the database tests: build with WITH_DB=1 (service php-db).
ARG WITH_DB=0
RUN if [ "$WITH_DB" = "1" ]; then \
      apt-get update && apt-get install -y --no-install-recommends libpq-dev \
      && docker-php-ext-install pdo_mysql pdo_pgsql \
      && rm -rf /var/lib/apt/lists/*; \
    fi
RUN pecl install pcov && docker-php-ext-enable pcov
ENV COMPOSER_ALLOW_SUPERUSER=1
WORKDIR /app
