#!/bin/sh
set -eu

mkdir -p var/cache var/log var/share var/backups/database public/uploads/courriers
chown -R www-data:www-data var public/uploads

if [ "${APP_ENV:-prod}" = "prod" ]; then
    php bin/console assets:install public --env=prod --no-debug --no-interaction
    php bin/console cache:clear --env=prod --no-debug --no-interaction
fi

exec "$@"
