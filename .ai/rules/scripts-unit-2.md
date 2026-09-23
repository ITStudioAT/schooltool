---
paths:
  - '{scripts/git_helpers.ps1,scripts/git_branch_helpers.ps1,tests/Unit/GitBranchWorkflowTest.php}'
  - '{scripts/deploy_preview_cloudways.sh,scripts/git_preview_helpers.ps1,tests/Unit/PreviewDeploymentTest.php}'
  - '{scripts/update.php,tests/Unit/LocalDeploymentGuardTest.php}'
  - '{scripts/git_branch_helpers.ps1,scripts/git_preview_helpers.ps1,tests/Unit/GitBranchWorkflowTest.php}'
  - '{scripts/git_ssh_helpers.ps1,tests/Unit/GitDeploymentSshTest.php}'
---

# Scripts Unit 2

## Bound release PHP tests with sequential owned-database batches
Full local release checks discover every tests/Unit and tests/Feature *Test.php, sort paths ordinally and execute at most ten files per fresh native PHP process, sequentially with stop-on-failure/error. Keep the existing integration-group exclusion; never raise global memory limits or skip failed files. Revalidate the active candidate path, self-created local database environment, matching successful ownership receipt and uncached test config before each batch. Preserve batch file manifests/stdout/stderr on failure. Run later release checks only after PHP batches pass. Bind Process.Handle immediately after Start-Process on Windows PowerShell 5.1 so ExitCode remains available after the process exits; verify native success and failure codes and literal file arguments.

## Check preview plan from the running target application
Run the under-lock preview:snapshot assert-plan command in a target-directory subshell, then resume candidate preflight. Never override candidate LARAVEL_STORAGE_PATH to target storage: the preview runtime correctly rejects storage outside its application. Keep helper protocol pinning and executable deployment tests aligned, checking target cwd and restoration of candidate cwd.

## Treat npm's hidden lock as a disposable cache
Local preparation must validate installed locked package versions and executable targets/shims, not reinstall merely because node_modules/.package-lock.json is missing or older. A matching project lock receipt can establish identity without that npm cache; absent/stale receipts need matching installed-lock evidence. Preserve npm's optional platform/package omissions and mark interrupted installs incomplete. On Windows, refuse a necessary npm ci before removal while this project's node_modules executables are running; never kill unrelated processes automatically.

## Discard a feature only through its exact reserved lifecycle
gitdiscard NAME runs only on clean current main, refuses occupied worktrees, divergent local/remote tips, invalid reservations or active/unverifiable preview, and requires DISCARD feature/NAME. Preserve exact feature/reservation commits under refs/schooltool/discarded before atomically deleting both remote refs with exact leases; local cleanup is CAS and never merges. gitdiscard and confirmed gitpreview share an exclusive codex/operations/<lifecycle> lock; every participating PC needs current helpers. Preserve candidates/receipts and fail closed on stale locks; no DB changes.

## Stage live launchers in an exclusive private directory
storage/framework can legitimately be 0775 on Cloudways. Keep the SSH transfer parent guard strict; after LIVE create a unique 0700 child directory and transfer launcher.sh there. Never chmod the shared framework directory or loosen transfer ownership/canonical-path checks. Clean only the exact launcher and then the empty directory; retain uncertain/failed cleanup for inspection. Exit21 is the remote transfer parent guard, before file creation or activation.
