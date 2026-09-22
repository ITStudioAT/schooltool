---
paths:
  - '{app/Services/AdminNavigationService.php,resources/routes/admin.js,resources/js/pages/admin/settings/Settings.vue}'
---

# Admin Settings

## Keep the profile in the dashboard navigation
Expose Profil as its own /admin/profile page in the left dashboard menu, immediately after Dokumentation and before Abmelden, using the existing profile capability. Do not embed or list it in Settings. Legacy settings?tab=profile links redirect to /admin/profile; users without another settings tab also fall back to that page.

## Show global settings only for settings management roles
The global Einstellungen menu and settings route capability require a role with an actual global settings section: super_admin, admin, register_admin, teaching_admin, materials_admin, materials_moderator, or lunch_admin. Admin-shell access alone is insufficient; teacher, ABA-only, timetable-only, and custom shell-only roles keep the separate Profil item without Einstellungen.
