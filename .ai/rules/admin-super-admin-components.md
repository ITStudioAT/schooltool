---
paths:
  - '{app/Models/School.php,app/Http/Resources/Admin/SchoolResource.php,resources/js/pages/admin/App.vue,resources/js/pages/admin/components/{AdminAppBar.vue,AdminNavigationDrawer.vue},resources/js/pages/admin/superAdmin/components/Schools.vue}'
---

# Admin Super Admin Components

## Apply school color to admin chrome
Persist the admin shell choice on School.use_school_color_for_admin_ui. When true, the admin app bar, navigation drawer, and drawer toolbar use a validated six-digit School.color; when false or invalid, use Vuetify's semantic `primary` color. Do not mutate the global theme, because unrelated admin accents must remain primary.
