#!/usr/bin/env bash
set -Eeuo pipefail

project_directory="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$project_directory"

prepare_only=false

if [ "${1:-}" = "--prepare" ]; then
    prepare_only=true
elif [ "$#" -gt 0 ]; then
    echo "Usage: bash scripts/deploy_cloudways.sh [--prepare]" >&2
    exit 2
fi

maintenance_mode_enabled=false
backend_update_started=false
maintenance_marker="${project_directory}/storage/framework/cloudways-deploy-maintenance"
horizon_restart_timeout="${DEPLOY_HORIZON_RESTART_TIMEOUT:-60}"
frontend_release_archive="${project_directory}/deployment/frontend-build.tar.gz"
frontend_release_archive_hash="${project_directory}/deployment/frontend-build.sha256"
frontend_release_marker="${project_directory}/deployment/source-commit"
frontend_release_manifest_path="deployment/source-manifest.sha256"
frontend_release_manifest="${project_directory}/deployment/source-manifest.sha256"
frontend_artifact_directory=""
frontend_backup_directory=""

if [[ ! "$horizon_restart_timeout" =~ ^[1-9][0-9]*$ ]]; then
    echo "DEPLOY_HORIZON_RESTART_TIMEOUT must be a positive number of seconds." >&2
    exit 1
fi

prepare_cloudways_pull() {
    if [ -f storage/framework/down ]; then
        if [ ! -f "$maintenance_marker" ]; then
            echo "The application is in maintenance mode, but not because of this deployment workflow." >&2
            echo "Resolve that state before preparing a Cloudways Pull." >&2

            return 1
        fi

        if [ "$(tr -d '\r\n' < "$maintenance_marker")" != prepared ]; then
            echo "The deployment maintenance marker is not in the prepared state." >&2
            echo "Resolve the interrupted deployment before preparing another Cloudways Pull." >&2

            return 1
        fi

        echo "Cloudways deployment maintenance mode is already active."
    else
        printf 'preparing\n' > "$maintenance_marker"

        if ! php artisan down --render="errors::503" --retry=60 --refresh=15; then
            rm -f -- "$maintenance_marker"

            return 1
        fi

        maintenance_mode_enabled=true
        printf 'prepared\n' > "$maintenance_marker"
        echo "Cloudways deployment maintenance mode enabled."
    fi

    if [ "${SCHOOLTOOL_CLOUDWAYS_TERMINAL_PULL:-false}" = true ]; then
        echo "Cloudways deployment maintenance mode prepared for the terminal pull."
    else
        echo "Now use Cloudways Pull from main, then run composer deploy."
    fi
}

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
    local excluded_process_ids="${1:-}"
    local health_check_arguments=()
    local existing_process_ids

    while IFS= read -r process_id; do
        if [[ "$process_id" =~ ^[1-9][0-9]*$ ]]; then
            health_check_arguments+=("--exclude-master-pid=${process_id}")
        fi
    done <<< "$excluded_process_ids"

    existing_process_ids="$(horizon_master_process_ids)"

    if [ -n "$existing_process_ids" ]; then
        echo "Recycling unhealthy Horizon master process(es): ${existing_process_ids//$'\n'/, }." >&2

        while IFS= read -r process_id; do
            if [[ "$process_id" =~ ^[1-9][0-9]*$ ]]; then
                health_check_arguments+=("--exclude-master-pid=${process_id}")
                kill -TERM "$process_id" 2>/dev/null || true
            fi
        done <<< "$existing_process_ids"

        if wait_for_queue_runtime "$existing_process_ids"; then
            return
        fi
    fi

    if php artisan queue:health-check "${health_check_arguments[@]}" >/dev/null 2>&1; then
        return
    fi

    echo "The process monitor did not restart Horizon. Starting Horizon directly..."

    nohup php artisan horizon 8>&- 9>&- >> storage/logs/horizon.log 2>&1 </dev/null &
    local horizon_process_id=$!

    for ((attempt = 1; attempt <= horizon_restart_timeout; attempt++)); do
        if php artisan queue:health-check "${health_check_arguments[@]}" >/dev/null 2>&1; then
            echo "Horizon started successfully with process ${horizon_process_id}."

            return
        fi

        if ! kill -0 "$horizon_process_id" 2>/dev/null; then
            break
        fi

        sleep 1
    done

    php artisan queue:health-check "${health_check_arguments[@]}" || true
    echo "Horizon could not be started. Check storage/logs/horizon.log." >&2

    return 1
}

horizon_master_process_ids() {
    pgrep -f '[a]rtisan horizon$' || true
}

wait_for_queue_runtime() {
    local excluded_process_ids="${1:-}"
    local health_check_arguments=()

    while IFS= read -r process_id; do
        if [[ "$process_id" =~ ^[1-9][0-9]*$ ]]; then
            health_check_arguments+=("--exclude-master-pid=${process_id}")
        fi
    done <<< "$excluded_process_ids"

    echo "Waiting up to ${horizon_restart_timeout} seconds for the process monitor to restart Horizon..."

    for ((attempt = 1; attempt <= horizon_restart_timeout; attempt++)); do
        if php artisan queue:health-check "${health_check_arguments[@]}" >/dev/null 2>&1; then
            echo "Horizon restarted successfully."

            return
        fi

        sleep 1
    done

    php artisan queue:health-check "${health_check_arguments[@]}" || true
    echo "Horizon did not restart within ${horizon_restart_timeout} seconds." >&2

    return 1
}

ensure_queue_runtime() {
    local excluded_process_ids="${1:-}"

    if wait_for_queue_runtime "$excluded_process_ids"; then
        return
    fi

    start_horizon_directly "$excluded_process_ids"
}

prepare_frontend_artifact() {
    echo "Verifying the locally built frontend release..."

    if [ ! -f "$frontend_release_archive" ] || [ ! -f "$frontend_release_archive_hash" ] || [ ! -f "$frontend_release_marker" ] || [ ! -f "$frontend_release_manifest" ]; then
        echo "The deployment release is missing." >&2
        echo "Run gitpush locally, then use Cloudways Pull from the main branch again." >&2

        return 1
    fi

    if ! php scripts/frontend-release.php verify; then
        echo "The pulled source and frontend release do not belong together." >&2
        echo "Run gitpush locally and use Cloudways Pull again." >&2

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
        echo "The frontend release archive could not be extracted." >&2

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

    echo "Frontend artifact installed."

    return 0
}

finalize_frontend_artifact() {
    if [ -z "$frontend_backup_directory" ] || [ ! -d "$frontend_backup_directory" ]; then
        return
    fi

    case "$frontend_backup_directory" in
        "${project_directory}"/public/.schooltool-build-backup.*)
            rm -rf -- "$frontend_backup_directory"
            frontend_backup_directory=""
            ;;
        *)
            echo "Refusing to remove unexpected frontend backup directory: ${frontend_backup_directory}" >&2

            return 1
            ;;
    esac
}

rollback_frontend_artifact() {
    if [ -z "$frontend_backup_directory" ] || [ ! -d "$frontend_backup_directory" ]; then
        return
    fi

    echo "Restoring the previous frontend build..." >&2

    if [ -d public/build ]; then
        rm -rf -- public/build
    fi

    if mv "$frontend_backup_directory" public/build; then
        frontend_backup_directory=""

        return
    fi

    echo "Could not restore the previous frontend from ${frontend_backup_directory}." >&2
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
    rollback_frontend_artifact

    if [ "$maintenance_mode_enabled" = true ]; then
        if [ "$backend_update_started" = true ]; then
            echo "Deployment failed after application changes began." >&2
            echo "The application remains in maintenance mode. Fix the error, rerun composer deploy, then use php artisan up only after success." >&2
        else
            echo "Restoring the application from maintenance mode..."

            if php artisan up; then
                rm -f -- "$maintenance_marker"
                maintenance_mode_enabled=false
            fi
        fi
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

command -v pgrep >/dev/null 2>&1 || {
    echo "pgrep was not found on PATH."
    exit 1
}

exec 9>storage/framework/cloudways-deploy.lock

if ! flock -n 9; then
    echo "Another Cloudways deployment is already running." >&2
    exit 1
fi

if [ "$prepare_only" = true ]; then
    verify_queue_runtime
    prepare_cloudways_pull
    trap - EXIT

    exit 0
fi

if [ -f storage/framework/down ]; then
    if [ ! -f "$maintenance_marker" ]; then
        echo "The application is in maintenance mode, but not because of this deployment workflow." >&2
        echo "Resolve that state before deploying." >&2
        exit 1
    fi

    maintenance_state="$(tr -d '\r\n' < "$maintenance_marker")"
    maintenance_mode_enabled=true

    case "$maintenance_state" in
        prepared)
            echo "Resuming the Cloudways deployment prepared before Pull."
            ;;
        backend-started)
            backend_update_started=true
            echo "Resuming the interrupted Cloudways backend deployment."
            ;;
        *)
            echo "The deployment maintenance marker has an unexpected state: ${maintenance_state:-empty}." >&2
            echo "The application remains in maintenance mode for manual inspection." >&2
            trap - EXIT
            exit 1
            ;;
    esac
elif [ -f "$maintenance_marker" ]; then
    rm -f -- "$maintenance_marker"
fi

verify_queue_runtime

if [ "$maintenance_mode_enabled" != true ]; then
    printf 'preparing\n' > "$maintenance_marker"

    if ! php artisan down --render="errors::503" --retry=60 --refresh=15; then
        rm -f -- "$maintenance_marker"
        exit 1
    fi

    maintenance_mode_enabled=true
    printf 'prepared\n' > "$maintenance_marker"
fi

echo "Pruning stale source files preserved by Cloudways Pull..."
php scripts/source-manifest.php prune-unlisted "$frontend_release_manifest_path"
prepare_frontend_artifact

backend_update_started=true
printf 'backend-started\n' > "$maintenance_marker"

composer install \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader \
    --no-interaction

install_frontend_artifact
php artisan app:update --no-interaction --skip-frontend
php artisan optimize

previous_horizon_process_ids="$(horizon_master_process_ids)"
php artisan horizon:terminate
ensure_queue_runtime "$previous_horizon_process_ids"

php artisan up
maintenance_mode_enabled=false
backend_update_started=false
rm -f -- "$maintenance_marker"
finalize_frontend_artifact
cleanup_frontend_artifact
trap - EXIT

echo "Cloudways deployment completed successfully."
