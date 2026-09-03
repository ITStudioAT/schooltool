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
