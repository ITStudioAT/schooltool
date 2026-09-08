---
paths:
  - '{scripts/git_helpers.ps1,scripts/update-changelog.mjs}'
---

# Scripts 2

## Build changelog before versioned gitpush commits
When gitpush receives a version, run scripts/update-changelog.mjs before source change detection and staging. Read the exact version section from UPDATES.md, upsert the Docusaurus changelog/sidebar, build both locales, and overlay generated documentation while preserving standalone pages. Missing notes or build errors must stop the release; unversioned pushes skip this step. Default documentation root is C:/docusaurus/schooltool, overridable with SCHOOLTOOL_DOCUMENTATION_ROOT.
