---
paths:
  - '{app/Models/TeachingCourseStudent.php,app/Http/Controllers/Admin/Teaching/**,resources/js/pages/admin/teaching/**}'
---

# Pages Admin Teaching

## Keep sensitive course student information separate
Besondere Informationen may contain health information. Store special_information encrypted and hidden; read/write it only through the course-authorized dedicated special-information endpoint. General course payloads and overview badges expose only has_special_information, never sensitive text. Do not include it in student-facing payloads or ordinary exports, and preserve it when normal course edits omit it.
