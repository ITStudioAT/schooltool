#!/usr/bin/env bash
set -Eeuo pipefail

project_directory="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$project_directory"

maintenance_mode_enabled=false

verify_queue_runtime() {
    echo "Verifying the Horizon queue runtime..."

    if php artisan queue:health-check; then
        return
    fi

    echo "Horizon is not serving the configured queues. Aborting before maintenance mode." >&2

    return 1
}

wait_for_queue_runtime() {
    echo "Waiting for the process monitor to restart Horizon..."

    for ((attempt = 1; attempt <= 20; attempt++)); do
        if php artisan queue:health-check >/dev/null 2>&1; then
            echo "Horizon restarted successfully."

            return
        fi

        sleep 1
    done

    php artisan queue:health-check || true
    echo "Horizon did not restart within 20 seconds." >&2

    return 1
}

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

verify_queue_runtime

php artisan down --retry=60
maintenance_mode_enabled=true

composer install \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader \
    --no-interaction

php artisan app:update --no-interaction
wait_for_queue_runtime
php artisan optimize
php artisan up

maintenance_mode_enabled=false
trap - EXIT

echo "Cloudways deployment completed successfully."
