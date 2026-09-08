---
paths:
  - 'resources/js/pages/admin/teaching/Teaching.vue,resources/js/pages/admin/teaching/admin/TeacherAdministration.vue,routes/web.php'
---

# Teaching Admin

## Teaching administration follows Einstellungen
Place the Admin item immediately after Einstellungen for admin, super_admin, and teaching_admin. Its /admin/teaching/administration page currently contains only the Lehrer submenu and no content. Protect direct access on the Laravel route and Vue section navigation. Keep the existing legacy admin/Admin.vue import and holiday panels separate.

## Lehrer administration now hosts teacher management
The formerly empty Lehrer panel now embeds the existing Teachers component with hideBackButton=true, including import, search, CRUD, and account-state actions. Keep Admin immediately after Einstellungen. This UI move preserves existing backend account-management permissions; menu visibility alone does not authorize API mutations.

## Teaching administration owns school administration panels
Supersedes the earlier separate-panel rule: /admin/teaching/administration hosts Lehrer, Import 116, Ferien, and Schulstunden as sibling panels. Persist selection in query panel=teachers|import|holidays|school_hours and restore it on reload and browser history. Keep existing administration access checks and the missing-school-hours warning.
