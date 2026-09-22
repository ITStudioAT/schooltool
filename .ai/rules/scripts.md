---
paths:
  - 'scripts/*cloudways*.sh'
  - scripts/frontend-release.php
  - 'scripts/git*.ps1'
  - scripts/install_powershell_helpers.ps1
  - 'scripts/*cloudways*.sh, scripts/frontend-release.php, scripts/git*.ps1, scripts/install_powershell_helpers.ps1'
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

## Install Git helpers into both Windows PowerShell and PowerShell 7 profiles
composer setup:powershell invokes powershell.exe (5.1), even from PowerShell 7. Update both Documents/WindowsPowerShell and Documents/PowerShell console profiles plus the current host profile; preserve unmanaged content and use the redirected Documents folder. Tests must pass a sandbox DocumentsDirectory and mock PROFILE to avoid touching real user profiles. A child installer cannot load functions into the caller: reopen the terminal or dot-source $PROFILE.

## Reserve independent features and publish only checked exact Git states
main and multiple independent feature/* are the user branches; each feature has an atomic reservation containing an immutable lifecycle id. gitsave commits/pushes only the current branch; main builds the bound release and delegates required tests to GitHub policy v2, with optional local -Full; versioned saves require release notes/docs. gitpreview and gitrelease prepare preserved candidate worktrees, merge current main there and run mandatory local full checks before explicit PREVIEW/RELEASE confirmation. Release atomically advances main and removes only the exact checked feature/reservation, with ancestry checks and compare-and-swap leases; never rewrite history. Branch switches prepare dependencies/build/cache only, without migrations or seeds. Candidate tests use only self-created disposable local databases, never the existing developer/test database. gitdeploy requires exact successful policy-v2 CI evidence, LIVE confirmation and key-only strict-host-verified SSH, pinning both frontend and backend source identity; bare pdeploy is not an unchecked fallback. Preview import/deployment failure stays closed for explicit recovery; no automatic Git/database rollback is assumed.

## Keep independent feature lifecycles and explicitly select one shared preview
Multiple feature/* branches may remain open. New reservations live at codex/features/<slug>; read the existing codex/active-feature reservation only for its own branch and preserve its exact commit/lifecycle id until that feature releases. Never delete other feature reservations at release. gitwork NAME selects work; with multiple features gitpreview -Feature NAME must explicitly match the checkout. Switching preview lifecycle requires REFRESH and PREVIEW for fresh live data; returning also uses fresh data, not old feature test data. Revalidate preview state after confirmation and under the server deployment lock before mutation. Branch switches never switch or mutate databases.

## Keep deployment recovery files outside Cloudways permission resets
Cloudways platform Git Pull resets permissions of files/directories below public_html, including untracked dot-directories (observed 700→775 and 600→664). Do not assume a private operational directory there remains private after Pull. Prefer application-private storage outside public_html for recovery state. If a reviewed deployment deliberately keeps an operation there, fail closed on changed permissions, verify pinned source/backup/operation identity, restore exact private modes, and use verified resume without weakening checks or repeating the Pull.
