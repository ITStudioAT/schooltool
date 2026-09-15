---
paths:
  - 'scripts/*cloudways*.sh'
  - scripts/frontend-release.php
  - 'scripts/git*.ps1'
---

# Scripts

## Do not leak deployment locks into daemons
Keep the terminal-pull lock in a dedicated `flock --close` wrapper so deployment descendants never inherit it. Any long-lived process started by the deployment script must explicitly close deployment descriptors 8 and 9. Preserve regression coverage in `tests/Unit/DeploymentLauncherTest.php`.

## Keep Horizon monitor grace short and direct startup verification long
During Cloudways deployment, exclude the terminating Horizon master IDs, recycle stale masters, and give the external process monitor one short grace period before the direct fallback. Keep the directly started Horizon health timeout separate and longer. Always health-check before recycling or starting so a monitor-started replacement is never duplicated, and keep deployment lock descriptors closed on nohup.

## Retry atomic frontend release moves on Windows
Keep frontend activation and rollback as same-parent atomic directory renames. On Windows, retry transient rename failures with a bounded delay because freshly extracted assets may briefly be held by Defender/indexers. Never replace this with a recursive copy fallback, and always check/report rollback failure while preserving the backup path.

## Launch Windows command wrappers through cmd
When proc_open receives array commands on Windows, resolve PATH/PATHEXT wrappers such as composer.bat and npm.cmd and invoke them through the Windows command shell. Optional runtime-version probes must fail silently when a tool is unavailable, while required deployment commands must continue surfacing failures.

## Notify users before deployment and preserve recovery safety
After lock/queue preflight, announce via scripts/deployment-status.php and wait DEPLOY_NOTICE_SECONDS (default30, range0..300), then render maintenance with no Refresh header. Status lives in storage/framework; public/deployment-status.php must work without Laravel/vendor and expose only state/id. Apache serves public/maintenance.html for dynamic requests while down; its recovery requires both available status and /up JSON status=up. Active SPA notices use explicit reload to retain open input. Complete notices only after artisan up; notice write failure after up must not roll back the frontend. Pre-backend failures clear announcements only after recovery; backend failures stay down. Preserve explicit successful no-op returns in EXIT cleanup.

## Keep feature synchronization separate from production releases
gitsave only pushes feature/*; main remains Cloudways' deployment branch. gitrelease prepares a preserved codex/release-* candidate, requires current origin/main in the saved feature, runs mandatory full checks and asks for RELEASE before an atomic main/tag push. Never rebase or force-push a validated candidate after main advances. Branch switches prepare dependencies/build/cache only: commit-bound deployment artifacts are invalid on unfinished features, and migrations/seeders must not run implicitly; Git does not isolate databases.
