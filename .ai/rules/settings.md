---
paths:
  - resources/js/pages/admin/settings/Settings.vue
---

# Settings

## Keep students timetables management out of Settings
Do not expose a Schülerstundenpläne tab or teacher-roster panel under /admin/settings. Manage the timetable teacher roster only in the Students Timetables module at /admin/students-timetables/teachers/overview; legacy settings query URLs fall back to another available settings tab.
