---
paths:
  - '{app/Services/AdminService.php,app/Http/Controllers/Admin/AdminController.php,resources/js/pages/admin/auth/UnknownPassword.vue}'
---

# Auth

## Unknown-password email codes log admins in
Kennwort unbekannt is a passwordless admin login: after valid email codes, complete login without changing the password or requiring password entry. Preserve active/confirmed accounts, the admin login role whitelist, single-use expiring codes, legacy second-email verification, and confirmed authenticator/recovery-code challenges.
