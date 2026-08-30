---
paths:
  - '{app/Models/User.php,app/Http/Controllers/Admin/AdminShellColorPreferenceController.php,app/Http/Resources/Admin/UserWithRoleResource.php,resources/js/pages/admin/{App.vue,profile/Profile.vue},resources/js/helpers/adminShellTheme.js}'
---

# Helpers

## Store the admin shell color choice per user
This supersedes the earlier school-level toggle rule. Keep School.color admin-managed, but store the school-color versus semantic-primary choice on User.use_school_color_for_admin_ui. The update endpoint must be self-only. A true preference uses a valid school hex color; false or an invalid/missing color uses Vuetify primary.
