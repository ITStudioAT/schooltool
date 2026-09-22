---
paths:
  - '{app/Services/AdminService.php,app/Services/StudentsTimetablesStudentService.php}'
  - '{app/Services/PersonalTeachingBackupService.php,app/Services/TeachingBackupService.php}'
---

# Services Services

## Disable superadmin password overrides in preview
Preview must reject the legacy same-school super_admin password override in every login flow; only the target account's own authentication may succeed. Otherwise a revoked, deactivated or password-changed live administrator could still use a stale snapshot hash to enter another granted account. Preserve main's existing override behavior and normal target-account passwords/2FA. Cover Admin, homepage, teaching, SEPP and restaurant regressions using isolated databases.

## Keep copied preview unit files private in teaching backups
For curriculum unit_file rows whose stored disk is exactly s3, reuse CurriculumUnitFileService::diskName when collecting backup sources. A preview local result must read only its private copy; never fall back to public/default/S3 if that copy is missing or unreadable. Preserve table metadata and main, null/empty, whitespace, unknown-disk and upload fallback behavior. Keep the global preview remote-storage block unchanged.
