---
paths:
  - '{app/Http/Controllers/Admin/StudentsTimetables/TeacherAccountController.php,app/Models/Teacher.php,resources/js/pages/admin/settings/components/StudentsTimetablesTeachers.vue}'
---

# Settings Components

## Bulk teacher active state stays school scoped
The Lehrerliste bulk active/inactive action applies to all imported and registered roster entries in the authenticated manager's school, regardless of pagination or search. Bulk deactivation must keep the acting user plus admin and super_admin accounts active; bulk activation may reactivate every roster entry. Imported Teacher rows persist is_active and a fresh teacher-list import sets imported rows active again.
