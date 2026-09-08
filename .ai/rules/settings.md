---
paths:
  - resources/js/pages/admin/settings/Settings.vue
---

# Settings

## Keep students timetables management out of Settings
Do not expose a Schülerstundenpläne tab or teacher-roster panel under /admin/settings. Manage the timetable teacher roster only in the Students Timetables module at /admin/students-timetables/teachers/overview; legacy settings query URLs fall back to another available settings tab.

## Use a single teacher list in teaching settings
Teaching settings opens Lehrer directly; do not restore the separate Lehrer/Lehrerliste switcher. Imports create or update teacher accounts, so the preregistration list is no longer a settings view. Retain the underlying Teacher source records used by class-head assignments and groups.

## Teacher account management moved into teaching administration
This supersedes the earlier default-Lehrer settings rule. Teacher account management now lives at /admin/teaching/administration?panel=teachers. Global teaching settings retain only teaching_admin (Import 116, Ferien, Schulstunden); old teachers panel queries normalize to that remaining panel. Reuse the same Teachers component at the new location.

## Global teaching settings forward to teaching administration
Supersedes the retained teaching_admin tools rule: Unterricht in global settings is a shortcut to /admin/teaching/administration?panel=import. Do not embed the old teaching Admin component or its submenu in Settings. Legacy teaching panel queries preserve teachers/import/holidays/school_hours; other values default to import. Accounts without administration roles fall back to Profil.

## Remove Unterricht from global settings navigation
Supersedes the Unterricht shortcut rule: do not show an Unterricht tab in dashboard Settings. Teaching administration is reached through Unterricht > Admin. Keep legacy settings?tab=teaching links redirecting to the corresponding administration panel.

## Match the Super Admin submenu to Materials 2 Admin
Use the Materials 2 Admin submenu appearance for the Super-Admin section selector only: dark slate bar, contiguous primary v-btn-toggle buttons, icons, and two-line labels with metadata badges. Keep the other settings tabs and nested panel menus unchanged.

## Match the Super Admin submenu to Materials 2 Admin
Use the Materials 2 Admin submenu appearance for the Super-Admin, Admin, Gruppen, and Restaurant section selectors: dark slate bar, contiguous primary v-btn-toggle buttons with subtle dividers, icons, and two-line labels with metadata badges. Keep the other settings tabs and nested panel menus unchanged.
