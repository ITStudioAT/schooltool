#!/usr/bin/env bash
set -Eeuo pipefail

project_directory="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$project_directory"

maintenance_mode_enabled=false

restore_application() {
    if [ "$maintenance_mode_enabled" = false ]; then
        return
    fi

    echo "Restoring the application from maintenance mode..."
    php artisan up || true
}

trap restore_application EXIT

command -v php >/dev/null 2>&1 || {
    echo "php was not found on PATH."
    exit 1
}

command -v composer >/dev/null 2>&1 || {
    echo "composer was not found on PATH."
    exit 1
}

php artisan down --retry=60
maintenance_mode_enabled=true

composer install \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader \
    --no-interaction

php artisan app:update --no-interaction
php artisan optimize
php artisan up

maintenance_mode_enabled=false
trap - EXIT

echo "Cloudways deployment completed successfully."
