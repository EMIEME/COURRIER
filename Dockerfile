FROM php:8.4-apache

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    COMPOSER_ALLOW_SUPERUSER=1

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        default-mysql-client \
        git \
        libicu-dev \
        libzip-dev \
        unzip; \
    docker-php-ext-install intl opcache pdo_mysql zip; \
    a2enmod headers rewrite; \
    rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php/prod.ini /usr/local/etc/php/conf.d/zz-prod.ini
COPY docker/php/docker-entrypoint.sh /usr/local/bin/docker-entrypoint

WORKDIR /var/www/html

COPY . .

RUN set -eux; \
    composer install --no-dev --prefer-dist --no-progress --no-interaction --no-scripts --optimize-autoloader; \
    mkdir -p var/cache var/log var/share var/backups/database var/uploads/courriers; \
    chown -R www-data:www-data var; \
    chmod +x /usr/local/bin/docker-entrypoint

ENTRYPOINT ["docker-entrypoint"]
CMD ["apache2-foreground"]
