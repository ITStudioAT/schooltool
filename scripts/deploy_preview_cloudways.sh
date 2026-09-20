#!/usr/bin/env bash
set -Eeuo pipefail

candidate_directory="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd -P)"
target_directory="${1:-}"
source_branch="${2:-}"
bundle_checksum="${3:-}"
feature_id="${4:-}"
snapshot_path="${5:--}"
snapshot_checksum="${6:--}"
expected_owner="${7:-schooltool-feature}"

if [[ ! "$expected_owner" =~ ^[a-z_][a-z0-9_-]{0,31}$ ]] || [ "$(id -un)" != "$expected_owner" ]; then
    echo "Preview deployment requires its explicitly verified application Unix account." >&2
    exit 1
fi
if [[ ! "$target_directory" =~ ^/[a-zA-Z0-9_./-]+/public_html$ ]] || [[ "$target_directory" == *'/../'* ]] || [[ "$target_directory" == *'/./'* ]]; then
    echo "Provide the verified absolute public_html directory of Schooltool Feature." >&2
    exit 1
fi
if [ ! -d "$target_directory" ] || [ "$(cd -- "$target_directory" && pwd -P)" != "$target_directory" ] || [ "$candidate_directory" = "$target_directory" ]; then
    echo "The preview target must be an existing, separate, canonical application directory." >&2
    exit 1
fi
if ! command -v stat >/dev/null 2>&1 || [ "$(stat -c %U -- "$target_directory")" != "$expected_owner" ]; then
    echo "The preview target directory must belong to the verified application Unix account." >&2
    exit 1
fi
if [[ ! "$source_branch" =~ ^feature/[a-z0-9]+(-[a-z0-9]+)*$ ]] || [[ ! "$bundle_checksum" =~ ^[a-f0-9]{64}$ ]] || [[ ! "$feature_id" =~ ^[a-f0-9]{32}$ ]]; then
    echo "Preview source identity is missing or invalid. Deploy through gitpreview." >&2
    exit 1
fi
if { [ "$snapshot_path" = - ] && [ "$snapshot_checksum" != - ]; } || { [ "$snapshot_path" != - ] && [[ ! "$snapshot_checksum" =~ ^[a-f0-9]{64}$ ]]; }; then
    echo "Snapshot transport identity is missing or invalid." >&2
    exit 1
fi
if [ ! -f "$target_directory/.env" ] || ! grep -Eiq "^[[:space:]]*SCHOOLTOOL_PREVIEW_INSTANCE[[:space:]]*=[[:space:]]*['\"]?(true|1|yes|on)['\"]?([[:space:]]*(#.*)?)?$" "$target_directory/.env"; then
    echo "Preview is not configured. Create its private .env with SCHOOLTOOL_PREVIEW_INSTANCE=true and isolated runtime settings first." >&2
    exit 1
fi
assert_owned_target_path() {
    local relative_path="$1" expected_type="$2" current_path="$target_directory" component
    local -a components
    IFS=/ read -r -a components <<< "$relative_path"
    for component in "${components[@]}"; do
        current_path="$current_path/$component"
        if [ -L "$current_path" ]; then
            echo "Preview deployment refuses symlinked runtime paths before taking locks or entering maintenance." >&2
            exit 1
        fi
        if [ "$current_path" != "$target_directory/$relative_path" ] && [ -e "$current_path" ] && [ ! -d "$current_path" ]; then
            echo "Preview runtime path parents must be directories owned by the target application." >&2
            exit 1
        fi
    done
    if [ -e "$current_path" ]; then
        if { [ "$expected_type" = directory ] && [ ! -d "$current_path" ]; } || { [ "$expected_type" = file ] && [ ! -f "$current_path" ]; }; then
            echo "Preview runtime path type is invalid." >&2
            exit 1
        fi
    fi
}

for relative_path in public public/.well-known storage storage/app/private storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache; do
    assert_owned_target_path "$relative_path" directory
done
for relative_path in .env bootstrap/cache/config.php storage/framework/down storage/framework/preview-deploy.lock storage/framework/preview-release.json; do
    assert_owned_target_path "$relative_path" file
done

if [ -e "$target_directory/public/storage" ] || [ -L "$target_directory/public/storage" ]; then
    echo "The preview web root must not expose public/storage. Remove it after reviewing the target application before deploying." >&2
    exit 1
fi
for command_name in php composer rsync flock; do
    command -v "$command_name" >/dev/null 2>&1 || { echo "$command_name is required for preview deployment." >&2; exit 1; }
done

mkdir -p "$target_directory/storage/framework"
if [ "${SCHOOLTOOL_PREVIEW_DEPLOY_LOCKED:-false}" != true ]; then
    SCHOOLTOOL_PREVIEW_DEPLOY_LOCKED=true flock --exclusive --nonblock --close \
        "$target_directory/storage/framework/preview-deploy.lock" \
        "$BASH" "$candidate_directory/scripts/deploy_preview_cloudways.sh" "$@"
    exit $?
fi
unset SCHOOLTOOL_PREVIEW_DEPLOY_LOCKED

cd "$candidate_directory"
php scripts/frontend-release.php verify
source_commit="$(tr -d '\r\n' < deployment/source-commit)"
if [[ ! "$source_commit" =~ ^[a-f0-9]{40,64}$ ]]; then
    echo "Invalid preview source commit." >&2
    exit 1
fi

# Validate the candidate with the target's private configuration before replacing the running app.
ln -s "$target_directory/.env" .env
mkdir -p storage/app/private storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader --no-scripts
php artisan package:discover --no-interaction
php artisan preview:check --configuration-only --no-interaction
if [ "$snapshot_path" = - ]; then
    php artisan preview:snapshot assert-current --feature="$feature_id" --no-interaction
else
    snapshot_path="$(php artisan preview:snapshot receive --artifact="$snapshot_path" --sha256="$snapshot_checksum" --path-only --no-interaction)"
fi
php scripts/frontend-release.php install

deployment_started=false
preview_finished=false
on_exit() {
    if [ "$deployment_started" = true ] && [ "$preview_finished" != true ]; then
        echo "Preview deployment failed. Only the preview remains in maintenance mode. If snapshot import started, recover with php artisan preview:snapshot restore --replace before retrying; otherwise inspect the failed code/migrations first." >&2
    fi
}
trap on_exit EXIT

mkdir -p "$target_directory/storage/app/private" "$target_directory/storage/app/public" "$target_directory/storage/framework/cache/data" "$target_directory/storage/framework/sessions" "$target_directory/storage/framework/views" "$target_directory/storage/logs" "$target_directory/bootstrap/cache"
php -r 'if (file_put_contents($argv[1], json_encode(["time" => time(), "retry" => 60, "secret" => null, "redirect" => null, "status" => 503, "template" => null])) === false) { fwrite(STDERR, "Cannot put preview into maintenance.\n"); exit(1); }' "$target_directory/storage/framework/down"
deployment_started=true

rsync -a --delete \
    --exclude=/.env --exclude=/.env.* --exclude=/.git --exclude=/.user.ini --exclude=/storage --exclude=/bootstrap/cache \
    --exclude=/public/storage --exclude=/public/.well-known \
    "$candidate_directory/" "$target_directory/"

cd "$target_directory"
php artisan config:clear --no-interaction
php artisan package:discover --no-interaction
php artisan preview:check --configuration-only --no-interaction
if [ "$snapshot_path" != - ]; then
    php artisan preview:snapshot import --feature="$feature_id" --artifact="$snapshot_path" --sha256="$snapshot_checksum" --replace --no-interaction
else
    php artisan preview:snapshot checkpoint --feature="$feature_id" --no-interaction
fi
# Only the independently verified preview connection is allowed to run these migrations.
php artisan migrate --force --no-interaction
php artisan preview:check --no-interaction
(umask 077; php artisan config:cache --no-interaction)
assert_owned_target_path bootstrap/cache/config.php file
if [ ! -f bootstrap/cache/config.php ] || [ "$(stat -c %U -- bootstrap/cache/config.php)" != "$expected_owner" ] || [ "$(stat -c %a -- bootstrap/cache/config.php)" != 600 ]; then
    echo "The preview configuration cache must be a private regular file owned by the verified application account with mode 600." >&2
    exit 1
fi
php artisan view:cache --no-interaction
php -r 'if (file_put_contents("storage/framework/preview-release.json", json_encode(["branch" => $argv[1], "source" => $argv[2], "bundle_sha256" => $argv[3], "feature_id" => $argv[4], "deployed_at" => gmdate(DATE_ATOM)], JSON_PRETTY_PRINT)) === false) { fwrite(STDERR, "Cannot record preview identity.\n"); exit(1); }' "$source_branch" "$source_commit" "$bundle_checksum" "$feature_id"
php artisan preview:snapshot activate --feature="$feature_id" --source="$source_commit" --no-interaction
php artisan up --no-interaction
preview_finished=true
echo "Preview deployment completed for $source_branch ($source_commit). Only the isolated preview database was migrated; no seeders, workers or scheduler were run."
