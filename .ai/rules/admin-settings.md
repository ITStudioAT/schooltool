---
paths:
  - '{app/Services/AdminNavigationService.php,resources/routes/admin.js,resources/js/pages/admin/settings/Settings.vue}'
---

# Admin Settings

## Keep the profile in the dashboard navigation
Expose Profil as its own /admin/profile page in the left dashboard menu, immediately after Dokumentation and before Abmelden, using the existing profile capability. Do not embed or list it in Settings. Legacy settings?tab=profile links redirect to /admin/profile; users without another settings tab also fall back to that page.
