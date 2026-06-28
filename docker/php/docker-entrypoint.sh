#!/bin/sh
set -eu

mkdir -p var/cache var/log var/share var/backups/database var/uploads/courriers
chown -R www-data:www-data var

if [ "${APP_ENV:-prod}" = "prod" ]; then
    php bin/console assets:install public --env=prod --no-debug --no-interaction
    php bin/console cache:clear --env=prod --no-debug --no-interaction
fi

exec "$@"
