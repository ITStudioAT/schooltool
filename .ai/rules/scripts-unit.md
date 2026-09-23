---
paths:
  - 'scripts/git_branch_helpers.ps1,tests/Unit/GitBranchWorkflowTest.php'
  - 'scripts/git_ssh_helpers.ps1,tests/Unit/GitDeploymentSshTest.php'
  - 'scripts/git*preview*.ps1,tests/Unit/GitBranchWorkflowTest.php'
---

# Scripts Unit

## Use relative Laravel cache overrides in Windows candidate worktrees
Candidate APP_CONFIG_CACHE/APP_ROUTES_CACHE/APP_PACKAGES_CACHE/APP_SERVICES_CACHE/APP_EVENTS_CACHE must be relative bootstrap/cache paths. Laravel normalizeCachePath treats Windows drive-letter paths as relative and prefixes the worktree. Retain absolute owned CachePaths only for cleanup and test actual Laravel path resolution.

## Keep candidate worktrees outside Git metadata
Create preserved release/preview/test worktrees under the user's dedicated temporary schooltool-worktrees/<uuid> directory, never inside .git: Vite's default server.fs.deny rejects **/.git/** including Vitest setup files. Do not weaken that deny rule. Reject an existing target and a temporary path inside .git. Isolated workflow tests set their own TEMP/TMP and include a real minimal Vitest setup-file regression. Candidate Laravel cache settings must be relative bootstrap/cache/<unique-receipt>.*.php paths; retain absolute candidate paths separately for exact cleanup because Laravel does not treat Windows C:/ paths as absolute cache paths.

## Transfer preview archives through native SSH binary streams
Cloudways SFTP can use a different path namespace from SSH, so transfer canonical SSH paths through redirected native SSH byte streams with the existing strict host/account guards. Create uploads exclusively with private permissions and length/SHA checks; publish downloads only after SSH success, then require the caller's expected snapshot hash. PowerShell 5.1 stdin encoding must be BOM-free before Process.Start and raw stdin must close without StreamWriter BOM emission. Do not return VoidTaskResult objects into the PowerShell pipeline.

## Resume only source-bound previews before publication
New preview receipts use v3 and certify only successful inline build/integrity preflight; v2 retains its original full-check evidence rules; bind original checkout/origin, reservation, main/feature commits, detached candidate/recovery ref, exact source/artifact and bundle. Revalidate before and after confirmation; .started blocks replay after any publication attempt. V1 receipts remain immutable and may use the exact original branch or exact refs/schooltool/archived-heads/<archive>/<original-branch> at ArtifactCommit; retain bounded legacy evidence rules, never infer certification from loose logs or add a skip flag.

## Keep checked candidates detached and recoverable
Create candidate worktrees detached, with Id/Kind/Path/common-repository/Commit bound to refs/schooltool/candidates/<kind>/<id>. Keep user commands detached-forbidden. Internal releases require the verified candidate plus build/integrity preflight and unchanged ancestry/CAS guards. Retain completed commits at checkpoints and on failure using direct refs and compare-and-swap update-ref --no-deref; refuse symbolic, moved, unrelated or foreign-repository refs. Do not change the remote active-feature reservation or preview artifact refs to simplify the local branch list.
