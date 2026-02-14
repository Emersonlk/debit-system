#!/bin/sh
set -e
if [ -f "artisan" ]; then
    php artisan migrate --force --no-interaction 2>/dev/null || true
fi

exec "$@"
