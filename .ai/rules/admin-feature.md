---
paths:
  - '{app/Http/Controllers/Admin/SchoolToolController.php,resources/js/pages/admin/App.vue,tests/Feature/SchoolToolControllerTest.php}'
---

# Admin Feature

## Super admins may change the schoolwide schoolyear
Treat both `super_admin` and `admin` as authorized to change a school's active schoolyear in the app bar and the server endpoint. Keep the endpoint scoped to schoolyears belonging to the authenticated user's school, and retain a forbidden test for ordinary users.
