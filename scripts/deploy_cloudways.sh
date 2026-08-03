#!/usr/bin/env bash
set -Eeuo pipefail

project_directory="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$project_directory"

maintenance_mode_enabled=false
horizon_restart_timeout="${DEPLOY_HORIZON_RESTART_TIMEOUT:-20}"
frontend_artifact_branch="production-assets"
frontend_artifact_wait_timeout="${DEPLOY_FRONTEND_ARTIFACT_WAIT_TIMEOUT:-900}"
frontend_artifact_poll_interval=10
frontend_artifact_commit=""
frontend_artifact_directory=""
frontend_backup_directory=""

if [[ ! "$horizon_restart_timeout" =~ ^[1-9][0-9]*$ ]]; then
    echo "DEPLOY_HORIZON_RESTART_TIMEOUT must be a positive number of seconds." >&2
    exit 1
fi

if [[ ! "$frontend_artifact_wait_timeout" =~ ^[1-9][0-9]*$ ]]; then
    echo "DEPLOY_FRONTEND_ARTIFACT_WAIT_TIMEOUT must be a positive number of seconds." >&2
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
    echo "Fetching the frontend artifact for this commit..."

    local artifact_reference="refs/remotes/origin/${frontend_artifact_branch}"
    local artifact_source_commit
    local application_source_commit
    local elapsed_seconds=0
    local remaining_seconds
    local sleep_seconds

    application_source_commit="$(git rev-parse HEAD)"

    while true; do
        if ! GIT_TERMINAL_PROMPT=0 git fetch --quiet origin "+refs/heads/${frontend_artifact_branch}:refs/remotes/origin/${frontend_artifact_branch}"; then
            echo "The frontend artifact could not be fetched. Confirm that CI published it and that this server can read the repository." >&2

            return 1
        fi

        frontend_artifact_commit="$(git rev-parse --verify "${artifact_reference}^{commit}")"
        artifact_source_commit="$(git show "${frontend_artifact_commit}:.source-commit" | tr -d '\r\n')"

        if [ "$artifact_source_commit" = "$application_source_commit" ]; then
            break
        fi

        if [ "$elapsed_seconds" -ge "$frontend_artifact_wait_timeout" ]; then
            echo "The frontend artifact still belongs to ${artifact_source_commit}, but the application is at ${application_source_commit}." >&2
            echo "GitHub Actions did not publish the matching artifact within ${frontend_artifact_wait_timeout} seconds." >&2

            return 1
        fi

        if [ "$elapsed_seconds" -eq 0 ]; then
            echo "Waiting up to ${frontend_artifact_wait_timeout} seconds for GitHub Actions to publish the matching artifact..."
        fi

        remaining_seconds=$((frontend_artifact_wait_timeout - elapsed_seconds))
        sleep_seconds="$frontend_artifact_poll_interval"

        if [ "$remaining_seconds" -lt "$sleep_seconds" ]; then
            sleep_seconds="$remaining_seconds"
        fi

        sleep "$sleep_seconds"
        elapsed_seconds=$((elapsed_seconds + sleep_seconds))
    done

    frontend_artifact_directory="$(mktemp -d "${project_directory}/public/.schooltool-build.XXXXXX")"
    git archive "$frontend_artifact_commit" public/build | tar -x --strip-components=2 -C "$frontend_artifact_directory"

    if [ ! -f "$frontend_artifact_directory/manifest.json" ]; then
        echo "The frontend artifact does not contain public/build/manifest.json." >&2

        return 1
    fi

    echo "Frontend artifact verified for ${application_source_commit}."
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

command -v git >/dev/null 2>&1 || {
    echo "git was not found on PATH."
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
