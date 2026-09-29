---
paths:
  - '{scripts/git_helpers.ps1,scripts/update-changelog.mjs}'
  - '{scripts/git_helpers.ps1,scripts/git_workflow.ps1,scripts/install_powershell_helpers.ps1}'
---

# Scripts 2

## Build changelog before versioned publication
When main gitsave or gitrelease receives a version, run scripts/update-changelog.mjs before source change detection and staging. Read the exact version section from UPDATES.md, upsert the Docusaurus changelog/sidebar, build both locales, and overlay generated documentation while preserving standalone pages. Missing notes or build errors must stop the release; unversioned publication skips this step. Default documentation root is C:/docusaurus/schooltool, overridable with SCHOOLTOOL_DOCUMENTATION_ROOT. Feature gitsave does not accept a version. Legacy gitpush delegates to the new checked publication semantics.

## Synchronize pinned dependencies when taking over on another PC
The operator's automatic package update request means installing the Composer/npm versions committed in lockfiles when receiving work on another PC, not enabling unattended version upgrades or dependency bots. Route gitpull through trusted project helpers and run the existing Windows local --prepare path after a successful pull; gitmain/gitwork/gitupdate already prepare dependencies. Use composer install/npm ci, skip matching installs, surface failures, and keep this dependency synchronization free of migrations, seeds, deployments and secret changes.
