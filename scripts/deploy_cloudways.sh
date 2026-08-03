#!/usr/bin/env bash
set -Eeuo pipefail

project_directory="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$project_directory"

maintenance_mode_enabled=false
horizon_restart_timeout="${DEPLOY_HORIZON_RESTART_TIMEOUT:-20}"
frontend_release_archive="${project_directory}/deployment/frontend-build.tar.gz"
frontend_release_marker="${project_directory}/deployment/source-commit"
frontend_artifact_directory=""
frontend_backup_directory=""

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

prepare_frontend_artifact() {
    echo "Verifying the CI-built frontend release..."

    if [ ! -f "$frontend_release_archive" ] || [ ! -f "$frontend_release_marker" ]; then
        echo "The CI-built deployment release is missing." >&2
        echo "Wait for the Publish deployment release job to succeed, then use Cloudways Pull from the main branch again." >&2

        return 1
    fi

    local release_source_commit
    local artifact_source_commit

    release_source_commit="$(tr -d '\r\n' < "$frontend_release_marker")"

    if [[ ! "$release_source_commit" =~ ^[0-9a-f]{40,64}$ ]]; then
        echo "The Cloudways release source marker is invalid." >&2

        return 1
    fi

    frontend_artifact_directory="$(mktemp -d "${project_directory}/public/.schooltool-build.XXXXXX")"

    if ! tar -xzf "$frontend_release_archive" -C "$frontend_artifact_directory"; then
        echo "The CI-built frontend archive could not be extracted." >&2

        return 1
    fi

    if [ ! -f "$frontend_artifact_directory/manifest.json" ]; then
        echo "The frontend artifact does not contain public/build/manifest.json." >&2

        return 1
    fi

    if [ ! -f "$frontend_artifact_directory/deployment-source.txt" ]; then
        echo "The frontend artifact source marker is missing." >&2

        return 1
    fi

    artifact_source_commit="$(tr -d '\r\n' < "$frontend_artifact_directory/deployment-source.txt")"

    if [ "$artifact_source_commit" != "$release_source_commit" ]; then
        echo "The frontend artifact was built for ${artifact_source_commit}, but the Cloudways release contains ${release_source_commit}." >&2

        return 1
    fi

    echo "Frontend artifact verified for ${release_source_commit}."
}

install_frontend_artifact() {
    echo "Installing the verified frontend artifact..."

    if [ -e public/build ] && [ ! -d public/build ]; then
        echo "public/build exists but is not a directory." >&2

        return 1
    fi

    if [ -d public/hot ] && [ ! -L public/hot ]; then
        echo "public/hot is a directory; refusing to remove it." >&2

        return 1
    fi

    rm -f -- public/hot

    if [ -d public/build ]; then
        frontend_backup_directory="$(mktemp -d "${project_directory}/public/.schooltool-build-backup.XXXXXX")"
        rmdir -- "$frontend_backup_directory"
        mv public/build "$frontend_backup_directory"
    fi

    if ! mv "$frontend_artifact_directory" public/build; then
        if [ -n "$frontend_backup_directory" ] && [ -d "$frontend_backup_directory" ]; then
            if mv "$frontend_backup_directory" public/build; then
                frontend_backup_directory=""
            else
                echo "The previous build remains at ${frontend_backup_directory}; restore it manually before serving the application." >&2
            fi
        fi

        echo "Could not install the frontend artifact." >&2

        return 1
    fi

    frontend_artifact_directory=""

    if [ -n "$frontend_backup_directory" ] && [ -d "$frontend_backup_directory" ]; then
        if ! rm -rf -- "$frontend_backup_directory"; then
            echo "The new frontend is installed, but the previous build could not be removed from ${frontend_backup_directory}." >&2

            return 1
        fi

        frontend_backup_directory=""
    fi

    echo "Frontend artifact installed."

    return 0
}

cleanup_frontend_artifact() {
    if [ -z "$frontend_artifact_directory" ] || [ ! -d "$frontend_artifact_directory" ]; then
        return
    fi

    case "$frontend_artifact_directory" in
        "${project_directory}"/public/.schooltool-build.*)
            rm -rf -- "$frontend_artifact_directory"
            frontend_artifact_directory=""
            ;;
        *)
            echo "Refusing to remove unexpected frontend artifact directory: ${frontend_artifact_directory}" >&2
            ;;
    esac
}

restore_application() {
    if [ "$maintenance_mode_enabled" = true ]; then
        echo "Restoring the application from maintenance mode..."
        php artisan up || true
    fi

    cleanup_frontend_artifact
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

command -v tar >/dev/null 2>&1 || {
    echo "tar was not found on PATH."
    exit 1
}

command -v mktemp >/dev/null 2>&1 || {
    echo "mktemp was not found on PATH."
    exit 1
}

command -v flock >/dev/null 2>&1 || {
    echo "flock was not found on PATH."
    exit 1
}

exec 9>storage/framework/cloudways-deploy.lock

if ! flock -n 9; then
    echo "Another Cloudways deployment is already running." >&2
    exit 1
fi

prepare_frontend_artifact
verify_queue_runtime

php artisan down --retry=60
maintenance_mode_enabled=true

composer install \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader \
    --no-interaction

install_frontend_artifact
php artisan app:update --no-interaction --skip-frontend
php artisan optimize
php artisan up

maintenance_mode_enabled=false
ensure_queue_runtime
cleanup_frontend_artifact
trap - EXIT

echo "Cloudways deployment completed successfully."
