---
paths:
  - '{scripts/git_preview*.ps1,scripts/deploy_preview_cloudways.sh,app/Services/FeaturePreviewSnapshotService.php}'
---

# Scripts Services

## Return the shared preview to main without a substitute feature
gitpreview -Main runs only from clean saved main, binds its exact commit in a separate protected receipt and publishes only a preview artifact. Reserved all-zero snapshot identity represents main and must never be a registered feature; retain it in status for older clients' REFRESH consent. Use existing remote status/export/assert-plan before installing the candidate, then its new complete-main action after up. Keep pending backup/recovery until completion and fail closed on errors; gitdiscard must not accept incomplete or inconsistent main activation. Lock main and the displaced lifecycle and preserve REFRESH/PREVIEW, source/plan revalidation, server flock and local recovery.
