---
paths:
  - 'scripts/*cloudways*.sh'
---

# Scripts

## Do not leak deployment locks into daemons
Keep the terminal-pull lock in a dedicated `flock --close` wrapper so deployment descendants never inherit it. Any long-lived process started by the deployment script must explicitly close deployment descriptors 8 and 9. Preserve regression coverage in `tests/Unit/DeploymentLauncherTest.php`.
