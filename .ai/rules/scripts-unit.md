---
paths:
  - 'scripts/git_branch_helpers.ps1,tests/Unit/GitBranchWorkflowTest.php'
  - 'scripts/git_ssh_helpers.ps1,tests/Unit/GitDeploymentSshTest.php'
---

# Scripts Unit

## Use relative Laravel cache overrides in Windows candidate worktrees
Candidate APP_CONFIG_CACHE/APP_ROUTES_CACHE/APP_PACKAGES_CACHE/APP_SERVICES_CACHE/APP_EVENTS_CACHE must be relative bootstrap/cache paths. Laravel normalizeCachePath treats Windows drive-letter paths as relative and prefixes the worktree. Retain absolute owned CachePaths only for cleanup and test actual Laravel path resolution.

## Keep candidate worktrees outside Git metadata
Create preserved release/preview/test worktrees under the user's dedicated temporary schooltool-worktrees/<uuid> directory, never inside .git: Vite's default server.fs.deny rejects **/.git/** including Vitest setup files. Do not weaken that deny rule. Reject an existing target and a temporary path inside .git. Isolated workflow tests set their own TEMP/TMP and include a real minimal Vitest setup-file regression. Candidate Laravel cache settings must be relative bootstrap/cache/<unique-receipt>.*.php paths; retain absolute candidate paths separately for exact cleanup because Laravel does not treat Windows C:/ paths as absolute cache paths.

## Transfer preview archives through native SSH binary streams
Cloudways SFTP can use a different path namespace from SSH, so transfer canonical SSH paths through redirected native SSH byte streams with the existing strict host/account guards. Create uploads exclusively with private permissions and length/SHA checks; publish downloads only after SSH success, then require the caller's expected snapshot hash. PowerShell 5.1 stdin encoding must be BOM-free before Process.Start and raw stdin must close without StreamWriter BOM emission. Do not return VoidTaskResult objects into the PowerShell pipeline.
