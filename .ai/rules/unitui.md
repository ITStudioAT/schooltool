---
paths:
  - '{app/Jobs/ImportTeachersListJob.php,config/schooltool.php,resources/js/pages/admin/superAdmin/components/TeachersList.vue,tests/{Unit,ui}/**/*TeachersList*}'
---

# Unitui

## Keep teacher-list CSV compatibility
Teacher-list imports support XLS/XLSX and UTF-8 CSV. CSV parsing must preserve comma-delimited files and detect semicolon-delimited exports; recognize Kürzel/Familienname/Vorname/EMail as equivalents of the canonical teacher columns. Keep upload allowlists, UI copy, and synthetic tests aligned without committing real staff exports.
