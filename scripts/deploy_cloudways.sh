#!/usr/bin/env bash
set -Eeuo pipefail

project_directory="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$project_directory"

maintenance_mode_enabled=false
horizon_restart_timeout="${DEPLOY_HORIZON_RESTART_TIMEOUT:-20}"

if [[ ! "$horizon_restart_timeout" =~ ^[1-9][0-9]*$ ]]; then
    echo "DEPLOY_HORIZON_RESTART_TIMEOUT must be a positive number of seconds." >&2
    exit 1
fi

verify_queue_runtime() {
    echo "Verifying the Horizon queue runtime..."

    if php artisan queue:health-check; then
        return
    fi

    if ensure_queue_runtime; then
        return
    fi

    echo "Horizon is not serving the configured queues. Aborting before maintenance mode." >&2

    return 1
}

start_horizon_directly() {
    echo "The process monitor did not restart Horizon. Starting Horizon directly..."

    nohup php artisan horizon >> storage/logs/horizon.log 2>&1 </dev/null &
    local horizon_process_id=$!

    for ((attempt = 1; attempt <= horizon_restart_timeout; attempt++)); do
        if php artisan queue:health-check >/dev/null 2>&1; then
            echo "Horizon started successfully with process ${horizon_process_id}."

            return
        fi

        if ! kill -0 "$horizon_process_id" 2>/dev/null; then
            break
        fi

        sleep 1
    done

    php artisan queue:health-check || true
    echo "Horizon could not be started. Check storage/logs/horizon.log." >&2

    return 1
}

wait_for_queue_runtime() {
    echo "Waiting up to ${horizon_restart_timeout} seconds for the process monitor to restart Horizon..."

    for ((attempt = 1; attempt <= horizon_restart_timeout; attempt++)); do
        if php artisan queue:health-check >/dev/null 2>&1; then
            echo "Horizon restarted successfully."

            return
        fi

        sleep 1
    done

    php artisan queue:health-check || true
    echo "Horizon did not restart within ${horizon_restart_timeout} seconds." >&2

    return 1
}

ensure_queue_runtime() {
    if wait_for_queue_runtime; then
        return
    fi

    start_horizon_directly
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

command -v nohup >/dev/null 2>&1 || {
    echo "nohup was not found on PATH."
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
php artisan optimize
php artisan up

maintenance_mode_enabled=false
ensure_queue_runtime
trap - EXIT

echo "Cloudways deployment completed successfully."
