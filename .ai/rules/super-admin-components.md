---
paths:
  - '{app/Models/School.php,app/Http/Requests/Admin/School*Request.php,app/Policies/SchoolPolicy.php,resources/js/pages/admin/settings/Settings.vue,resources/js/pages/admin/superAdmin/components/Schools.vue,tests/**}'
---

# Super Admin Components

## Scope school color changes by role
School colors are stored as strict six-digit #RRGGBB values. A super_admin may change any school's color; an admin may change only their own school's color. Keep that restriction enforced by SchoolPolicy on the server, and keep non-color school-management actions super_admin-only in the settings UI.
