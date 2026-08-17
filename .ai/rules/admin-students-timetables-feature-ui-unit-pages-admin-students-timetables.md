---
paths:
  - 'resources/views/pdfs/students-timetable-overview.blade.php,resources/js/pages/admin/studentsTimetables/timetableV3/TimetableV3.vue,app/Http/Controllers/Admin/StudentsTimetables/StudentsTimetablesController.php,tests/Feature/StudentsTimetablesModuleTest.php,tests/ui/unit/pages/admin/studentsTimetables/TimetableV3.test.ts'
---

# Admin Students Timetables Feature Ui Unit Pages Admin Students Timetables

## Show PDF instruction-type labels
Manual timetable PDF course payloads must preserve is_fu, is_kompaktunterricht, and is_block. Render the applicable labels as Fernunterricht, Kompaktunterricht, and Block in the timetable cell information row; Kompaktunterricht remains excluded from Fernunterricht.
