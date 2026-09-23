---
paths:
  - '{scripts/git_helpers.ps1,scripts/git_branch_helpers.ps1,tests/Unit/GitBranchWorkflowTest.php}'
  - '{scripts/deploy_preview_cloudways.sh,scripts/git_preview_helpers.ps1,tests/Unit/PreviewDeploymentTest.php}'
  - '{scripts/update.php,tests/Unit/LocalDeploymentGuardTest.php}'
---

# Scripts Unit 2

## Bound release PHP tests with sequential owned-database batches
Full local release checks discover every tests/Unit and tests/Feature *Test.php, sort paths ordinally and execute at most ten files per fresh native PHP process, sequentially with stop-on-failure/error. Keep the existing integration-group exclusion; never raise global memory limits or skip failed files. Revalidate the active candidate path, self-created local database environment, matching successful ownership receipt and uncached test config before each batch. Preserve batch file manifests/stdout/stderr on failure. Run later release checks only after PHP batches pass. Bind Process.Handle immediately after Start-Process on Windows PowerShell 5.1 so ExitCode remains available after the process exits; verify native success and failure codes and literal file arguments.

## Check preview plan from the running target application
Run the under-lock preview:snapshot assert-plan command in a target-directory subshell, then resume candidate preflight. Never override candidate LARAVEL_STORAGE_PATH to target storage: the preview runtime correctly rejects storage outside its application. Keep helper protocol pinning and executable deployment tests aligned, checking target cwd and restoration of candidate cwd.

## Treat npm's hidden lock as a disposable cache
Local preparation must validate installed locked package versions and executable targets/shims, not reinstall merely because node_modules/.package-lock.json is missing or older. A matching project lock receipt can establish identity without that npm cache; absent/stale receipts need matching installed-lock evidence. Preserve npm's optional platform/package omissions and mark interrupted installs incomplete. On Windows, refuse a necessary npm ci before removal while this project's node_modules executables are running; never kill unrelated processes automatically.
