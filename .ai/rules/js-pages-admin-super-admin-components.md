---
paths:
  - 'resources/js/pages/admin/superAdmin/components/{Teachers.vue,TeachersList.vue,TeachersListImportDialog.vue}'
---

# Js Pages Admin Super Admin Components

## Keep teacher import under Lehrer
Expose the teacher-list import action in the registered Lehrer view, not in the preregistration Lehrerliste view. Keep upload progress active until the backend event or authenticated status fallback confirms completion, then refresh both the preregistration store and the registered teacher list.
