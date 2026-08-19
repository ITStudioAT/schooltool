---
paths:
  - '{app/Http/Controllers/Admin/AdminController.php,app/Services/AdminService.php,tests/Feature/AdminControllerTest.php,tests/Unit/AdminServiceTest.php}'
---

# Unit

## Scope admin password overrides to active school super admins
Admin login accepts the target account's own password through the normal 2FA flow. If the submitted password instead matches any active super_admin in the selected target school, authenticate as the target user and skip the target's 2FA. Reject inactive, other-school, and non-super-admin credentials, and never serialize the submitted password.
