---
paths:
  - '{app/Jobs/Teaching/Import116Job.php,app/Http/Controllers/Admin/Teaching/Import116Controller.php,resources/js/pages/admin/teaching/admin/import116/Import116.vue}'
---

# Import116

## Show affected students in Import 116 warning details
Persist every school-level warning with the imported student's full name, class, student code and Excel row. Completion events and run previews may show only the first 10 warnings; expanded run details must expose all stored warnings, including unchanged students. Retain string-warning compatibility for older import reports.
