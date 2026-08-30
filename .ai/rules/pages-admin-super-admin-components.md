---
paths:
  - '{app/Http/Controllers/Admin/{UserController.php,UserWithRoleController.php,StudentsTimetables/AdminUserController.php,StudentsTimetables/TeacherAccountController.php},resources/js/pages/admin/superAdmin/components/Users.vue,tests/**}'
---

# Pages Admin Super Admin Components

## Never deactivate super admins
A user with the super_admin role must never transition from active to inactive through any admin or Students Timetables account endpoint. Reject deactivation server-side and hide the lock action in the UI. If legacy data contains an inactive super_admin, allow reactivation.
