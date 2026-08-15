#!/bin/sh
set -eu

render_port="${PORT:-10000}"

# Apache must listen on the port assigned dynamically by Render.
sed -ri "s/^Listen .*/Listen ${render_port}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${render_port}>/" /etc/apache2/sites-available/000-default.conf

php artisan config:cache
php artisan route:cache
php artisan migrate --force

exec "$@"
