# ampf's development container: PHP 8.5 with the extensions composer.json requires, pcov for the tests' coverage,
# and Composer. The scripts next to this file run every command in a throwaway container of this image.
FROM php:8.5-cli-alpine

COPY --from=ghcr.io/mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini" \
    && install-php-extensions pdo_mysql pcov \
    && printf 'memory_limit = 1G\n' > "$PHP_INI_DIR/conf.d/ampf.ini"
