---
paths:
  - '{app/Http/Controllers/Admin/AdminController.php,resources/js/pages/admin/App.vue,resources/js/pages/admin/components/AdminAppBar.vue}'
---

# Components

## Show the schoolwide schoolyear in the admin shell
Expose `schoolwide_active_schoolyear` separately from the user's `selected_schoolyear`. Keep the schoolwide year persistently visible in the admin app bar, warn when the personal view year differs, and show a clear error state when no schoolwide year is configured.
