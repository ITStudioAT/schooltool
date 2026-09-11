---
paths:
  - '{resources/js/pages/admin/studentsTimetables/timetable/Timetable.vue,resources/js/pages/admin/teaching/admin/import116/Import116.vue}'
---

# Admin Import116

## Keep both Import 116 detail views aligned
Students Timetables embeds its own Import 116 history/detail UI in timetable/Timetable.vue; it does not render teaching/admin/import116/Import116.vue. Show warning counts and report_summary.warnings in both screens. Cover the actual /students-timetables/timetable/imports/import116 route with a mounted UI test, including warning-only unchanged students.

## Load Import 116 history when its page becomes active
Students Timetables must request Import 116 runs immediately on direct entry and when navigating back to import116, including when role configuration arrives after mount. Reload runs on personal-schoolyear changes. The mounted regression must show history without first clicking Aktualisieren; loading general import-button metadata does not load Import 116 history.
