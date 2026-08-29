---
paths:
  - 'scripts/*cloudways*.sh'
---

# Scripts

## Do not leak deployment locks into daemons
Keep the terminal-pull lock in a dedicated `flock --close` wrapper so deployment descendants never inherit it. Any long-lived process started by the deployment script must explicitly close deployment descriptors 8 and 9. Preserve regression coverage in `tests/Unit/DeploymentLauncherTest.php`.

## Keep Horizon monitor grace short and direct startup verification long
During Cloudways deployment, exclude the terminating Horizon master IDs, recycle stale masters, and give the external process monitor one short grace period before the direct fallback. Keep the directly started Horizon health timeout separate and longer. Always health-check before recycling or starting so a monitor-started replacement is never duplicated, and keep deployment lock descriptors closed on nohup.
