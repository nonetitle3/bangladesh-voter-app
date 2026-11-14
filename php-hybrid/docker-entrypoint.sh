#!/bin/sh
set -eu

PORT_VALUE="${PORT:-10000}"
case "$PORT_VALUE" in
  *[!0-9]*|'') echo "PORT must be a numeric TCP port" >&2; exit 1 ;;
esac

# Apache must listen on Render's assigned port, not the image's local default.
sed -i "s/^Listen .*/Listen ${PORT_VALUE}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:.*>/<VirtualHost *:${PORT_VALUE}>/" /etc/apache2/sites-available/000-default.conf

exec "$@"
