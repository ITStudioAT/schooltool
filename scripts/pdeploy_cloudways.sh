#!/usr/bin/env bash
set -Eeuo pipefail

launcher_path="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)/$(basename -- "${BASH_SOURCE[0]}")"
project_directory="$(cd -- "${SCHOOLTOOL_DEPLOY_PROJECT_DIRECTORY:-$(dirname -- "$launcher_path")/..}" && pwd)"
cd "$project_directory"

if [[ "${SCHOOLTOOL_PREVIEW_INSTANCE:-false}" =~ ^(true|1|yes|on)$ ]] || { [ -f .env ] && grep -Eiq "^[[:space:]]*SCHOOLTOOL_PREVIEW_INSTANCE[[:space:]]*=[[:space:]]*['\"]?(true|1|yes|on)['\"]?([[:space:]]*(#.*)?)?$" .env; }; then
    echo "Production deployment is disabled on the preview instance. Use gitpreview." >&2
    exit 1
fi

expected_main="${SCHOOLTOOL_EXPECTED_MAIN_COMMIT:-}"
expected_source="${SCHOOLTOOL_EXPECTED_SOURCE_COMMIT:-}"
expected_frontend="${SCHOOLTOOL_EXPECTED_FRONTEND_SHA256:-}"
expected_manifest="${SCHOOLTOOL_EXPECTED_SOURCE_MANIFEST_BLOB:-}"
if [[ ! "$expected_main" =~ ^([a-f0-9]{40}|[a-f0-9]{64})$ ]] || [[ ! "$expected_source" =~ ^([a-f0-9]{40}|[a-f0-9]{64})$ ]] || [[ ! "$expected_frontend" =~ ^[a-f0-9]{64}$ ]] || [[ ! "$expected_manifest" =~ ^([a-f0-9]{40}|[a-f0-9]{64})$ ]]; then
    echo "Deployment requires pinned main, source, frontend and source manifest identities. Use gitdeploy after its package checks and LIVE confirmation." >&2
    exit 1
fi

# This identifies the confirmed package handoff, not a claim that tests passed.
if [[ "${SCHOOLTOOL_PUBLICATION_POLICY:-}" != 'background-ci-v1' ]]; then
    echo "Deployment requires the background-ci-v1 package handoff. Use gitdeploy." >&2
    exit 1
fi

verify_confirmed_release() {
    if [ ! -f deployment/source-commit ] || [ "$(tr -d '\r\n' < deployment/source-commit)" != "$expected_source" ]; then
        echo "The pulled source differs from the confirmed release. The application remains in maintenance mode." >&2
        exit 1
    fi
    actual_frontend="$(php -r 'echo is_file("deployment/frontend-build.tar.gz") ? hash_file("sha256", "deployment/frontend-build.tar.gz") : "missing";')"
    if [ "$actual_frontend" != "$expected_frontend" ]; then
        echo "The pulled frontend differs from the confirmed release. The application remains in maintenance mode." >&2
        exit 1
    fi
    actual_manifest="$(php -r '$contents = @file_get_contents("deployment/source-manifest.sha256"); if ($contents === false) { exit(1); } echo hash(strlen($argv[1]) === 40 ? "sha1" : "sha256", "blob ".strlen($contents)."\0".$contents);' "$expected_manifest")"
    if [ "$actual_manifest" != "$expected_manifest" ]; then
        echo "The pulled backend manifest differs from the confirmed release. The application remains in maintenance mode." >&2
        exit 1
    fi
    # Do not execute a pulled verifier until its complete source matches the pinned manifest.
    php -r '
        $manifest = file_get_contents("deployment/source-manifest.sha256");
        $root = str_replace("\\", "/", (string) realpath("."))."/";
        $verified = [];
        foreach (preg_split("/\R/", trim($manifest)) ?: [] as $line) {
            if (! preg_match("/^([0-9a-f]{64})  (.+)$/", $line, $entry)) {
                fwrite(STDERR, "Invalid pinned source manifest.\n"); exit(1);
            }
            $path = $entry[2];
            $resolved = realpath($path);
            if (str_starts_with($path, "/") || str_contains($path, "\\")
                || preg_match("#(^|/)\.\.?(/|$)#", $path) || isset($verified[$path])
                || ! is_file($path) || is_link($path) || $resolved === false
                || ! str_starts_with(str_replace("\\", "/", $resolved), $root)) {
                fwrite(STDERR, "Unsafe or missing pinned source file.\n"); exit(1);
            }
            $contents = file_get_contents($path);
            if ($contents === false) { fwrite(STDERR, "Cannot read pinned source file.\n"); exit(1); }
            if (! str_contains($contents, "\0")) { $contents = str_replace(["\r\n", "\r"], "\n", $contents); }
            if (! hash_equals($entry[1], hash("sha256", $contents))) {
                fwrite(STDERR, "The pulled source differs from the confirmed manifest: ".$path."\n"); exit(1);
            }
            $verified[$path] = true;
        }
        foreach (["artisan", "composer.json", "composer.lock", "scripts/frontend-release.php", "scripts/source-manifest.php", "scripts/deploy_cloudways.sh", "scripts/pdeploy_cloudways.sh"] as $required) {
            if (! isset($verified[$required])) { fwrite(STDERR, "Pinned source manifest omits a required deployment file.\n"); exit(1); }
        }
    '
    php scripts/frontend-release.php verify "$expected_source"
}

for command_name in bash php flock; do
    if ! command -v "$command_name" >/dev/null 2>&1; then
        echo "${command_name} was not found on PATH." >&2
        exit 1
    fi
done

pdeploy_lock="${project_directory}/storage/framework/cloudways-pdeploy.lock"
pdeploy_lock_conflict_exit_code=75

if [ "${SCHOOLTOOL_CLOUDWAYS_PDEPLOY_LOCKED:-false}" != true ]; then
    if SCHOOLTOOL_CLOUDWAYS_PDEPLOY_LOCKED=true flock \
        --exclusive \
        --nonblock \
        --close \
        --conflict-exit-code "$pdeploy_lock_conflict_exit_code" \
        "$pdeploy_lock" \
        "$BASH" "$launcher_path" "$@"; then
        exit 0
    else
        deployment_exit_code=$?
    fi

    if [ "$deployment_exit_code" -eq "$pdeploy_lock_conflict_exit_code" ]; then
        echo "Another terminal pull deployment is already running." >&2
        exit 1
    fi

    exit "$deployment_exit_code"
fi

unset SCHOOLTOOL_CLOUDWAYS_PDEPLOY_LOCKED

maintenance_marker="${project_directory}/storage/framework/cloudways-deploy-maintenance"
maintenance_prepared=false
deployment_handed_off=false

restore_after_pull_failure() {
    if [ "$maintenance_prepared" != true ] || [ "$deployment_handed_off" = true ]; then
        return
    fi

    if [ ! -f "$maintenance_marker" ] || [ "$(tr -d '\r\n' < "$maintenance_marker")" != prepared ]; then
        echo "The terminal pull failed, but the deployment maintenance marker changed unexpectedly." >&2
        echo "The application remains in maintenance mode for manual inspection." >&2

        return
    fi

    echo "The terminal pull failed; restoring the application from maintenance mode..." >&2

    if php artisan up; then
        rm -f -- "$maintenance_marker"
        maintenance_prepared=false
        php scripts/deployment-status.php idle || true
    fi
}

pull_with_cloudways_api() {
    echo "No Git working tree found; using the Cloudways platform Pull API."

    if ! php artisan cloudways:pull --check --no-interaction; then
        echo "Configure the Cloudways deployment API values, clear cached configuration, and run gitdeploy again." >&2
        exit 1
    fi

    SCHOOLTOOL_CLOUDWAYS_TERMINAL_PULL=true bash scripts/deploy_cloudways.sh --prepare
    maintenance_prepared=true

    if ! php artisan cloudways:pull --no-interaction; then
        echo "Cloudways did not complete the platform Pull; deployment was not started." >&2
        exit 1
    fi

    deployment_handed_off=true
    verify_confirmed_release
    bash scripts/deploy_cloudways.sh

    maintenance_prepared=false
    trap - EXIT

    echo "Cloudways platform Pull and deployment completed successfully."
    php -r 'echo "Abgeschlossen: ", (new DateTimeImmutable("now", new DateTimeZone("Europe/Vienna")))->format("d.m.Y H:i:s T"), " (Europe/Vienna)", PHP_EOL;'
    exit 0
}

trap restore_after_pull_failure EXIT

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    pull_with_cloudways_api
fi

if ! command -v git >/dev/null 2>&1; then
    echo "git was not found on PATH." >&2
    exit 1
fi

current_branch="$(git branch --show-current)"

if [ "$current_branch" != main ]; then
    echo "Terminal deployment requires the main branch; current branch: ${current_branch:-detached HEAD}." >&2
    exit 1
fi

if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
    echo "Tracked production files contain local changes; refusing to pull." >&2
    echo "Review git status before deploying." >&2
    exit 1
fi

echo "Fetching origin/main before maintenance mode..."
git fetch origin main

if [ "$(git rev-parse FETCH_HEAD)" != "$expected_main" ]; then
    echo "GitHub main changed after confirmation; no maintenance or database update was started." >&2
    exit 1
fi

if ! git merge-base --is-ancestor HEAD FETCH_HEAD; then
    echo "Production main has diverged from origin/main; refusing to merge or overwrite files." >&2
    exit 1
fi

SCHOOLTOOL_CLOUDWAYS_TERMINAL_PULL=true bash scripts/deploy_cloudways.sh --prepare
maintenance_prepared=true

echo "Fast-forwarding production to origin/main..."
git merge --ff-only FETCH_HEAD

deployment_handed_off=true
verify_confirmed_release
bash scripts/deploy_cloudways.sh

maintenance_prepared=false
trap - EXIT

echo "Cloudways pull and deployment completed successfully."
php -r 'echo "Abgeschlossen: ", (new DateTimeImmutable("now", new DateTimeZone("Europe/Vienna")))->format("d.m.Y H:i:s T"), " (Europe/Vienna)", PHP_EOL;'
