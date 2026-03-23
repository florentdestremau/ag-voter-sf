#!/bin/sh
set -e

mkdir -p /storage/sessions

php bin/console doctrine:migrations:migrate --no-interaction

exec "$@"
